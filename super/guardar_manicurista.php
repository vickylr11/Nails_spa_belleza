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

if ($id > 0) {

    $stmt = $conexion->prepare("UPDATE manicuristas SET nombre = ?, porcentaje = ? WHERE id_manicurista = ?");
    $stmt->bind_param("sii", $nombre, $porcentaje, $id);
    $stmt->execute();

    volver_con_mensaje("manicuristas.php", "Manicurista actualizada.");

} else {

    $stmt = $conexion->prepare("INSERT INTO manicuristas (nombre, porcentaje) VALUES (?, ?)");
    $stmt->bind_param("si", $nombre, $porcentaje);
    $stmt->execute();

    volver_con_mensaje("manicuristas.php", "$nombre ya puede recibir citas. Para que vea su agenda, créele una cuenta en Usuarios.");
}
