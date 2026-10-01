<?php

require_once "../conexion/conexion.php";
require_once "../includes/agenda.php";
require_once "../includes/fidelidad.php";


// Devuelve al formulario con un mensaje y no sigue.
function volver($mensaje)
{
    // Se guarda un momento lo que escogió (día, manicurista, servicio, datos)
    // para que el formulario lo vuelva a mostrar y no tenga que empezar de cero.
    $_SESSION['reserva_vieja'] = array_intersect_key($_POST, array_flip([
        'nombre', 'telefono', 'correo', 'fecha', 'id_manicurista', 'id_servicio'
    ]));

    header(
        "Location: ../paginas/reservar.php?error=" .
        urlencode($mensaje)
    );

    exit;
}


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

$id_manicurista = intval($_POST['id_manicurista'] ?? 0);

$fecha = $_POST['fecha'] ?? '';

$hora = $_POST['hora'] ?? '';


// ==========================================
// CAMPOS OBLIGATORIOS
// ==========================================

if (
    $nombre === '' ||
    $telefono === '' ||
    $id_servicio <= 0 ||
    $id_manicurista <= 0 ||
    $fecha === '' ||
    $hora === ''
) {
    volver("Completa todos los campos obligatorios.");
}

// El celular se guarda siempre igual (10 dígitos): con él se reconoce
// a la clienta y se le cuentan las citas para la tarjeta de fidelidad.
$telefono = normalizar_telefono($telefono);

if ($telefono === '') {
    volver("Escribe tu celular de 10 dígitos (ej. 300 123 4567).");
}


// ==========================================
// ¿SE PUEDE TOMAR ESA CITA?
// El servidor vuelve a revisar TODO, aunque el formulario
// ya haya deshabilitado las horas ocupadas: el JavaScript
// se puede apagar o cambiar con F12.
// La regla completa está en includes/agenda.php.
// ==========================================

// Transacción: mientras se revisa y se guarda, la fila de la
// manicurista queda "bloqueada" (FOR UPDATE). Si dos clientas
// confirman la misma hora con la misma manicurista al mismo tiempo,
// la segunda espera a que la primera termine, y al revisar ya ve
// la cita nueva. Sin esto, las dos podrían pasar la revisión.
$conexion->begin_transaction();

$bloqueo = $conexion->prepare("
    SELECT id_manicurista
    FROM manicuristas
    WHERE id_manicurista = ?
    FOR UPDATE
");
$bloqueo->bind_param("i", $id_manicurista);
$bloqueo->execute();
$bloqueo->get_result();

$motivo = revisar_cita($conexion, $fecha, $hora, $id_servicio, $id_manicurista);

if ($motivo !== "") {
    $conexion->rollback();
    volver($motivo);
}

$inicio = strtotime("$fecha $hora");

$fecha_limpia = date('Y-m-d', $inicio);

$hora_limpia = date('H:i:s', $inicio);


// ==========================================
// BUSCAR O CREAR LA CLIENTA POR SU CELULAR
// y revisar su tarjeta de fidelidad: si tiene una cita gratis
// ganada, ESTA cita sale gratis.
// ==========================================

$id_cliente = buscar_o_crear_cliente($conexion, $telefono, $nombre, $correo);

$tarjeta = tarjeta_cliente($conexion, $id_cliente, true);

$gratis = $tarjeta['disponibles'] > 0 ? 1 : 0;


// ==========================================
// GUARDAR LA RESERVA
// Se copian el precio y la duración que tiene HOY el servicio:
// si mañana el salón los cambia, esta cita queda como se reservó.
// ==========================================

$stmt = $conexion->prepare("
    SELECT precio, duracion
    FROM servicios
    WHERE id_servicio = ?
");
$stmt->bind_param("i", $id_servicio);
$stmt->execute();
$servicio = $stmt->get_result()->fetch_assoc();

$insertar_reserva = $conexion->prepare("
    INSERT INTO reservas
    (id_cliente, id_servicio, id_manicurista, fecha, hora, precio, duracion, gratis, estado)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pendiente')
");

$insertar_reserva->bind_param(
    "iiissdii",
    $id_cliente,
    $id_servicio,
    $id_manicurista,
    $fecha_limpia,
    $hora_limpia,
    $servicio['precio'],
    $servicio['duracion'],
    $gratis
);

$insertar_reserva->execute();

$conexion->commit();


// ==========================================
// TODO CORRECTO: la clienta vuelve al formulario
// con su confirmación (la agenda es solo del personal).
// ==========================================

$mensaje = "¡Listo! Tu cita quedó reservada para el " .
    date('d/m/Y', $inicio) . " a las " . date('h:i A', $inicio) . ". ";

if ($gratis) {

    $mensaje .= "🎁 ¡Esta cita es GRATIS! Es tu cita número " . (CITAS_PARA_GRATIS + 1) . ". Gracias por preferirnos.";

} else {

    // Cuántas le faltan (la cita que acaba de reservar cuenta cuando se complete).
    $faltan = CITAS_PARA_GRATIS - $tarjeta['sellos'] - 1;

    $mensaje .= $faltan <= 0
        ? "Cuando te atiendan, tu próxima cita será GRATIS."
        : "Te " . ($faltan === 1 ? "falta 1 cita" : "faltan $faltan citas") . " más para tu cita gratis.";
}

header(
    "Location: ../paginas/reservar.php?ok=" . urlencode($mensaje)
);

exit;
