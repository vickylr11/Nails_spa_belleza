<?php

require_once "../conexion/conexion.php";
require_once "../includes/panel.php";   // iniciales() para las manicuristas sin foto

$base = "../";

$titulo = "Reservar cita - Nails Spa Belleza";


// ==========================================
// LA RESERVA SE HACE EN PASOS
//   1. ¿Qué día?          (botones con los próximos 14 días)
//   2. ¿Con quién?        (fotos de las manicuristas)
//   3. ¿Qué servicio?     (solo los que hace ESA manicurista)
//   4. ¿A qué hora?       (botones con las horas libres)
//   5. Tus datos          (nombre y celular)
// El servicio va ANTES de la hora porque la duración decide qué horas
// alcanzan (un spa de 90 min no cabe a las 11:00, por el descanso de las 12).
//
// Cada paso solo guarda un número en un campo oculto (fecha, id_manicurista,
// id_servicio, hora): eso es lo que se envía. El JavaScript pinta los botones;
// el servidor vuelve a revisar todo al guardar (includes/agenda.php).
// ==========================================


// Si la reserva falló (ej. otra clienta tomó la hora), se vuelve con lo que ya
// se había escogido, para no empezar de cero.
$viejo = $_SESSION['reserva_vieja'] ?? [];
unset($_SESSION['reserva_vieja']);

// ?servicio=3 llega desde el botón «Reservar» de un servicio en la portada.
$servicio_inicial = intval($viejo['id_servicio'] ?? ($_GET['servicio'] ?? 0));


// ---------- Servicios activos ----------

