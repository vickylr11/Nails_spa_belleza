<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

exigir_admin();

$base = "../";

$titulo = "Cita en el salón - Nails Spa Belleza";

$viejo = $_SESSION['viejo'] ?? null;
unset($_SESSION['viejo']);


$servicios = $conexion->query("
    SELECT id_servicio, nombre, precio, duracion
    FROM servicios
    WHERE activo = 1
    ORDER BY nombre
")->fetch_all(MYSQLI_ASSOC);

// Con la lista de servicios que hace cada una ("1,2,4"): al escoger el servicio,
// el JavaScript deja escoger solo a las que lo hacen.
$manicuristas = $conexion->query("
    SELECT m.id_manicurista, m.nombre,
        GROUP_CONCAT(ms.id_servicio) AS servicios
    FROM manicuristas m
    LEFT JOIN manicurista_servicio ms ON ms.id_manicurista = m.id_manicurista
    WHERE m.activa = 1
    GROUP BY m.id_manicurista, m.nombre
    ORDER BY m.nombre
")->fetch_all(MYSQLI_ASSOC);

$estado_elegido = $viejo['estado'] ?? 'Confirmada';

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>ADMINISTRADOR</span>

        <h1>Cita en el salón</h1>

        <p>Para la clienta que llega sin cita, la que llama por teléfono, o un servicio que ya se hizo.</p>

    </div>

</section>


<section class="panel-contenido">

    <?php mostrar_mensajes(); ?>

    <div class="admin-grid">

        <div class="form-card admin-form">

            <h2>Datos de la cita</h2>

            <form action="guardar_cita.php" method="POST">

                <label>Celular de la clienta</label>
                <input type="tel" name="telefono" id="telefono-salon" maxlength="20" required
                       placeholder="300 123 4567" autocomplete="off"
                       value="<?= htmlspecialchars($viejo['telefono'] ?? '') ?>">

                <!-- Aquí el JavaScript muestra si la clienta ya existe y cómo va su tarjeta -->
                <div id="info-clienta" class="info-clienta" hidden></div>

                <label>Nombre de la clienta</label>
                <input type="text" name="nombre" id="nombre-salon" maxlength="100"
                       value="<?= htmlspecialchars($viejo['nombre'] ?? '') ?>">

                <!-- Solo se muestra si la clienta tiene una cita gratis para usar -->
                <label class="usar-gratis" id="caja-gratis" hidden>
                    <input type="checkbox" name="usar_gratis" value="1" checked>
                    Usar su cita GRATIS en esta cita
                </label>

                <label>Servicio</label>
                <select name="id_servicio" id="servicio" required>
                    <option value="">Escoge…</option>
                    <?php foreach ($servicios as $s): ?>
                        <option value="<?= $s['id_servicio'] ?>"
                            <?= intval($viejo['id_servicio'] ?? 0) === intval($s['id_servicio']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s['nombre']) ?> — $<?= number_format($s['precio'], 0, ',', '.') ?> (<?= $s['duracion'] ?> min)
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Manicurista</label>
                <select name="id_manicurista" id="manicurista" required>
                    <option value="">Escoge…</option>
                    <?php foreach ($manicuristas as $m): ?>
                        <option value="<?= $m['id_manicurista'] ?>"
                            data-servicios="<?= htmlspecialchars($m['servicios'] ?? '') ?>"
                            <?= intval($viejo['id_manicurista'] ?? 0) === intval($m['id_manicurista']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label>Fecha</label>
                <input type="date" name="fecha" id="fecha" required
                       value="<?= htmlspecialchars($viejo['fecha'] ?? date('Y-m-d')) ?>">

                <label>¿En qué va la cita?</label>
                <select name="estado" id="estado" required>
                    <option value="Confirmada" <?= $estado_elegido === 'Confirmada' ? 'selected' : '' ?>>
                        La van a atender (ahora o más tarde)
                    </option>
                    <option value="Completada" <?= $estado_elegido === 'Completada' ? 'selected' : '' ?>>
                        Ya la atendieron (registrar servicio hecho)
                    </option>
                </select>

                <label>Hora</label>

                <input type="hidden" name="hora" id="hora">

                <!-- Mismos botones de la página, en modo salón:
                     «La van a atender» agrega el botón «Ahora»; «Ya la atendieron» muestra horas pasadas. -->
                <div
                    class="horas-botones"
                    id="horas"
                    data-api="../acciones/horas_disponibles.php"
                    data-modo="salon"
                >
                    <p class="nota">Escoge servicio, manicurista y fecha para ver las horas.</p>
                </div>

                <p class="nota">
                    Solo salen las horas en que el salón abre ese día. En gris, las que no se pueden
                    (ocupada, descanso, no alcanza). «Ahora» es para la clienta que acaba de llegar.
                    «Ya la atendieron» muestra las horas que ya pasaron (hasta 60 días atrás).
                </p>

                <button type="submit" class="btn-principal btn-completo">
                    Guardar cita
                </button>

            </form>

        </div>


        <div class="form-card">

            <h2>¿Cuándo se usa?</h2>

            <div class="lista-ayuda">

                <p><strong>Primero el celular:</strong> si la clienta ya vino antes, aparece su nombre y cuántas
                   citas lleva. Si es nueva, escribe su nombre y queda creada.</p>

                <p><strong>Tarjeta de fidelidad:</strong> cada cita completada suma. Después de 9, la
                   número 10 es gratis. Cuentan igual las citas de la página y las del salón.</p>

                <p><strong>Llegó sin cita:</strong> deja la fecha de hoy, «La van a atender» y toca el botón «Ahora».
                   La cita queda Confirmada en la agenda y la hora queda ocupada para la página.</p>

                <p><strong>Llamó por teléfono:</strong> escoge la fecha y toca la hora que pidió.</p>

                <p><strong>Se atendió y no quedó registrada:</strong> escoge «Ya la atendieron». Queda
                   Completada y le cuenta a la manicurista para su pago.</p>

                <p>El precio y la duración se copian del servicio en este momento, igual que en la página.</p>

            </div>

        </div>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
