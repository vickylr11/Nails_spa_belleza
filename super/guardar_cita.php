<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";
require_once "../includes/agenda.php";
require_once "../includes/fidelidad.php";

exigir_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: cita_nueva.php");
    exit;
}

function volver($mensaje)
{
    volver_con_error("cita_nueva.php", $mensaje);
}


// ==========================================
// RECIBIR Y VALIDAR
// ==========================================

$nombre = trim($_POST['nombre'] ?? '');

$telefono = trim($_POST['telefono'] ?? '');

$id_servicio = intval($_POST['id_servicio'] ?? 0);

$id_manicurista = intval($_POST['id_manicurista'] ?? 0);

$fecha = $_POST['fecha'] ?? '';

$hora = $_POST['hora'] ?? '';

$estado = $_POST['estado'] ?? '';


// El celular es la llave de la clienta: siempre 10 dígitos.
$telefono = normalizar_telefono($telefono);

if ($telefono === '') {
    volver("Escribe el celular de 10 dígitos (ej. 300 123 4567).");
}

// Si la clienta ya existe se usa su nombre de la base; si es nueva, hay que escribirlo.
$stmt = $conexion->prepare("SELECT nombre FROM clientes WHERE telefono = ?");
$stmt->bind_param("s", $telefono);
$stmt->execute();

$existente = $stmt->get_result()->fetch_assoc();

if ($existente) {
    $nombre = $existente['nombre'];
} elseif (mb_strlen_seguro($nombre) < 3) {
    volver("Es una clienta nueva: escribe su nombre.");
}

if (!in_array($estado, ['Confirmada', 'Completada'], true)) {
    volver("Escoge en qué va la cita.");
}

$inicio = strtotime("$fecha $hora");

if ($inicio === false) {
    volver("La fecha o la hora no son válidas.");
}

$ahora = time();

if ($estado === 'Confirmada') {

    // La van a atender: puede ser ahora (hasta 30 minutos atrás) o después.
    if ($inicio < $ahora - 30 * 60) {
        volver("Esa hora ya pasó hace más de 30 minutos. Si ya la atendieron, escoge «Ya la atendieron».");
    }

} else {

    // Ya la atendieron: tuvo que empezar antes de ahora, y no hace más de 60 días.
    if ($inicio > $ahora) {
        volver("Un servicio que ya se hizo no puede tener una hora futura.");
    }

    if ($inicio < strtotime('-60 days', $ahora)) {
        volver("Solo se pueden registrar servicios de los últimos 60 días.");
    }
}


// ==========================================
// LA MISMA REGLA DE LA AGENDA (con permiso para horas pasadas)
// Transacción + FOR UPDATE, igual que en la página pública.
// ==========================================

$conexion->begin_transaction();

$bloqueo = $conexion->prepare("SELECT id_manicurista FROM manicuristas WHERE id_manicurista = ? FOR UPDATE");
$bloqueo->bind_param("i", $id_manicurista);
$bloqueo->execute();
$bloqueo->get_result();

$motivo = revisar_cita($conexion, $fecha, $hora, $id_servicio, $id_manicurista, true);

if ($motivo !== "") {
    $conexion->rollback();
    volver($motivo);
}


// ==========================================
// CLIENTA Y TARJETA DE FIDELIDAD
// Si tiene una cita gratis ganada, se usa, salvo que el administrador
// haya quitado el chulo de «Usar la cita gratis» (la clienta la guarda
// para otro día).
// ==========================================

$id_cliente = buscar_o_crear_cliente($conexion, $telefono, $nombre);

$tarjeta = tarjeta_cliente($conexion, $id_cliente, true);

$no_usar = isset($_POST['gratis_mostrado']) && !isset($_POST['usar_gratis']);

$gratis = ($tarjeta['disponibles'] > 0 && !$no_usar) ? 1 : 0;


// ==========================================
// GUARDAR (origen = salon)
// ==========================================

$stmt = $conexion->prepare("SELECT precio, duracion FROM servicios WHERE id_servicio = ?");
$stmt->bind_param("i", $id_servicio);
$stmt->execute();
$servicio = $stmt->get_result()->fetch_assoc();

$fecha_limpia = date('Y-m-d', $inicio);
$hora_limpia = date('H:i:s', $inicio);

$stmt = $conexion->prepare("
    INSERT INTO reservas
    (id_cliente, id_servicio, id_manicurista, fecha, hora, precio, duracion, gratis, origen, estado)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'salon', ?)
");
$stmt->bind_param(
    "iiissdiis",
    $id_cliente,
    $id_servicio,
    $id_manicurista,
    $fecha_limpia,
    $hora_limpia,
    $servicio['precio'],
    $servicio['duracion'],
    $gratis,
    $estado
);
$stmt->execute();

$conexion->commit();

header(
    "Location: agenda.php?fecha=" . $fecha_limpia .
    "&mensaje=" . urlencode(
        "Cita guardada: " . $nombre . " a las " . date('h:i A', $inicio) .
        ($estado === 'Completada' ? " (ya atendida)." : ".") .
        ($gratis ? " 🎁 Es su cita GRATIS de la tarjeta de fidelidad." : "")
    )
);

exit;
