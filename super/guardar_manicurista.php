<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

exigir_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: manicuristas.php");
    exit;
}

$accion = $_POST['accion'] ?? '';

$id = intval($_POST['id_manicurista'] ?? 0);


// ==========================================
// ACTIVAR / DESACTIVAR
// Una manicurista nunca se borra: tiene citas en su historia.
// ==========================================

if ($accion === 'cambiar_activa') {

    $stmt = $conexion->prepare("SELECT nombre, activa FROM manicuristas WHERE id_manicurista = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $m = $stmt->get_result()->fetch_assoc();

    if (!$m) {
        volver_con_mensaje("manicuristas.php", "Esa manicurista no existe.");
    }

    if ($m['activa']) {

        // No se puede dejar el salón sin nadie que atienda.
        $activas = $conexion->query("SELECT COUNT(*) AS n FROM manicuristas WHERE activa = 1")
            ->fetch_assoc()['n'];

        if ($activas <= 1) {
            header("Location: manicuristas.php?error=" .
                urlencode("No se puede desactivar: es la única manicurista activa y el salón quedaría sin quién atienda."));
            exit;
        }

        // Si tiene citas por atender, quedarían huérfanas.
        $stmt = $conexion->prepare("
            SELECT COUNT(*) AS n
            FROM reservas
            WHERE id_manicurista = ?
            AND fecha >= CURDATE()
            AND estado IN ('Pendiente', 'Confirmada')
        ");
        $stmt->bind_param("i", $id);
        $stmt->execute();

        $pendientes = $stmt->get_result()->fetch_assoc()['n'];

        if ($pendientes > 0) {
            header("Location: manicuristas.php?error=" .
                urlencode($m['nombre'] . " tiene $pendientes " . ($pendientes == 1 ? "cita" : "citas") . " por atender. " .
                    "Cancélelas o atiéndalas antes de desactivarla."));
            exit;
        }
    }

    $stmt = $conexion->prepare("
        UPDATE manicuristas
        SET activa = 1 - activa
        WHERE id_manicurista = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    volver_con_mensaje(
        "manicuristas.php",
        $m['activa']
            ? $m['nombre'] . " ya no recibe citas nuevas."
            : $m['nombre'] . " vuelve a recibir citas."
    );
}


if ($accion !== 'guardar') {
    header("Location: manicuristas.php");
    exit;
}


// ==========================================
// CREAR / EDITAR
// ==========================================

$nombre = trim($_POST['nombre'] ?? '');

$porcentaje = $_POST['porcentaje'] ?? '';

if (mb_strlen_seguro($nombre) < 3 || mb_strlen_seguro($nombre) > 100) {
    volver_con_error("manicuristas.php", "El nombre debe tener entre 3 y 100 caracteres.", $id);
}

// Número entero de 0 a 100.
if (!ctype_digit((string) $porcentaje) || intval($porcentaje) > 100) {
    volver_con_error("manicuristas.php", "El porcentaje debe ser un número entero de 0 a 100.", $id);
}

$porcentaje = intval($porcentaje);

$stmt = $conexion->prepare("
    SELECT id_manicurista
    FROM manicuristas
    WHERE nombre = ?
    AND id_manicurista <> ?
");
$stmt->bind_param("si", $nombre, $id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    volver_con_error("manicuristas.php", "Ya hay una manicurista con ese nombre.", $id);
}

// ---------- Servicios que hace ----------
// Llegan como servicios[] = [1, 3, 4]. Se dejan solo los que existen y están activos.

$escogidos = array_map('intval', (array) ($_POST['servicios'] ?? []));

$activos = array_map('intval', array_column(
    $conexion->query("SELECT id_servicio FROM servicios WHERE activo = 1")->fetch_all(MYSQLI_ASSOC),
    'id_servicio'
));

$escogidos = array_values(array_unique(array_intersect($escogidos, $activos)));

if (count($escogidos) === 0) {
    volver_con_error("manicuristas.php", "Marca al menos un servicio que haga.", $id);
}


// ---------- Si se edita, debe existir ----------

$anterior = null;

if ($id > 0) {

    $stmt = $conexion->prepare("SELECT foto FROM manicuristas WHERE id_manicurista = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $anterior = $stmt->get_result()->fetch_assoc();

    if (!$anterior) {
        volver_con_mensaje("manicuristas.php", "Esa manicurista no existe.");
    }
}


// ---------- Foto ----------
// Se sube al final, cuando todo lo demás ya está bien
// (así no quedan fotos sueltas de formularios con error).

$foto = $anterior['foto'] ?? null;

$foto_nueva = !empty($_FILES['foto']['name']);
$quitar_foto = !$foto_nueva && isset($_POST['quitar_foto']);

if ($foto_nueva) {

    $resultado = subir_imagen($_FILES['foto'], 'manicuristas');

    if (str_starts_with($resultado, "ERROR: ")) {
        volver_con_error("manicuristas.php", substr($resultado, 7), $id);
    }

    $foto = $resultado;

} elseif ($quitar_foto) {

    $foto = null;
}


// ---------- Guardar ----------
// Transacción: la manicurista y sus servicios se guardan juntos, o nada.

$conexion->begin_transaction();

if ($id > 0) {

    $stmt = $conexion->prepare("UPDATE manicuristas SET nombre = ?, porcentaje = ?, foto = ? WHERE id_manicurista = ?");
    $stmt->bind_param("sisi", $nombre, $porcentaje, $foto, $id);
    $stmt->execute();

} else {

    $stmt = $conexion->prepare("INSERT INTO manicuristas (nombre, porcentaje, foto) VALUES (?, ?, ?)");
    $stmt->bind_param("sis", $nombre, $porcentaje, $foto);
    $stmt->execute();

    $id_nueva = $conexion->insert_id;
}

$id_m = $id > 0 ? $id : $id_nueva;

// Los servicios: se borran los de antes y se escriben los marcados.
// (Solo los activos: los de servicios desactivados se dejan como estaban,
// por si el servicio se vuelve a activar.)
$stmt = $conexion->prepare("
    DELETE ms FROM manicurista_servicio ms
    JOIN servicios s ON s.id_servicio = ms.id_servicio
    WHERE ms.id_manicurista = ? AND s.activo = 1
");
$stmt->bind_param("i", $id_m);
$stmt->execute();

$stmt = $conexion->prepare("INSERT INTO manicurista_servicio (id_manicurista, id_servicio) VALUES (?, ?)");

foreach ($escogidos as $id_servicio) {
    $stmt->bind_param("ii", $id_m, $id_servicio);
    $stmt->execute();
}

$conexion->commit();

// La foto vieja ya no se usa: se borra del servidor (solo si se subió desde el panel).
if (($foto_nueva || $quitar_foto) && $anterior) {
    borrar_imagen_subida($anterior['foto']);
}

if ($id > 0) {
    volver_con_mensaje("manicuristas.php", "Manicurista actualizada.");
}

volver_con_mensaje("manicuristas.php", "$nombre ya puede recibir citas. Para que vea su agenda, créele una cuenta en Usuarios.");
