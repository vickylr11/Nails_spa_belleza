<?php

require_once "../conexion/conexion.php";

$titulo = "Clientes - Nails Spa Belleza";

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>
            PANEL ADMINISTRATIVO
        </span>

        <h1>
            Clientes
        </h1>

        <p>
            Consulta los clientes registrados.
        </p>

    </div>

</section>


<section class="panel-contenido">

    <div class="tabla-contenedor">

        <table>

            <thead>

                <tr>

                    <th>
                        ID
                    </th>

                    <th>
                        Nombre
                    </th>

                    <th>
                        Teléfono
                    </th>

                    <th>
                        Correo
                    </th>

                    <th>
                        Registro
                    </th>

                </tr>

            </thead>

            <tbody>

                <?php

                $resultado = $conexion->query("
                    SELECT *
                    FROM clientes
                    ORDER BY id_cliente DESC
                ");

                if ($resultado->num_rows === 0):

                ?>

                    <tr>

                        <td
                            colspan="5"
                            class="sin-datos"
                        >

                            Todavía no hay clientes registrados.

                        </td>

                    </tr>

                <?php endif; ?>


                <?php while (
                    $cliente = $resultado->fetch_assoc()
                ): ?>

                    <tr>

                        <td>
                            #<?= $cliente['id_cliente'] ?>
                        </td>

                        <td>

                            <strong>
                                <?= htmlspecialchars(
                                    $cliente['nombre']
                                ) ?>
                            </strong>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $cliente['telefono']
                            ) ?>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $cliente['correo'] ?: 'No registrado'
                            ) ?>
                        </td>

                        <td>
                            <?= date(
                                'd/m/Y',
                                strtotime(
                                    $cliente['fecha_registro']
                                )
                            ) ?>
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