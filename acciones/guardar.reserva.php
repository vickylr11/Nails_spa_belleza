<?php

require_once "../conexion/conexion.php";


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: ../paginas/reservar.php");

    exit;

}


$nombre = trim($_POST['nombre'] ?? '');

$telefono = trim($_POST['telefono'] ?? '');

$correo = trim($_POST['correo'] ?? '');

$id_servicio = intval($_POST['id_servicio'] ?? 0);

$fecha = $_POST['fecha'] ?? '';

$hora = $_POST['hora'] ?? '';


// ==========================================
// VALIDACIONES
// ==========================================

if (
    $nombre === '' ||
    $telefono === '' ||
    $id_servicio <= 0 ||
    $fecha === '' ||
    $hora === ''
) {

    header(
        "Location: ../paginas/reservar.php?error=" .
        urlencode("Completa todos los campos obligatorios.")
    );

    exit;

}


// ==========================================
// VALIDAR FECHA
// ==========================================

if ($fecha < date('Y-m-d')) {

    header(
        "Location: ../paginas/reservar.php?error=" .
        urlencode("No puedes reservar una fecha pasada.")
    );

    exit;

}


// ==========================================
// COMPROBAR HORARIO
// ==========================================

$stmt = $conexion->prepare("
    SELECT id_reserva
    FROM reservas
    WHERE fecha = ?
    AND hora = ?
    AND estado != 'Cancelada'
");

$stmt->bind_param(
    "ss",
    $fecha,
    $hora
);

$stmt->execute();

$resultado = $stmt->get_result();


if ($resultado->num_rows > 0) {

    header(
        "Location: ../paginas/reservar.php?error=" .
        urlencode("Ese horario ya está ocupado.")
    );

    exit;

}


// ==========================================
// BUSCAR CLIENTE
// ==========================================

$stmt = $conexion->prepare("
    SELECT id_cliente
    FROM clientes
    WHERE telefono = ?
");

$stmt->bind_param(
    "s",
    $telefono
);

$stmt->execute();

$resultado = $stmt->get_result();


if ($resultado->num_rows > 0) {

    $cliente = $resultado->fetch_assoc();

    $id_cliente = $cliente['id_cliente'];

    // Actualizar nombre y correo

    $actualizar = $conexion->prepare("
        UPDATE clientes
        SET nombre = ?, correo = ?
        WHERE id_cliente = ?
    ");

    $actualizar->bind_param(
        "ssi",
        $nombre,
        $correo,
        $id_cliente
    );

    $actualizar->execute();

} else {

    // Crear cliente

    $insertar = $conexion->prepare("
        INSERT INTO clientes
        (nombre, telefono, correo)
        VALUES (?, ?, ?)
    ");

    $insertar->bind_param(
        "sss",
        $nombre,
        $telefono,
        $correo
    );

    $insertar->execute();

    $id_cliente = $conexion->insert_id;

}


// ==========================================
// GUARDAR RESERVA
// ==========================================

$insertar_reserva = $conexion->prepare("
    INSERT INTO reservas
    (id_cliente, id_servicio, fecha, hora)
    VALUES (?, ?, ?, ?)
");

$insertar_reserva->bind_param(
    "iiss",
    $id_cliente,
    $id_servicio,
    $fecha,
    $hora
);


if ($insertar_reserva->execute()) {

    header(
        "Location: ../paginas/agenda.php?mensaje=" .
        urlencode("La reserva fue creada correctamente.")
    );

    exit;

} else {

    header(
        "Location: ../paginas/reservar.php?error=" .
        urlencode("No fue posible guardar la reserva.")
    );

    exit;

}