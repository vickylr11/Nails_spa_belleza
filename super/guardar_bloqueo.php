<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: bloqueos.php");
    exit;
}

$accion = $_POST['accion'] ?? '';


// ==========================================
// QUITAR UN BLOQUEO
// Un bloqueo no es historia (como una cita o un pago): se puede borrar.
// Solo los de hoy en adelante, y la manicurista solo los suyos.
// ==========================================

if ($accion === 'quitar') {

    $id = intval($_POST['id_bloqueo'] ?? 0);

    $stmt = $conexion->prepare("SELECT id_manicurista, fecha FROM bloqueos WHERE id_bloqueo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $bloqueo = $stmt->get_result()->fetch_assoc();

    if (!$bloqueo) {
        volver_con_mensaje("bloqueos.php", "Ese bloqueo ya no existe.");
    }

    if (!es_admin() && intval($bloqueo['id_manicurista']) !== intval($_SESSION['id_manicurista'])) {
        header("Location: bloqueos.php?error=" . urlencode("Solo puedes quitar tus propios bloqueos."));
        exit;
    }

    if ($bloqueo['fecha'] < date('Y-m-d')) {
        header("Location: bloqueos.php?error=" . urlencode("Ese bloqueo ya pasó."));
        exit;
    }

    $stmt = $conexion->prepare("DELETE FROM bloqueos WHERE id_bloqueo = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    volver_con_mensaje("bloqueos.php", "Bloqueo quitado: esas horas vuelven a quedar libres.");
}


if ($accion !== 'crear') {
    header("Location: bloqueos.php");
    exit;
}


// ==========================================
// CREAR UN BLOQUEO — se revisa TODO antes de guardar
// ==========================================

// ¿De quién? La manicurista: de ella misma (sale de su SESIÓN, no del
// formulario, así no puede bloquear a otra con F12). El administrador: la que escoja.
$id_manicurista = es_admin()
    ? intval($_POST['id_manicurista'] ?? 0)
    : intval($_SESSION['id_manicurista']);

$fecha = $_POST['fecha'] ?? '';
$todo_el_dia = isset($_POST['todo_el_dia']);
$motivo = trim($_POST['motivo'] ?? '');


// La manicurista existe y está activa.
$stmt = $conexion->prepare("SELECT nombre FROM manicuristas WHERE id_manicurista = ? AND activa = 1");
$stmt->bind_param("i", $id_manicurista);
$stmt->execute();

$manicurista = $stmt->get_result()->fetch_assoc();

if (!$manicurista) {
    volver_con_error("bloqueos.php", "Escoge una manicurista activa.");
}

// Fecha válida, de hoy en adelante.
$ts = strtotime($fecha);

if ($ts === false || date('Y-m-d', $ts) !== $fecha) {
    volver_con_error("bloqueos.php", "La fecha no es válida.");
}

if ($fecha < date('Y-m-d')) {
    volver_con_error("bloqueos.php", "No se puede bloquear un día que ya pasó.");
}

if (mb_strlen_seguro($motivo) > 100) {
    volver_con_error("bloqueos.php", "El motivo puede tener máximo 100 caracteres.");
}

// Horario del salón ese día (date('N'): 1 = lunes ... 7 = domingo = id_horario).
$dia = date('N', $ts);

$stmt = $conexion->prepare("SELECT hora_apertura, hora_cierre, disponible FROM horarios WHERE id_horario = ?");
$stmt->bind_param("i", $dia);
$stmt->execute();

$horario = $stmt->get_result()->fetch_assoc();

if (!$horario || !$horario['disponible']) {
    volver_con_error("bloqueos.php", "Ese día el salón no atiende: no hace falta bloquearlo.");
}

// Desde y hasta.
if ($todo_el_dia) {

    // Todo el día = desde que abre hasta que cierra.
    $hora_inicio = $horario['hora_apertura'];
    $hora_fin = $horario['hora_cierre'];

} else {

    $hora_inicio = $_POST['hora_inicio'] ?? '';
    $hora_fin = $_POST['hora_fin'] ?? '';

    // HH:MM -> HH:MM:00
    if (!preg_match('/^\d{2}:\d{2}$/', $hora_inicio) || !preg_match('/^\d{2}:\d{2}$/', $hora_fin)) {
        volver_con_error("bloqueos.php", "Escribe desde qué hora y hasta qué hora, o marca «Todo el día».");
    }

    $hora_inicio .= ":00";
    $hora_fin .= ":00";

    if ($hora_inicio >= $hora_fin) {
        volver_con_error("bloqueos.php", "La hora «Hasta» debe ser después de «Desde».");
    }
}

// Si es hoy, el rato no puede haber terminado ya.
if (strtotime("$fecha $hora_fin") <= time()) {
    volver_con_error("bloqueos.php", "Ese rato ya pasó.");
}


// ==========================================
// GUARDAR (en transacción)
// La fila de la manicurista queda bloqueada (FOR UPDATE), igual que al
// reservar: si una clienta está reservando justo en ese rato, una de las
// dos espera a la otra y la segunda ve lo que hizo la primera.
// ==========================================

$conexion->begin_transaction();

$stmt = $conexion->prepare("SELECT id_manicurista FROM manicuristas WHERE id_manicurista = ? FOR UPDATE");
$stmt->bind_param("i", $id_manicurista);
$stmt->execute();
$stmt->get_result();


// ¿Tiene citas en ese rato? Entonces no se bloquea: la clienta quedaría
// con una cita que nadie va a atender. Se dicen cuáles son.
$stmt = $conexion->prepare("
    SELECT r.hora, c.nombre AS cliente, s.nombre AS servicio
    FROM reservas r
    INNER JOIN clientes c ON c.id_cliente = r.id_cliente
    INNER JOIN servicios s ON s.id_servicio = r.id_servicio
    WHERE r.fecha = ?
    AND r.id_manicurista = ?
    AND r.estado NOT IN ('Cancelada', 'No asistió')
    AND r.hora < ?
    AND ADDTIME(r.hora, SEC_TO_TIME(r.duracion * 60)) > ?
    ORDER BY r.hora
");
$stmt->bind_param("siss", $fecha, $id_manicurista, $hora_fin, $hora_inicio);
$stmt->execute();

$citas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (count($citas) > 0) {

    $conexion->rollback();

    $lista = [];

    foreach ($citas as $c) {
        $lista[] = date('g:i A', strtotime($c['hora'])) . " " . $c['cliente'] . " (" . $c['servicio'] . ")";
    }

    volver_con_error(
        "bloqueos.php",
        $manicurista['nombre'] . " tiene " . (count($citas) === 1 ? "una cita" : count($citas) . " citas") .
        " en ese rato: " . implode(", ", $lista) . ". " .
        (es_admin()
            ? "Muévala o cancélela primero en la Agenda."
            : "Pídele al administrador que la mueva o la cancele primero.")
    );
}


// ¿Ya hay un bloqueo que se cruza? (para no repetir)
$stmt = $conexion->prepare("
    SELECT id_bloqueo
    FROM bloqueos
    WHERE fecha = ? AND id_manicurista = ?
    AND hora_inicio < ? AND hora_fin > ?
");
$stmt->bind_param("siss", $fecha, $id_manicurista, $hora_fin, $hora_inicio);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    $conexion->rollback();
    volver_con_error("bloqueos.php", "Ya hay un bloqueo que se cruza con ese rato. Quítalo primero si quieres cambiarlo.");
}


$motivo_guardar = $motivo === '' ? null : $motivo;

$stmt = $conexion->prepare("
    INSERT INTO bloqueos (id_manicurista, fecha, hora_inicio, hora_fin, motivo, id_usuario)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("issssi", $id_manicurista, $fecha, $hora_inicio, $hora_fin, $motivo_guardar, $_SESSION['usuario_id']);
$stmt->execute();

$conexion->commit();

volver_con_mensaje(
    "bloqueos.php",
    "Listo: " . $manicurista['nombre'] . " queda sin citas el " . date('d/m/Y', $ts) .
    " de " . date('g:i A', strtotime($hora_inicio)) . " a " . date('g:i A', strtotime($hora_fin)) . "."
);
