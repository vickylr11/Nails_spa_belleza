
<?php

session_start();

date_default_timezone_set('America/Bogota');

require_once "../conexion/conexion.php";


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

$fecha = trim($_POST['fecha'] ?? '');
$hora = trim($_POST['hora'] ?? '');

$id_manicurista = intval($_SESSION['id_manicurista'] ?? 0);

$manicurista = '';


// ==========================================
// VALIDAR CAMPOS
// ==========================================

if (
    $nombre === '' ||
    $telefono === '' ||
    $id_servicio <= 0 ||
    $fecha === '' ||
    $hora === '' ||
    $id_manicurista <= 0
) {

    regresarReserva(
        "Completa todos los campos obligatorios."
    );
}


// ==========================================
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
// ==========================================

if ($hora === '12:00:00') {

    regresarReserva(
        "De 12:00 PM a 1:00 PM es horario de descanso."
    );
}


// ==========================================
// COMPROBAR SERVICIO ACTIVO
// ==========================================

$stmt = $conexion->prepare("
    SELECT id_servicio
    FROM servicios
    WHERE id_servicio = ?
    AND activo = 1
");

$stmt->bind_param("i", $id_servicio);

$stmt->execute();

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

header(
    "Location: ../paginas/agenda.php?mensaje=" .
    urlencode("La reserva fue creada correctamente.")
);

exit;

?>