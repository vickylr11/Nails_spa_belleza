<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";


function volver($error)
{
    header("Location: clave.php?error=" . urlencode($error));
    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: clave.php");
    exit;
}

$actual = $_POST['actual'] ?? '';
$nueva = $_POST['nueva'] ?? '';
$repetir = $_POST['repetir'] ?? '';

if (strlen($nueva) < 8) {
    volver("La contraseña nueva debe tener mínimo 8 caracteres.");
}

if ($nueva !== $repetir) {
    volver("Las dos contraseñas nuevas no son iguales.");
}


// 1. La contraseña actual debe ser correcta
//    (si alguien deja la sesión abierta, otro no puede cambiarla).
$stmt = $conexion->prepare("
    SELECT id_usuario
    FROM usuarios
    WHERE id_usuario = ?
    AND clave = SHA2(CONCAT(sal, ?), 256)
");

$stmt->bind_param("is", $_SESSION['usuario_id'], $actual);
$stmt->execute();

if ($stmt->get_result()->num_rows === 0) {
    volver("La contraseña actual no es correcta.");
}


// 2. Sal nueva y clave nueva. La sal es un texto al azar distinto para
//    cada usuario: así dos personas con la misma clave no quedan con el
//    mismo código guardado.
$sal = bin2hex(random_bytes(8));

$stmt = $conexion->prepare("
    UPDATE usuarios
    SET sal = ?, clave = SHA2(CONCAT(?, ?), 256)
    WHERE id_usuario = ?
");

$stmt->bind_param("sssi", $sal, $sal, $nueva, $_SESSION['usuario_id']);
$stmt->execute();

header("Location: clave.php?mensaje=" . urlencode("Contraseña cambiada."));

exit;
