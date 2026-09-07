<?php

require_once "../conexion/conexion.php";

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


        <form
            action="../acciones/guardar.reserva.php"
            method="POST"
            id="formReserva"
        >

            <label>
                Nombre completo
            </label>

            <input
                type="text"
                name="nombre"
                placeholder="Ej: María Rodríguez"
                required
            >


            <label>
                Teléfono
            </label>

            <input
                type="tel"
                name="telefono"
                placeholder="Ej: 3001234567"
                required
            >


            <label>
                Correo electrónico
            </label>

            <input
                type="email"
                name="correo"
                placeholder="correo@ejemplo.com"
            >


            <label>
                Servicio
            </label>

            <select
                name="id_servicio"
                required
            >

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
                            : '' ?>
                    >

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


            <label>
                Fecha
            </label>

            <input
                type="date"
                name="fecha"
                id="fecha"
                min="<?= date('Y-m-d') ?>"
                required
            >


            <label>
                Hora
            </label>

            <select
                name="hora"
                required
            >

                <option value="">
                    Selecciona una hora
                </option>

                <option value="09:00:00">
                    09:00 AM
                </option>

                <option value="10:00:00">
                    10:00 AM
                </option>

                <option value="11:00:00">
                    11:00 AM
                </option>

                <option value="12:00:00">
                    12:00 PM
                </option>

                <option value="14:00:00">
                    02:00 PM
                </option>

                <option value="15:00:00">
                    03:00 PM
                </option>

                <option value="16:00:00">
                    04:00 PM
                </option>

                <option value="17:00:00">
                    05:00 PM
                </option>

            </select>


            <button
                type="submit"
                class="btn-principal btn-completo"
            >
                Confirmar reserva
            </button>

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

<?php

require_once "../includes/footer.php";

?>