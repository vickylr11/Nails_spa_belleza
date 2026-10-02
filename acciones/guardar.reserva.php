
<?php

session_start();

date_default_timezone_set('America/Bogota');

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
// FUNCION PARA REDIRECCIONAR CON MENSAJE
// ==========================================

function regresarReserva($mensaje) {

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

<<<<<<< HEAD
$fecha = trim($_POST['fecha'] ?? '');
$hora = trim($_POST['hora'] ?? '');

$id_manicurista = intval($_SESSION['id_manicurista'] ?? 0);

$manicurista = '';


// ==========================================
// VALIDAR CAMPOS
=======
$id_manicurista = intval($_POST['id_manicurista'] ?? 0);

$fecha = $_POST['fecha'] ?? '';

$hora = $_POST['hora'] ?? '';


// ==========================================
// CAMPOS OBLIGATORIOS
>>>>>>> profe
// ==========================================

if (
    $nombre === '' ||
    $telefono === '' ||
    $id_servicio <= 0 ||
    $id_manicurista <= 0 ||
    $fecha === '' ||
<<<<<<< HEAD
    $hora === '' ||
    $id_manicurista <= 0
) {

    regresarReserva(
        "Completa todos los campos obligatorios."
    );
=======
    $hora === ''
) {
    volver("Completa todos los campos obligatorios.");
}

// El celular se guarda siempre igual (10 dígitos): con él se reconoce
// a la clienta y se le cuentan las citas para la tarjeta de fidelidad.
$telefono = normalizar_telefono($telefono);

if ($telefono === '') {
    volver("Escribe tu celular de 10 dígitos (ej. 300 123 4567).");
>>>>>>> profe
}


// ==========================================
<<<<<<< HEAD
// VALIDAR FORMATO DE FECHA
// ==========================================

$fechaObjeto = DateTime::createFromFormat(
    '!Y-m-d',
    $fecha
);

if (
    !$fechaObjeto ||
    $fechaObjeto->format('Y-m-d') !== $fecha
) {

    regresarReserva("La fecha seleccionada no es válida.");
}


// ==========================================
// VALIDAR FORMATO DE HORA
// ==========================================

// Aceptar tanto 14:00 como 14:00:00

if (preg_match('/^\d{2}:\d{2}$/', $hora)) {
    $hora .= ':00';
}

$horaObjeto = DateTime::createFromFormat(
    '!H:i:s',
    $hora
);

if (
    !$horaObjeto ||
    $horaObjeto->format('H:i:s') !== $hora
) {

    regresarReserva("La hora seleccionada no es válida.");
}


// ==========================================
// VALIDAR FECHA Y HORA ACTUAL
// ==========================================

$ahora = new DateTime('now');

$fechaHoraReserva = DateTime::createFromFormat(
    '!Y-m-d H:i:s',
    $fecha . ' ' . $hora
);

if (!$fechaHoraReserva) {

    regresarReserva("No se pudo validar la fecha y hora.");
}


// No permitir fechas pasadas

if ($fechaObjeto < new DateTime('today')) {

    regresarReserva(
        "No puedes reservar una fecha pasada."
    );
}


// No permitir horas pasadas únicamente si es hoy

if (
    $fecha === $ahora->format('Y-m-d') &&
    $fechaHoraReserva <= $ahora
) {

    regresarReserva(
        "Esa hora ya pasó. Selecciona otra hora disponible."
    );
}


// ==========================================
// VALIDAR DESCANSO
=======
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
>>>>>>> profe
// ==========================================

$id_cliente = buscar_o_crear_cliente($conexion, $telefono, $nombre, $correo);

<<<<<<< HEAD
    regresarReserva(
        "De 12:00 PM a 1:00 PM es horario de descanso."
    );
}


// ==========================================
// COMPROBAR SERVICIO ACTIVO
=======
$tarjeta = tarjeta_cliente($conexion, $id_cliente, true);

$gratis = $tarjeta['disponibles'] > 0 ? 1 : 0;


// ==========================================
// GUARDAR LA RESERVA
// Se copian el precio y la duración que tiene HOY el servicio:
// si mañana el salón los cambia, esta cita queda como se reservó.
>>>>>>> profe
// ==========================================

