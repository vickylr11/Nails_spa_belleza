<?php

require_once "../conexion/conexion.php";

$titulo = "Agenda - Nails Spa Belleza";

$fecha = $_GET['fecha'] ?? date('Y-m-d');

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>
            PANEL ADMINISTRATIVO
        </span>

        <h1>
            Agenda
        </h1>

        <p>
            Consulta y administra las citas.
        </p>

    </div>

    <a
        href="reservar.php"
        class="btn-principal"
    >
        + Nueva cita
    </a>

</section>


<section class="panel-contenido">

    <?php if (isset($_GET['mensaje'])): ?>

        <div class="alerta exito">

            <?= htmlspecialchars($_GET['mensaje']) ?>

        </div>

    <?php endif; ?>


    <div class="filtros">

        <form method="GET">

            <label>
                Seleccionar fecha
            </label>

            <input
                type="date"
                name="fecha"
                value="<?= htmlspecialchars($fecha) ?>"
            >

            <button
                type="submit"
                class="btn-principal"
            >
                Buscar
            </button>

        </form>

    </div>


    <div class="agenda-titulo">

        <div>

            <span>
                AGENDA DEL DÍA
            </span>

            <h2>
                <?= date(
                    'd/m/Y',
                    strtotime($fecha)
                ) ?>
            </h2>

        </div>

        <?php

        $stmt = $conexion->prepare("
            SELECT COUNT(*) AS total
            FROM reservas
            WHERE fecha = ?
            AND estado != 'Cancelada'
        ");

        $stmt->bind_param(
            "s",
            $fecha
        );

        $stmt->execute();

        $contador = $stmt
            ->get_result()
            ->fetch_assoc();

        ?>

        <div class="contador-citas">

            <?= $contador['total'] ?>

            <span>
                citas
            </span>

        </div>

    </div>


    <div class="tabla-contenedor">

        <table>

            <thead>

                <tr>

                    <th>
                        Hora
                    </th>

                    <th>
                        Cliente
                    </th>

                    <th>
                        Teléfono
                    </th>

                    <th>
                        Servicio
                    </th>

                    <th>
                        Precio
                    </th>

                    <th>
                        Estado
                    </th>

                    <th>
                        Acción
                    </th>

                </tr>

            </thead>

            <tbody>

            <?php

            $stmt = $conexion->prepare("

                SELECT
                    r.*,
                    c.nombre AS cliente,
                    c.telefono,
                    s.nombre AS servicio,
                    s.precio

                FROM reservas r

                INNER JOIN clientes c
                    ON r.id_cliente = c.id_cliente

                INNER JOIN servicios s
                    ON r.id_servicio = s.id_servicio

                WHERE r.fecha = ?

                ORDER BY r.hora ASC

            ");

            $stmt->bind_param(
                "s",
                $fecha
            );

            $stmt->execute();

            $reservas = $stmt->get_result();

            if ($reservas->num_rows === 0):

            ?>

                <tr>

                    <td
                        colspan="7"
                        class="sin-datos"
                    >

                        No hay citas para este día.

                    </td>

                </tr>

            <?php

            endif;

            while ($reserva = $reservas->fetch_assoc()):

            ?>

                <tr>

                    <td>
                        <strong>
                            <?= date(
                                'h:i A',
                                strtotime($reserva['hora'])
                            ) ?>
                        </strong>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $reserva['cliente']
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $reserva['telefono']
                        ) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars(
                            $reserva['servicio']
                        ) ?>
                    </td>

                    <td>
                        $<?= number_format(
                            $reserva['precio'],
                            0,
                            ',',
                            '.'
                        ) ?>
                    </td>

                    <td>

                        <span class="estado estado-<?= strtolower(
                            $reserva['estado']
                        ) ?>">

                            <?= htmlspecialchars(
                                $reserva['estado']
                            ) ?>

                        </span>

                    </td>

                    <td>

                        <?php if ($reserva['estado'] !== 'Cancelada'): ?>

                            <a
                                href="../acciones/cancelar_reserva.php?id=<?= $reserva['id_reserva'] ?>"
                                class="btn-cancelar"
                                onclick="return confirmarCancelacion()"
                            >
                                Cancelar
                            </a>

                        <?php else: ?>

                            <span>
                                —
                            </span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endwhile; ?>

            </tbody>

        </table>

    </div>

</section>

<?php

require_once "../includes/footer.php";

?>