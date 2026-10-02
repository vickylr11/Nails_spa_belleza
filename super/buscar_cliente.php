<?php

// Busca una clienta por su celular y devuelve su tarjeta de fidelidad (JSON).
// La usa el formulario de «Cita en el salón» mientras se escribe el celular.
// Solo el administrador: los datos de las clientas no son públicos.

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/fidelidad.php";

exigir_admin();

header("Content-Type: application/json; charset=UTF-8");

$telefono = normalizar_telefono($_GET['telefono'] ?? '');

if ($telefono === '') {
    echo json_encode(['valido' => false]);
    exit;
}

$stmt = $conexion->prepare("SELECT id_cliente, nombre FROM clientes WHERE telefono = ?");
$stmt->bind_param("s", $telefono);
$stmt->execute();

$cliente = $stmt->get_result()->fetch_assoc();

if (!$cliente) {
    echo json_encode(['valido' => true, 'existe' => false]);
    exit;
}

$tarjeta = tarjeta_cliente($conexion, $cliente['id_cliente']);

echo json_encode([
    'valido' => true,
    'existe' => true,
    'nombre' => $cliente['nombre'],
    'pagadas' => $tarjeta['pagadas'],
    'sellos' => $tarjeta['sellos'],
    'meta' => CITAS_PARA_GRATIS,
    'disponibles' => $tarjeta['disponibles'],
    'texto' => texto_tarjeta($tarjeta)
]);
