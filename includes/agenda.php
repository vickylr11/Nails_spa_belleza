<?php

// ==========================================================
// LA REGLA DE LA AGENDA — escrita UNA sola vez.
// La usan:
//   acciones/guardar.reserva.php    (al confirmar la cita)
//   acciones/horas_disponibles.php  (los botones de horas del formulario)
// Si la regla cambia, se cambia solo aquí y las dos quedan iguales.
// ==========================================================

// Descanso del salón (nadie atiende en esta franja).
const DESCANSO_INICIO = "12:00:00";
const DESCANSO_FIN = "13:00:00";


// ==========================================================
// LAS HORAS DE UN DÍA, COMO BOTONES
// Devuelve cada hora en punto en que el salón abre ESE día, y para
// cada una si se puede tomar o por qué no. La usa
// acciones/horas_disponibles.php, que le entrega la lista al formulario.
//
// $modo:
//   'web'               la clienta en la página: nada en el pasado.
//   'salon_confirmada'  el administrador, clienta que va a ser atendida:
//                       se permite hasta 30 minutos atrás, y se agrega
//                       un botón «Ahora» con la hora actual.
//   'salon_completada'  el administrador registra un servicio ya hecho:
//                       solo horas que ya empezaron.
// ==========================================================
function horas_del_dia($conexion, $fecha, $id_servicio, $id_manicurista, $modo = 'web')
{
    $respuesta = ['abierto' => false, 'horas' => []];

    $dia_ts = strtotime($fecha);

    if ($dia_ts === false) {
        return $respuesta;
    }

    $fecha = date('Y-m-d', $dia_ts);

    // Horario de ese día (date('N'): 1 = lunes ... 7 = domingo = id_horario)
    $dia = date('N', $dia_ts);

    $stmt = $conexion->prepare("
        SELECT hora_apertura, hora_cierre, disponible
        FROM horarios
        WHERE id_horario = ?
    ");
    $stmt->bind_param("i", $dia);
    $stmt->execute();
    $horario = $stmt->get_result()->fetch_assoc();

    if (!$horario || !$horario['disponible']) {
        return $respuesta;
    }

    $respuesta['abierto'] = true;

    $ahora = time();
    $salon = $modo !== 'web';

    $candidatas = [];

    // Botón «Ahora» (solo en el salón, hoy, para la clienta que acaba de llegar):
    // la hora actual redondeada hacia abajo de 5 en 5 minutos (13:07 -> 13:05).
    if ($modo === 'salon_confirmada' && $fecha === date('Y-m-d', $ahora)) {
        $candidatas[] = ['ts' => $ahora - ($ahora % 300), 'ahora' => true];
    }

    // Cada hora en punto desde que abre hasta antes de que cierre.
    $inicio = strtotime("$fecha " . $horario['hora_apertura']);
    $cierre = strtotime("$fecha " . $horario['hora_cierre']);

    // Si abre a las 8:30, la primera hora en punto es 9:00.
    $hora = $inicio + ((3600 - ($inicio % 3600)) % 3600);

    for (; $hora < $cierre; $hora += 3600) {

        // Si «Ahora» cae justo en una hora en punto (a las 2:00), no se repite.
        if (isset($candidatas[0]) && $candidatas[0]['ahora'] && $candidatas[0]['ts'] === $hora) {
            continue;
        }

        $candidatas[] = ['ts' => $hora, 'ahora' => false];
    }

    foreach ($candidatas as $c) {

        $ts = $c['ts'];
        $hhmm = date('H:i:s', $ts);

        // Las horas que no tienen sentido en este modo ni se muestran:
        //   página: las que ya pasaron · salón (la van a atender): las de hace más de
        //   30 minutos · salón (ya se atendió): las que todavía no llegan.
        if (
            ($modo === 'web' && $ts <= $ahora) ||
            ($modo === 'salon_confirmada' && $ts < $ahora - 30 * 60) ||
            ($modo === 'salon_completada' && $ts > $ahora)
        ) {
            continue;
        }

        // La regla de la agenda de siempre: ocupada, descanso, no alcanza...
        $motivo = revisar_cita($conexion, $fecha, $hhmm, $id_servicio, $id_manicurista, $salon);

        $respuesta['horas'][] = [
            'hora' => $hhmm,
            'texto' => ($c['ahora'] ? 'Ahora · ' : '') . date('g:i A', $ts),
            'disponible' => $motivo === "",
            'motivo' => etiqueta_motivo($motivo),
            'ahora' => $c['ahora']
        ];
    }

    return $respuesta;
}


// Palabra corta para mostrar debajo de una hora que no se puede tomar.
function etiqueta_motivo($motivo)
{
    if ($motivo === "") return "";
    if (str_contains($motivo, "pasó")) return "Ya pasó";
    if (str_contains($motivo, "descanso")) return "Descanso";
    if (str_contains($motivo, "terminaría")) return "No alcanza";
    if (str_contains($motivo, "se cruza con esa hora")) return "Ocupada";
    if (str_contains($motivo, "no atiende")) return "Cerrado";
    if (str_contains($motivo, "no hace ese servicio")) return "No lo hace";
    if (str_contains($motivo, "no puede atender")) return "No disponible";
    return "No disponible";
}


// Revisa si una cita se puede tomar.
// Devuelve "" si todo está bien, o el motivo por el que no se puede.
// $permitir_pasado: solo el administrador (cita en el salón) puede registrar
// una hora que ya pasó: una clienta que llegó hace un rato o un servicio ya hecho.
// La página pública nunca lo usa.
function revisar_cita($conexion, $fecha, $hora, $id_servicio, $id_manicurista, $permitir_pasado = false)
{
    // 1. El servicio existe y está activo (de aquí sale la duración).
    $stmt = $conexion->prepare("
        SELECT duracion
        FROM servicios
        WHERE id_servicio = ? AND activo = 1
    ");
    $stmt->bind_param("i", $id_servicio);
    $stmt->execute();
    $servicio = $stmt->get_result()->fetch_assoc();

    if (!$servicio) {
        return "Ese servicio no existe o ya no se ofrece.";
    }

    // 2. La manicurista existe y está trabajando.
    $stmt = $conexion->prepare("
        SELECT id_manicurista
        FROM manicuristas
        WHERE id_manicurista = ? AND activa = 1
    ");
    $stmt->bind_param("i", $id_manicurista);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        return "Esa manicurista no está disponible.";
    }

    // 2b. Esa manicurista hace ese servicio (tabla manicurista_servicio).
    //     La página ya muestra solo sus servicios, pero el servidor lo
    //     vuelve a revisar: el formulario se puede cambiar con F12.
    $stmt = $conexion->prepare("
        SELECT 1
        FROM manicurista_servicio
        WHERE id_manicurista = ? AND id_servicio = ?
    ");
    $stmt->bind_param("ii", $id_manicurista, $id_servicio);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        return "Esa manicurista no hace ese servicio.";
    }

    // 3. Fecha y hora válidas y no pasadas.
    $inicio = strtotime("$fecha $hora");

    if ($inicio === false) {
        return "La fecha o la hora no son válidas.";
    }

    if (!$permitir_pasado && $inicio <= time()) {
        return "Esa hora ya pasó.";
    }

    // La cita termina cuando se acaba el servicio.
    $fin = $inicio + $servicio['duracion'] * 60;

    // 4. El salón atiende ese día y el servicio alcanza a terminar.
    //    date('N'): 1 = lunes ... 7 = domingo, igual que id_horario.
    $dia = date('N', $inicio);

    $stmt = $conexion->prepare("
        SELECT hora_apertura, hora_cierre, disponible
        FROM horarios
        WHERE id_horario = ?
    ");
    $stmt->bind_param("i", $dia);
    $stmt->execute();
    $horario = $stmt->get_result()->fetch_assoc();

    if (!$horario || !$horario['disponible']) {
        return "Ese día el salón no atiende.";
    }

    $abre = strtotime("$fecha " . $horario['hora_apertura']);
    $cierra = strtotime("$fecha " . $horario['hora_cierre']);

    if ($inicio < $abre || $fin > $cierra) {
        return "Ese día se atiende de " . date('h:i A', $abre) . " a " .
            date('h:i A', $cierra) . " y este servicio terminaría a las " .
            date('h:i A', $fin) . ".";
    }

    // 5. No se cruza con el descanso.
    //    Misma regla del choque: empieza antes de que acabe el descanso
    //    Y termina después de que empieza el descanso.
    $descanso_inicio = strtotime("$fecha " . DESCANSO_INICIO);
    $descanso_fin = strtotime("$fecha " . DESCANSO_FIN);

    if ($inicio < $descanso_fin && $fin > $descanso_inicio) {
        return "Este servicio se cruza con el descanso de 12:00 PM a 1:00 PM.";
    }

    // 6. La manicurista no tiene otra cita que se cruce.
    //    Dos citas chocan si la otra empieza ANTES de que termine la nueva
    //    Y termina DESPUÉS de que empieza la nueva.
    //    Canceladas y No asistió no cuentan: ya soltaron la hora.
    //    La duración de cada cita es la que quedó guardada EN LA CITA
    //    (si el servicio cambió de duración después, la cita no cambia).
    $hora_inicio = date('H:i:s', $inicio);
    $hora_fin = date('H:i:s', $fin);

    $stmt = $conexion->prepare("
        SELECT r.id_reserva
        FROM reservas r
        WHERE r.fecha = ?
        AND r.id_manicurista = ?
        AND r.estado NOT IN ('Cancelada', 'No asistió')
        AND r.hora < ?
        AND ADDTIME(r.hora, SEC_TO_TIME(r.duracion * 60)) > ?
    ");
    $stmt->bind_param("siss", $fecha, $id_manicurista, $hora_fin, $hora_inicio);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        return "Esa manicurista ya tiene una cita que se cruza con esa hora.";
    }

    // 7. La manicurista no bloqueó ese rato (cita médica, reunión...).
    //    Misma regla del choque que las citas. El motivo no se dice:
    //    es privado de ella (super/bloqueos.php).
    $stmt = $conexion->prepare("
        SELECT id_bloqueo
        FROM bloqueos
        WHERE fecha = ?
        AND id_manicurista = ?
        AND hora_inicio < ?
        AND hora_fin > ?
    ");
    $stmt->bind_param("siss", $fecha, $id_manicurista, $hora_fin, $hora_inicio);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        return "Esa manicurista no puede atender a esa hora. Escoge otra hora u otra manicurista.";
    }

    return "";
}
