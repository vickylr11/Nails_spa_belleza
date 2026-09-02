<?php
  

require_once "conexion/conexion.php";

$titulo = "Inicio - Nails Spa Belleza";

$consulta = $conexion->query("
    SELECT COUNT(*) AS total
    FROM reservas
    WHERE fecha = CURDATE()
    AND estado != 'Cancelada'
");

$datos = $consulta->fetch_assoc();

$citas_hoy = $datos['total'];

$consulta_clientes = $conexion->query("
    SELECT COUNT(*) AS total
    FROM clientes
");

$clientes = $consulta_clientes->fetch_assoc();

$total_clientes = $clientes['total'];

$consulta_servicios = $conexion->query("
    SELECT COUNT(*) AS total
    FROM servicios
    WHERE activo = 1
");

$servicios = $consulta_servicios->fetch_assoc();

$total_servicios = $servicios['total'];

require_once "includes/header.php";

?>

<section class="hero">

    <div class="hero-text">

        <span class="etiqueta">
            ✨ Bienvenida a tu espacio de belleza
        </span>

        <h1>
            Nails Spa<br>
            <span>Belleza</span>
        </h1>

        <p>
            Tus uñas, tu estilo.
        </p>

        <p class="descripcion">

            Reserva tu cita de manera rápida y sencilla.
            Elige el servicio, la fecha y el horario que
            más te convenga.

        </p>

        <div class="hero-botones">

            <a
                href="paginas/reservar.php"
                class="btn-principal"
            >
                Reservar mi cita
            </a>

            <a
                href="#servicios"
                class="btn-secundario"
            >
                Ver servicios
            </a>

        </div>

    </div>

    <div class="hero-imagen">

        <div class="circulo">

            💅

        </div>

        <div class="tarjeta-flotante">

            <strong>✨ Tu belleza</strong>

            <span>
                comienza aquí
            </span>

        </div>

    </div>

</section>


<!-- ESTADÍSTICAS -->

<section class="estadisticas">

    <div class="estadistica">

        <span class="estadistica-icono">
            📅
        </span>

        <div>

            <strong>
                <?= $citas_hoy ?>
            </strong>

            <span>
                Citas hoy
            </span>

        </div>

    </div>


    <div class="estadistica">

        <span class="estadistica-icono">
            👩
        </span>

        <div>

            <strong>
                <?= $total_clientes ?>
            </strong>

            <span>
                Clientes
            </span>

        </div>

    </div>


    <div class="estadistica">

        <span class="estadistica-icono">
            💅
        </span>

        <div>

            <strong>
                <?= $total_servicios ?>
            </strong>

            <span>
                Servicios
            </span>

        </div>

    </div>

</section>


<!-- SERVICIOS -->

<section
    id="servicios"
    class="seccion"
>

    <div class="titulo-seccion">

        <span>
            NUESTROS SERVICIOS
        </span>

        <h2>
            Mímate, te lo mereces
        </h2>

        <p>
            Elige el tratamiento perfecto para ti.
        </p>

    </div>


    <div class="servicios">

        <?php

        $resultado = $conexion->query("
            SELECT *
            FROM servicios
            WHERE activo = 1
            ORDER BY id_servicio ASC
        ");

        while ($servicio = $resultado->fetch_assoc()):

        ?>

        <article class="card-servicio">

            <div class="servicio-icono">
                💅
            </div>

            <h3>
                <?= htmlspecialchars($servicio['nombre']) ?>
            </h3>

            <p>
                <?= htmlspecialchars($servicio['descripcion']) ?>
            </p>

            <div class="servicio-info">

                <strong>
                    $<?= number_format(
                        $servicio['precio'],
                        0,
                        ',',
                        '.'
                    ) ?>
                </strong>

                <span>
                    <?= $servicio['duracion'] ?> min
                </span>

            </div>

            <a
                href="paginas/reservar.php?servicio=<?= $servicio['id_servicio'] ?>"
                class="btn-card"
            >
                Reservar
            </a>

        </article>

        <?php endwhile; ?>

    </div>

</section>


<!-- PASOS -->

<section class="seccion pasos-seccion">

    <div class="titulo-seccion">

        <span>
            ¿CÓMO FUNCIONA?
        </span>

        <h2>
            Reserva en 3 simples pasos
        </h2>

    </div>


    <div class="pasos">

        <div class="paso">

            <span>1</span>

            <h3>
                Escoge tu servicio
            </h3>

            <p>
                Selecciona el tratamiento
                que quieres realizarte.
            </p>

        </div>


        <div class="paso">

            <span>2</span>

            <h3>
                Elige fecha y hora
            </h3>

            <p>
                Consulta los horarios
                disponibles.
            </p>

        </div>


        <div class="paso">

            <span>3</span>

            <h3>
                Confirma tu cita
            </h3>

            <p>
                Ingresa tus datos y
                confirma la reserva.
            </p>

        </div>

    </div>
       <link rel="stylesheet" href="css/estilo.css">

</section>

<?php
 
require_once "includes/footer.php";

?>