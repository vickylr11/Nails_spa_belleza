<?php

// Devuelve (JSON) las horas de un día como botones:
// {"abierto": true, "horas": [{"hora":"09:00:00","texto":"9:00 AM","disponible":true,"motivo":""}, ...]}
// Usa la misma regla que al guardar (includes/agenda.php): el navegador solo pinta.

require_once "../conexion/conexion.php";
require_once "../includes/agenda.php";

header("Content-Type: application/json; charset=UTF-8");

$fecha = $_GET['fecha'] ?? '';
$id_servicio = intval($_GET['servicio'] ?? 0);
$id_manicurista = intval($_GET['manicurista'] ?? 0);

// Los modos del salón solo valen si quien pregunta es el administrador.
// Una clienta que escriba ?modo=salon_completada a mano recibe el modo web.
$modo = $_GET['modo'] ?? 'web';

$es_admin = isset($_SESSION['usuario_rol']) && $_SESSION['usuario_rol'] === 'administrador';

if (!$es_admin || !in_array($modo, ['salon_confirmada', 'salon_completada'], true)) {
    $modo = 'web';
}

if ($fecha === '' || $id_servicio <= 0 || $id_manicurista <= 0) {
    echo json_encode(['abierto' => null, 'horas' => []]);
    exit;
}

echo json_encode(horas_del_dia($conexion, $fecha, $id_servicio, $id_manicurista, $modo));
