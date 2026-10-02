
<?php

session_start();

require_once "../conexion/conexion.php";

$titulo = "Reservar cita - Nails Spa Belleza";

$servicio_seleccionado = isset($_GET['servicio'])
    ? intval($_GET['servicio'])
    : 0;


/* ==========================================
   MANICURISTA SELECCIONADA
========================================== */

$id_manicurista = $_SESSION['id_manicurista'] ?? 0;

$manicurista_seleccionada = null;

if ($id_manicurista > 0) {

    $stmt = $conexion->prepare("
        SELECT id_manicurista, nombre
        FROM manicuristas
        WHERE id_manicurista = ?
        AND estado = 1
    ");

    $stmt->bind_param("i", $id_manicurista);
    $stmt->execute();

    $manicurista_seleccionada = $stmt
        ->get_result()
        ->fetch_assoc();

    $stmt->close();
}


/* ==========================================
   CONSULTAR RESERVAS OCUPADAS
   SOLO DE LA MANICURISTA SELECCIONADA
========================================== */

$reservas_ocupadas = [];

if ($manicurista_seleccionada) {

    $nombreManicurista = $manicurista_seleccionada['nombre'];

    $sqlOcupadas = "
        SELECT
            DATE_FORMAT(fecha, '%Y-%m-%d') AS fecha_reserva,
            TIME_FORMAT(hora, '%H:%i') AS hora_reserva
        FROM reservas
        WHERE TRIM(manicurista) = TRIM(?)
          AND fecha >= CURDATE()
          AND (
              estado IS NULL
              OR LOWER(TRIM(estado)) NOT IN ('cancelada', 'cancelado')
          )
    ";

    $stmtOcupadas = $conexion->prepare($sqlOcupadas);

    $stmtOcupadas->bind_param("s", $nombreManicurista);

    $stmtOcupadas->execute();

    $resultadoOcupadas = $stmtOcupadas->get_result();

    while ($fila = $resultadoOcupadas->fetch_assoc()) {

        $fechaReserva = $fila['fecha_reserva'];
        $horaReserva = $fila['hora_reserva'];

        $reservas_ocupadas[$fechaReserva][] = $horaReserva;
    }

    $stmtOcupadas->close();
}


/* ==========================================
   HORARIOS DE ATENCION
========================================== */

$horarios_resultado = $conexion->query("
    SELECT
        dia,
        hora_apertura,
        hora_cierre,
        disponible
    FROM horarios
");

$horarios_semana = [];

while ($fila = $horarios_resultado->fetch_assoc()) {

    $horarios_semana[$fila['dia']] = [

        'apertura' => $fila['hora_apertura'],

        'cierre' => $fila['hora_cierre'],

        'disponible' => (int)$fila['disponible']

    ];

}


/* ==========================================
   CARGAR ENCABEZADO
========================================== */

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>
            NAILS SPA BELLEZA
        </span>

        <h1>
            Reserva tu cita
        </h1>

        <p>
            Completa los datos para agendar tu cita.
        </p>

    </div>

</section>


<section class="reserva-layout">

    <div class="form-card">

        <h2>
            Datos de la cita
        </h2>

        <?php if (isset($_GET['error'])): ?>

            <div class="alerta error">
                <?= htmlspecialchars($_GET['error']) ?>
            </div>

        <?php endif; ?>


        <form
            action="../acciones/guardar.reserva.php"
            method="POST"
            id="formReserva">

            <div class="campo-reserva">
                <label>
                    Nombre completo
                </label>

                <input
                    type="text"
                    name="nombre"
                    placeholder="Ej: María Rodríguez"
                    required>
            </div>


            <div class="campo-reserva">
                <label>
                    Teléfono
                </label>

                <input
                    type="tel"
                    name="telefono"
                    placeholder="Ej: 3001234567"
                    required>
            </div>


           


            <div class="campo-reserva">
                <label>
                    Servicio
                </label>

                <select
                    name="id_servicio"
                    required>

                    <option value="">
                        Selecciona un servicio
                    </option>



                    <?php

                    $servicios = $conexion->query("
                SELECT *
                FROM servicios
                WHERE activo = 1
                ORDER BY nombre
            ");

                    while ($servicio = $servicios->fetch_assoc()):

                    ?>

                        <option
                            value="<?= $servicio['id_servicio'] ?>"
                            <?= $servicio_seleccionado == $servicio['id_servicio']
                                ? 'selected'
                                : '' ?>>

                            <?= htmlspecialchars($servicio['nombre']) ?>

                            -
                            $<?= number_format(
                                    $servicio['precio'],
                                    0,
                                    ',',
                                    '.'
                                ) ?>

                        </option>

                    <?php endwhile; ?>

                </select>
            </div>

            <div class="campo-reserva">


                <div class="campo-reserva">
                    <label>Manicurista</label>

                    <?php if ($manicurista_seleccionada): ?>

                        <input
                            type="text"
                            value="<?= htmlspecialchars($manicurista_seleccionada['nombre']) ?>"
                            readonly>

                        <input
                            type="hidden"
                            name="manicurista"
                            value="<?= htmlspecialchars($manicurista_seleccionada['nombre']) ?>">

                        <input
                            type="hidden"
                            name="id_manicurista"
                            value="<?= (int)$manicurista_seleccionada['id_manicurista'] ?>">

                    <?php else: ?>

                        <p>Primero selecciona una manicurista.</p>

                        <a href="manicurista.php">Ver manicuristas</a>

                    <?php endif; ?>
                </div>

            </div>


            <div class="campo-reserva">
                <label>Fecha de la cita</label>

                <input
                    type="date"
                    name="fecha"
                    id="fecha"
                    min="<?= date('Y-m-d') ?>"
                    required>
            </div>


            <div class="campo-reserva">
                <label>Hora disponible</label>

                <select name="hora" id="hora" required disabled>
                    <option value="">Primero selecciona una fecha</option>
                </select>

                <small id="mensaje-horario">
                    Selecciona un día para consultar los horarios disponibles.
                </small>
            </div>

            <div class="campo-boton">

                <button
                    type="submit"
                    class="btn-principal btn-completo">
                    Confirmar reserva
                </button>

            </div>

        </form>

    </div>


    <div class="reserva-info">

        <div class="reserva-decoracion">
            💅
        </div>

        <h2>
            Un momento para ti
        </h2>

        <p>
            Regálate un espacio de cuidado y
            relajación.
        </p>

        <div class="info-lista">

            <div>
                <span>✓</span>
                Atención personalizada
            </div>

            <div>
                <span>✓</span>
                Profesionales especializados
            </div>

            <div>
                <span>✓</span>
                Productos de calidad
            </div>

            <div>
                <span>✓</span>
                Ambiente relajante
            </div>

        </div>

    </div>

</section>


<script>

const horariosSemana = <?= json_encode(
    $horarios_semana,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

// Reservas existentes de la manicurista seleccionada
const reservasOcupadas = <?= json_encode(
    $reservas_ocupadas,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
) ?>;

const fechaInput = document.getElementById("fecha");
const horaSelect = document.getElementById("hora");
const mensajeHorario = document.getElementById("mensaje-horario");

const nombresDias = [
    "Domingo",
    "Lunes",
    "Martes",
    "Miércoles",
    "Jueves",
    "Viernes",
    "Sábado"
];

function actualizarHoras() {

    const fechaSeleccionada = fechaInput.value;

    horaSelect.innerHTML = "";
    horaSelect.disabled = true;

    if (!fechaSeleccionada) {

        horaSelect.innerHTML =
            '<option value="">Primero selecciona una fecha</option>';

        mensajeHorario.textContent =
            "Selecciona un día para consultar los horarios disponibles.";

        return;
    }

    const [anio, mes, dia] =
        fechaSeleccionada.split("-").map(Number);

    const fecha = new Date(anio, mes - 1, dia);

    const nombreDia = nombresDias[fecha.getDay()];

    const horario = horariosSemana[nombreDia];

    if (!horario || horario.disponible !== 1) {

        horaSelect.innerHTML =
            '<option value="">No hay atención este día</option>';

        mensajeHorario.textContent =
            "Selecciona otro día.";

        return;
    }

    const ahora = new Date();

    const esHoy =
        anio === ahora.getFullYear() &&
        mes === ahora.getMonth() + 1 &&
        dia === ahora.getDate();

    const [horaApertura, minutoApertura] =
        horario.apertura.split(":").map(Number);

    const [horaCierre, minutoCierre] =
        horario.cierre.split(":").map(Number);

    let inicio = horaApertura * 60 + minutoApertura;

    const cierre = horaCierre * 60 + minutoCierre;

    if (esHoy) {

        const minutosActuales =
            ahora.getHours() * 60 + ahora.getMinutes();

        if (minutosActuales > inicio) {

            inicio = Math.ceil(minutosActuales / 60) * 60;

        }
    }

    // Horarios ocupados en la fecha seleccionada
    const ocupadas = reservasOcupadas[fechaSeleccionada] || [];

    let disponibles = 0;
    let ocupadasEncontradas = 0;

    for (
        let minutos = inicio;
        minutos + 60 <= cierre;
        minutos += 60
    ) {

        const hora = Math.floor(minutos / 60);
        const minuto = minutos % 60;

        // Descanso de 12:00 PM a 1:00 PM
        if (minutos >= 720 && minutos < 780) {
            continue;
        }

        const valor =
            String(hora).padStart(2, "0") + ":" +
            String(minuto).padStart(2, "0") + ":00";

        const hora12 = hora % 12 || 12;

        const periodo = hora < 12 ? "AM" : "PM";

        const texto =
            String(hora12).padStart(2, "0") + ":" +
            String(minuto).padStart(2, "0") + " " +
            periodo;

        // Comprobar si esta hora ya tiene reserva
        const estaOcupada = ocupadas.some(function(horaOcupada) {

            return horaOcupada.substring(0, 5) === valor.substring(0, 5);

        });

        const opcion = document.createElement("option");

        opcion.value = valor;

        if (estaOcupada) {

            opcion.textContent = texto + " — OCUPADO";

            opcion.disabled = true;

            opcion.style.color = "#999";

            ocupadasEncontradas++;

        } else {

            opcion.textContent = texto + " — Disponible";

            disponibles++;

        }

        horaSelect.appendChild(opcion);
    }

    if (disponibles === 0) {

        horaSelect.insertAdjacentHTML(
            "afterbegin",
            '<option value="">No hay horarios disponibles</option>'
        );

        mensajeHorario.textContent =
            "Todos los horarios de esta fecha están ocupados. Selecciona otro día.";

    } else {

        horaSelect.insertAdjacentHTML(
            "afterbegin",
            '<option value="">Selecciona una hora</option>'
        );

        horaSelect.disabled = false;

        mensajeHorario.textContent =
            disponibles + " horarios disponibles. " +
            ocupadasEncontradas + " horarios ocupados.";

    }

}

fechaInput.addEventListener("change", actualizarHoras);

// Ejecutar al cargar la página
actualizarHoras();

</script>
<?php

require_once "../includes/footer.php";

?>