$servicios = $conexion->query("
    SELECT id_servicio, nombre, descripcion, precio, duracion, imagen
    FROM servicios
    WHERE activo = 1
    ORDER BY nombre
")->fetch_all(MYSQLI_ASSOC);


// ---------- Manicuristas activas, con la lista de servicios que hace cada una ----------
// GROUP_CONCAT junta los id en un texto: "1,2,4". El JavaScript lo usa para
// mostrar solo sus servicios en el paso 3.

$manicuristas = $conexion->query("
    SELECT m.id_manicurista, m.nombre, m.foto,
        GROUP_CONCAT(s.id_servicio ORDER BY s.id_servicio) AS servicios
    FROM manicuristas m
    LEFT JOIN manicurista_servicio ms ON ms.id_manicurista = m.id_manicurista
    LEFT JOIN servicios s ON s.id_servicio = ms.id_servicio AND s.activo = 1
    WHERE m.activa = 1
    GROUP BY m.id_manicurista, m.nombre, m.foto
    HAVING servicios IS NOT NULL      -- la que no tiene servicios asignados no sale
    ORDER BY m.nombre
")->fetch_all(MYSQLI_ASSOC);


// ---------- Los próximos 14 días, con el horario de cada uno ----------

$horarios = [];

foreach ($conexion->query("SELECT id_horario, disponible FROM horarios") as $h) {
    $horarios[$h['id_horario']] = $h['disponible'];
}

$nombres_dia = [1 => 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
$nombres_mes = [1 => 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

$dias = [];

for ($i = 0; $i < 14; $i++) {

    $ts = strtotime("+$i day", strtotime(date('Y-m-d')));

    $dias[] = [
        'fecha' => date('Y-m-d', $ts),
        'nombre' => $i === 0 ? 'Hoy' : ($i === 1 ? 'Mañana' : $nombres_dia[date('N', $ts)]),
        'numero' => date('j', $ts),
        'mes' => $nombres_mes[date('n', $ts)],
        // date('N'): 1 = lunes ... 7 = domingo, igual que id_horario
        'abierto' => !empty($horarios[date('N', $ts)])
    ];
}

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>NAILS SPA BELLEZA</span>

        <h1>Reserva tu cita</h1>

        <p>Escoge el día, tu manicurista, el servicio y la hora.</p>

    </div>

</section>


<section class="reserva-layout">

    <div class="form-card">

        <?php if (isset($_GET['error'])): ?>
            <div class="alerta error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['ok'])): ?>
            <div class="alerta exito"><?= htmlspecialchars($_GET['ok']) ?></div>
        <?php endif; ?>


        <form
            action="../acciones/guardar.reserva.php"
            method="POST"
            id="formReserva"
            class="form-pasos"
        >

            <!-- Lo que de verdad se envía: lo llenan los botones de cada paso -->
            <input type="hidden" name="fecha" id="fecha"
                   value="<?= htmlspecialchars($viejo['fecha'] ?? '') ?>">
            <input type="hidden" name="id_manicurista" id="manicurista"
                   value="<?= intval($viejo['id_manicurista'] ?? 0) ?: '' ?>">
            <input type="hidden" name="id_servicio" id="servicio"
                   value="<?= $servicio_inicial ?: '' ?>">
            <input type="hidden" name="hora" id="hora">


            <!-- ================= PASO 1: DÍA ================= -->

            <div class="paso-reserva" id="paso-dia">

                <h2><span class="numero-paso">1</span> ¿Qué día?</h2>

                <div class="dias-botones">

                    <?php foreach ($dias as $d): ?>

                        <button
                            type="button"
                            class="dia-btn"
                            data-fecha="<?= $d['fecha'] ?>"
                            <?= $d['abierto'] ? '' : 'disabled' ?>
                        >
                            <small><?= $d['nombre'] ?></small>
                            <strong><?= $d['numero'] ?></strong>
                            <small><?= $d['abierto'] ? $d['mes'] : 'Cerrado' ?></small>
                        </button>

                    <?php endforeach; ?>

                </div>

                <label class="otra-fecha">
                    ¿Más adelante?
                    <input type="date" id="fecha-otra" min="<?= date('Y-m-d') ?>">
                </label>

            </div>


            <!-- ================= PASO 2: MANICURISTA ================= -->

            <div class="paso-reserva bloqueado" id="paso-manicurista">

                <h2><span class="numero-paso">2</span> ¿Con quién?</h2>

                <p class="nota paso-espera solo-bloqueado">Primero escoge el día.</p>

                <div class="manicuristas-opciones">

                    <?php foreach ($manicuristas as $m): ?>

                        <button
                            type="button"
                            class="manicurista-btn"
                            data-id="<?= $m['id_manicurista'] ?>"
                            data-nombre="<?= htmlspecialchars($m['nombre']) ?>"
                            data-servicios="<?= htmlspecialchars($m['servicios'] ?? '') ?>"
                        >
                            <?php if ($m['foto']): ?>
                                <img src="../<?= htmlspecialchars($m['foto']) ?>" alt="">
                            <?php else: ?>
                                <!-- Sin foto: sus iniciales en un círculo -->
                                <span class="avatar-iniciales"><?= htmlspecialchars(iniciales($m['nombre'])) ?></span>
                            <?php endif; ?>

                            <strong><?= htmlspecialchars($m['nombre']) ?></strong>

                            <!-- El JavaScript escribe aquí «No hace este servicio» si hace falta -->
                            <small class="manicurista-aviso"></small>
                        </button>

                    <?php endforeach; ?>

                </div>

            </div>


            <!-- ================= PASO 3: SERVICIO ================= -->

            <div class="paso-reserva bloqueado" id="paso-servicio">

                <h2><span class="numero-paso">3</span> ¿Qué servicio?</h2>

                <p class="nota paso-espera">Escoge tu manicurista para ver los servicios que hace.</p>

                <div class="servicios-opciones">

                    <?php foreach ($servicios as $s): ?>

                        <button
                            type="button"
                            class="servicio-btn"
                            data-id="<?= $s['id_servicio'] ?>"
                            data-nombre="<?= htmlspecialchars($s['nombre']) ?>"
                            data-precio="$<?= number_format($s['precio'], 0, ',', '.') ?>"
                            data-duracion="<?= $s['duracion'] ?>"
                        >
                            <img src="../<?= htmlspecialchars($s['imagen'] ?: 'assets/img/logo.png') ?>" alt="">

                            <span class="servicio-btn-texto">
                                <strong><?= htmlspecialchars($s['nombre']) ?></strong>
                                <small>$<?= number_format($s['precio'], 0, ',', '.') ?> · <?= $s['duracion'] ?> min</small>
                            </span>
                        </button>

                    <?php endforeach; ?>

                </div>

            </div>


            <!-- ================= PASO 4: HORA ================= -->
            <!-- El JavaScript pide las horas libres (acciones/horas_disponibles.php)
                 y las muestra como botones. -->

            <div class="paso-reserva bloqueado" id="paso-hora">

                <h2><span class="numero-paso">4</span> ¿A qué hora?</h2>

                <div
                    class="horas-botones"
                    id="horas"
                    data-api="../acciones/horas_disponibles.php"
                    data-modo="web"
                >
                    <p class="nota">Escoge el día, la manicurista y el servicio para ver las horas libres.</p>
                </div>

            </div>


            <!-- ================= PASO 5: DATOS ================= -->

            <div class="paso-reserva" id="paso-datos">

                <h2><span class="numero-paso">5</span> Tus datos</h2>

                <div class="datos-grid">

                    <div class="campo-reserva">
                        <label for="nombre">Nombre completo</label>
                        <input type="text" name="nombre" id="nombre" maxlength="100"
                               placeholder="Ej: María Rodríguez" required
                               value="<?= htmlspecialchars($viejo['nombre'] ?? '') ?>">
                    </div>

                    <div class="campo-reserva">
                        <label for="telefono">Celular</label>
                        <input type="tel" name="telefono" id="telefono" maxlength="20"
                               placeholder="Ej: 300 123 4567" inputmode="tel" required
                               value="<?= htmlspecialchars($viejo['telefono'] ?? '') ?>">
                    </div>

                    <div class="campo-reserva">
                        <label for="correo">Correo (opcional)</label>
                        <input type="email" name="correo" id="correo" maxlength="100"
                               placeholder="correo@ejemplo.com"
                               value="<?= htmlspecialchars($viejo['correo'] ?? '') ?>">
                    </div>

                </div>

                <p class="nota">
                    Con tu celular te reconocemos y te contamos las citas:
                    la número 10 es gratis. Te confirmamos por WhatsApp.
                </p>

            </div>


            <!-- Resumen de lo escogido (lo llena el JavaScript) -->
            <div class="resumen-cita" id="resumen" hidden></div>

            <button type="submit" class="btn-principal btn-completo">
                Confirmar reserva
            </button>

        </form>

    </div>


    <div class="reserva-info">

        <div class="reserva-decoracion">💅</div>

        <h2>Un momento para ti</h2>

        <p>Regálate un espacio de cuidado y relajación.</p>

        <div class="info-lista">

            <div><span>✓</span> Atención personalizada</div>

            <div><span>✓</span> Profesionales especializados</div>

            <div><span>✓</span> Productos de calidad</div>

            <div><span>✓</span> Ambiente relajante</div>

            <div class="promo-fidelidad">
                <span>🎁</span>
                <strong>Tu cita número 10 es GRATIS.</strong>
                Te reconocemos por tu celular: reserva siempre con el mismo.
            </div>

        </div>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
