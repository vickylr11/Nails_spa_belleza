<?php

require_once "../conexion/conexion.php";


// ==========================================
// SOLO PERMITIR POST
// ==========================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: ../paginas/reservar.php");
    exit;
}


// ==========================================
// RECIBIR DATOS
// ==========================================

$nombre = trim($_POST['nombre'] ?? '');
$telefono = trim($_POST['telefono'] ?? '');
$correo = trim($_POST['correo'] ?? '');
$id_servicio = intval($_POST['id_servicio'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$hora = $_POST['hora'] ?? '';


// ==========================================
// VALIDAR DATOS
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
// COMPROBAR QUE EL SERVICIO EXISTE
// ==========================================

$stmt = $conexion->prepare("
    SELECT id_servicio
    FROM servicios
    WHERE id_servicio = ?
    AND activo = 1
");

if (!$stmt) {
    die("Error SQL al buscar el servicio: " . $conexion->error);
}

$stmt->bind_param("i", $id_servicio);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {
    die("El servicio seleccionado no existe o está inactivo.");
}

$stmt->close();


// ==========================================
// COMPROBAR HORARIO DISPONIBLE
// ==========================================

$stmt = $conexion->prepare("
    SELECT id_reserva
    FROM reservas
    WHERE fecha = ?
    AND hora = ?
    AND estado != 'Cancelada'
");

if (!$stmt) {
    die("Error SQL al comprobar el horario: " . $conexion->error);
}

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

$stmt->close();


// ==========================================
// BUSCAR CLIENTE POR TELÉFONO
// ==========================================

$stmt = $conexion->prepare("
    SELECT id_cliente
    FROM clientes
    WHERE telefono = ?
");

if (!$stmt) {
    die("Error SQL al buscar el cliente: " . $conexion->error);
}

$stmt->bind_param("s", $telefono);
$stmt->execute();

$resultado = $stmt->get_result();


// ==========================================
// CLIENTE EXISTENTE
// ==========================================

if ($resultado->num_rows > 0) {

    $cliente = $resultado->fetch_assoc();

    $id_cliente = intval($cliente['id_cliente']);

    $stmt->close();


    // Actualizar datos del cliente

    $actualizar = $conexion->prepare("
        UPDATE clientes
        SET nombre = ?, correo = ?
        WHERE id_cliente = ?
    ");

    if (!$actualizar) {
        die("Error SQL al actualizar cliente: " . $conexion->error);
    }

    $actualizar->bind_param(
        "ssi",
        $nombre,
        $correo,
        $id_cliente
    );

    if (!$actualizar->execute()) {
        die(
            "Error al actualizar el cliente: " .
            $actualizar->error
        );
    }

    $actualizar->close();


// ==========================================
// CLIENTE NUEVO
// ==========================================

} else {

    $stmt->close();

    $insertar = $conexion->prepare("
        INSERT INTO clientes
        (nombre, telefono, correo)
        VALUES (?, ?, ?)
    ");

    if (!$insertar) {
        die("Error SQL al crear cliente: " . $conexion->error);
    }

    $insertar->bind_param(
        "sss",
        $nombre,
        $telefono,
        $correo
    );

    if (!$insertar->execute()) {
        die(
            "Error al crear el cliente: " .
            $insertar->error
        );
    }

    $id_cliente = $conexion->insert_id;

    $insertar->close();
}


// ==========================================
// GUARDAR RESERVA
// ==========================================

$insertar_reserva = $conexion->prepare("
    INSERT INTO reservas
    (id_cliente, id_servicio, fecha, hora, estado)
    VALUES (?, ?, ?, ?, 'Pendiente')
");

if (!$insertar_reserva) {
    die(
        "ERROR AL PREPARAR LA RESERVA: " .
        $conexion->error
    );
}

$insertar_reserva->bind_param(
    "iiss",
    $id_cliente,
    $id_servicio,
    $fecha,
    $hora
);


if (!$insertar_reserva->execute()) {

    die(
        "ERROR AL GUARDAR LA RESERVA: " .
        $insertar_reserva->error
    );

}

$insertar_reserva->close();


// ==========================================
// TODO CORRECTO
// ==========================================

header(
    "Location: ../paginas/agenda.php?mensaje=" .
    urlencode("La reserva fue creada correctamente.")
);

exit;

?>