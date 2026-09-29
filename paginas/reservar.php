<?php

require_once "../conexion/conexion.php";

$base = "../";

$titulo = "Reservar cita - Nails Spa Belleza";

$servicio_seleccionado = isset($_GET['servicio'])
    ? intval($_GET['servicio'])
    : 0;

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

        <?php if (isset($_GET['ok'])): ?>

            <div class="alerta exito">
                <?= htmlspecialchars($_GET['ok']) ?>
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
                    placeholder="Ej: 300 123 4567"
                    inputmode="tel"
                    required>
            </div>


            <div class="campo-reserva">
                <label>
                    Correo electrónico
                </label>

                <input
                    type="email"
                    name="correo"
                    placeholder="correo@ejemplo.com">
            </div>


            <div class="campo-reserva">
                <label>
                    Servicio
                </label>

                <select
                    name="id_servicio"
                    id="servicio"
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

                <label>
                    Manicurista
                </label>

                <select
                    name="id_manicurista"
                    id="manicurista"
                    required>

                    <option value="">
                        Selecciona una manicurista
                    </option>

                    <?php

                    $manicuristas = $conexion->query("
                        SELECT id_manicurista, nombre
                        FROM manicuristas
                        WHERE activa = 1
                        ORDER BY nombre
                    ");

                    while ($m = $manicuristas->fetch_assoc()):

                    ?>

                        <option value="<?= $m['id_manicurista'] ?>">
                            <?= htmlspecialchars($m['nombre']) ?>
                        </option>

                    <?php endwhile; ?>

                </select>

            </div>


            <div class="campo-reserva">
                <label>
                    Fecha
                </label>

                <input
                    type="date"
                    name="fecha"
                    id="fecha"
                    min="<?= date('Y-m-d') ?>"
                    required>
            </div>


            <!-- HORAS COMO BOTONES
                 El JavaScript pide las horas libres de ese día (acciones/horas_disponibles.php)
                 y las muestra como botones. Al tocar una, se guarda en el campo oculto «hora». -->
            <div class="campo-reserva campo-horas">

                <label>Hora</label>

                <input type="hidden" name="hora" id="hora">

                <div
                    class="horas-botones"
                    id="horas"
                    data-api="../acciones/horas_disponibles.php"
                    data-modo="web"
                >
                    <p class="nota">Escoge el servicio, la manicurista y la fecha para ver las horas libres.</p>
                </div>

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

            <div class="promo-fidelidad">
                <span>🎁</span>
                <strong>Tu cita número 10 es GRATIS.</strong>
                Te reconocemos por tu celular: reserva siempre con el mismo.
            </div>

        </div>

    </div>

</section>

<?php

require_once "../includes/footer.php";

?>