$stmt = $conexion->prepare("
    SELECT precio, duracion
    FROM servicios
    WHERE id_servicio = ?
");
<<<<<<< HEAD

=======
>>>>>>> profe
$stmt->bind_param("i", $id_servicio);

$stmt->execute();
<<<<<<< HEAD

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {

    $stmt->close();

    regresarReserva(
        "El servicio seleccionado no existe o está inactivo."
    );
}

$stmt->close();



// ==========================================
// COMPROBAR MANICURISTA ACTIVA
// ==========================================

$stmt = $conexion->prepare("
    SELECT nombre
    FROM manicuristas
    WHERE id_manicurista = ?
    AND estado = 1
");

$stmt->bind_param("i", $id_manicurista);

$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows === 0) {

    $stmt->close();

    regresarReserva(
        "La manicurista seleccionada no está disponible."
    );
}

$datosManicurista = $resultado->fetch_assoc();

$manicurista = trim($datosManicurista['nombre']);

$stmt->close();

// ==========================================
// BLOQUEAR TEMPORALMENTE EL HORARIO
// ==========================================

// Esto evita que dos clientas intenten guardar
// simultáneamente la misma hora con la misma
// manicurista.

$identificadorBloqueo =
    $manicurista . '|' . $fecha . '|' . $hora;

$nombreBloqueo =
    'reserva_' . substr(
        hash('sha256', $identificadorBloqueo),
        0,
        55
    );

$bloqueoAdquirido = false;

$transaccionIniciada = false;


try {

    // ==========================================
    // ADQUIRIR BLOQUEO DEL HORARIO
    // ==========================================

    $stmt = $conexion->prepare("
        SELECT GET_LOCK(?, 10)
    ");

    $stmt->bind_param("s", $nombreBloqueo);

    $stmt->execute();

    $stmt->bind_result($resultadoBloqueo);

    $stmt->fetch();

    $stmt->close();


    if ((int)$resultadoBloqueo !== 1) {

        regresarReserva(
            "No se pudo verificar el horario. Intenta nuevamente."
        );
    }

    $bloqueoAdquirido = true;


    // ==========================================
    // INICIAR TRANSACCION
    // ==========================================

    $conexion->begin_transaction();

    $transaccionIniciada = true;


    // ==========================================
    // COMPROBAR SI YA ESTA OCUPADO
    // ==========================================

   $stmt = $conexion->prepare("
    SELECT id_reserva
    FROM reservas
    WHERE fecha = ?
    AND hora = ?
    AND TRIM(manicurista) = ?
    AND LOWER(TRIM(estado)) NOT IN (
        'cancelada',
        'cancelado'
    )
    LIMIT 1
");

    $stmt->bind_param(
        "sss",
        $fecha,
        $hora,
        $manicurista
    );

    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {

        $stmt->close();

        $conexion->rollback();

        $transaccionIniciada = false;

        regresarReserva(
            "Ese horario ya está ocupado para esta manicurista. Selecciona otro horario o una profesional diferente."
        );
    }

    $stmt->close();


    // ==========================================
    // BUSCAR CLIENTE POR TELEFONO
    // ==========================================

    $stmt = $conexion->prepare("
        SELECT id_cliente
        FROM clientes
        WHERE telefono = ?
        LIMIT 1
    ");

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

        $actualizar->close();


    } else {

        // ======================================
        // CLIENTE NUEVO
        // ======================================

        $stmt->close();

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

        $insertar->close();
    }


    // ==========================================
    // GUARDAR RESERVA
    // ==========================================

    $insertar_reserva = $conexion->prepare("
        INSERT INTO reservas
        (
            id_cliente,
            id_servicio,
            fecha,
            hora,
            estado,
            manicurista
        )
        VALUES (?, ?, ?, ?, 'Pendiente', ?)
    ");

    $insertar_reserva->bind_param(
        "iisss",
        $id_cliente,
        $id_servicio,
        $fecha,
        $hora,
        $manicurista
    );

    $insertar_reserva->execute();

    $insertar_reserva->close();


    // ==========================================
    // CONFIRMAR TRANSACCION
    // ==========================================

    $conexion->commit();

    $transaccionIniciada = false;


} catch (Throwable $e) {

    if ($transaccionIniciada) {
        $conexion->rollback();
    }

    error_log(
        "Error al guardar reserva: " . $e->getMessage()
    );

    regresarReserva(
        "Ocurrió un error al guardar la reserva. Intenta nuevamente."
    );


} finally {

    // ==========================================
    // LIBERAR BLOQUEO
    // ==========================================

    if ($bloqueoAdquirido) {

        $stmt = $conexion->prepare("
            SELECT RELEASE_LOCK(?)
        ");

        $stmt->bind_param("s", $nombreBloqueo);

        $stmt->execute();

        $stmt->close();
    }
}


// ==========================================
// RESERVA CORRECTAMENTE GUARDADA
// ==========================================

=======
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

>>>>>>> profe
header(
    "Location: ../paginas/reservar.php?ok=" . urlencode($mensaje)
);

exit;
