<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header("Location: agenda.php");

    exit;
}


$id = intval($_POST['id'] ?? 0);

$estado = $_POST['estado'] ?? '';

$fecha = $_POST['fecha'] ?? date('Y-m-d');


// Solo se aceptan los estados que ese ROL puede poner.
// Nunca se borra una cita: se cambia su estado.
// Pendiente, Confirmada y Completada ocupan la hora;
// Cancelada y No asistió la liberan.
if (es_admin()) {
    $permitidos = ['Pendiente', 'Confirmada', 'Completada', 'No asistió', 'Cancelada'];
} else {
    $permitidos = ['Confirmada', 'Completada', 'No asistió'];
}

if ($id <= 0 || !in_array($estado, $permitidos, true)) {

    header("Location: agenda.php?fecha=" . urlencode($fecha));

    exit;
}


// Una cita Cancelada o No asistió ya soltó su hora y otra
// clienta pudo haberla tomado. Si se "reviviera", quedarían
// dos citas cruzadas. Por eso no se revive: se crea una nueva.
$stmt = $conexion->prepare("
    SELECT estado, id_manicurista, id_pago, fecha, hora
    FROM reservas
    WHERE id_reserva = ?
");

$stmt->bind_param("i", $id);
$stmt->execute();

$actual = $stmt->get_result()->fetch_assoc();

// La manicurista solo puede cambiar SUS citas. Se revisa aquí, en el
// servidor: el id de la cita viene del formulario y se puede cambiar con F12.
if (
    $actual &&
    !es_admin() &&
    intval($actual['id_manicurista']) !== intval($_SESSION['id_manicurista'])
) {

    header(
        "Location: agenda.php?fecha=" . urlencode($fecha) .
        "&mensaje=" . urlencode("Solo puedes cambiar tus propias citas.")
    );

    exit;
}

// Una cita que ya se le pagó a la manicurista no cambia: si se pudiera
// pasar a Cancelada, el pago quedaría descuadrado.
if ($actual && $actual['id_pago']) {

    header(
        "Location: agenda.php?fecha=" . urlencode($fecha) .
        "&mensaje=" . urlencode("Esa cita ya se le pagó a la manicurista y no se puede cambiar.")
    );

    exit;
}

// Una cita no se puede marcar Completada ni "No asistió" antes de su hora:
// todavía no ha pasado. Si se pudiera, se ganarían sellos de la tarjeta
// de fidelidad o pagos a la manicurista por servicios que no se han hecho.
if (
    $actual &&
    in_array($estado, ['Completada', 'No asistió'], true) &&
    strtotime($actual['fecha'] . ' ' . $actual['hora']) > time()
) {

    header(
        "Location: agenda.php?fecha=" . urlencode($fecha) .
        "&mensaje=" . urlencode("Esa cita todavía no ha llegado: no se puede marcar como $estado.")
    );

    exit;
}

$liberan = ['Cancelada', 'No asistió'];

if (!$actual || (in_array($actual['estado'], $liberan, true) && !in_array($estado, $liberan, true))) {

    header(
        "Location: agenda.php?fecha=" . urlencode($fecha) .
        "&mensaje=" . urlencode("Una cita cancelada no se puede volver a activar. Crea una cita nueva.")
    );

    exit;
}


$stmt = $conexion->prepare("
    UPDATE reservas
    SET estado = ?
    WHERE id_reserva = ?
");

$stmt->bind_param("si", $estado, $id);

$stmt->execute();


header(
    "Location: agenda.php?fecha=" . urlencode($fecha) .
    "&mensaje=" . urlencode("La cita quedó como: $estado.")
);

exit;
