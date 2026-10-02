<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";

exigir_admin();

$base = "../";

$titulo = "Horarios - Nails Spa Belleza";

require_once "../includes/header.php";

?>

<section class="pagina-header">


    <div>

        <span>
            PANEL ADMINISTRATIVO
        </span>

        <h1>
            Horarios y bloqueos
        </h1>

        <p>
            Configura los horarios de atención.
        </p>

    </div>

</section>


<section class="panel-contenido">

    <?php if (isset($_GET['mensaje'])): ?>

        <div class="alerta exito">

            <?= htmlspecialchars(
                $_GET['mensaje']
            ) ?>

        </div>

    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>

        <div class="alerta error">
            <?= htmlspecialchars($_GET['error']) ?>
        </div>

    <?php endif; ?>


    <form
        action="guardar_horarios.php"
        method="POST"
    >

        <div class="tabla-contenedor">

            <table>

                <thead>

                    <tr>

                        <th>
                            Día
                        </th>

                        <th>
                            Hora de apertura
                        </th>

                        <th>
                            Hora de cierre
                        </th>

                        <th>
                            Disponible
                        </th>

                    </tr>

                </thead>

                <tbody>

                <?php

                $resultado = $conexion->query("
                    SELECT *
                    FROM horarios
                    ORDER BY id_horario
                ");

                while ($horario = $resultado->fetch_assoc()):

                ?>

                    <tr>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $horario['dia']
                                ) ?>
                            </strong>

                            <input
                                type="hidden"
                                name="id[]"
                                value="<?= $horario['id_horario'] ?>"
                            >

                        </td>

                        <td>

                            <input
                                type="time"
                                name="apertura[]"
                                value="<?= $horario['hora_apertura'] ?>"
                            >

                        </td>

                        <td>

                            <input
                                type="time"
                                name="cierre[]"
                                value="<?= $horario['hora_cierre'] ?>"
                            >

                        </td>

                        <td>

                            <label class="switch">

                                <input
                                    type="checkbox"
                                    name="disponible[<?= $horario['id_horario'] ?>]"
                                    value="1"
                                    <?= $horario['disponible']
                                        ? 'checked'
                                        : '' ?>
                                >

                                <span></span>

                            </label>

                        </td>

                    </tr>

                <?php endwhile; ?>

                </tbody>

            </table>

        </div>


        <button
            type="submit"
            class="btn-principal"
        >
            Guardar horarios
        </button>

    </form>

</section>

<?php

require_once "../includes/footer.php";

?>