<?php

require_once "../conexion/conexion.php";

header("Content-Type: application/json; charset=UTF-8");

$fecha = $_GET['fecha'] ?? '';
$manicurista = trim($_GET['manicurista'] ?? '');

if ($fecha === '' || $manicurista === '') {
    echo json_encode([]);
    exit;
}

$stmt = $conexion->prepare("
    SELECT hora
    FROM reservas
    WHERE fecha = ?
    AND manicurista = ?
    AND estado != 'Cancelada'
");

if (!$stmt) {
    echo json_encode([]);
    exit;
}

$stmt->bind_param(
    "ss",
    $fecha,
    $manicurista
);

$stmt->execute();

$resultado = $stmt->get_result();

$horarios = [];

while ($fila = $resultado->fetch_assoc()) {
    $horarios[] = $fila['hora'];
}

$stmt->close();

echo json_encode($horarios);