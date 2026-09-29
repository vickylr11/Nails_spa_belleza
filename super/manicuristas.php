<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

exigir_admin();

$base = "../";

$titulo = "Manicuristas - Nails Spa Belleza";


$editar = null;

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    $stmt = $conexion->prepare("SELECT * FROM manicuristas WHERE id_manicurista = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $editar = $stmt->get_result()->fetch_assoc();
}

$viejo = $_SESSION['viejo'] ?? null;
unset($_SESSION['viejo']);


// Lista con: citas próximas y si tiene cuenta para entrar.
$manicuristas = $conexion->query("
    SELECT m.*,
        (SELECT COUNT(*) FROM reservas r
         WHERE r.id_manicurista = m.id_manicurista
         AND r.fecha >= CURDATE()
         AND r.estado IN ('Pendiente', 'Confirmada')) AS citas_proximas,
        u.correo
    FROM manicuristas m
    LEFT JOIN usuarios u ON u.id_manicurista = m.id_manicurista
    ORDER BY m.activa DESC, m.nombre ASC
")->fetch_all(MYSQLI_ASSOC);

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>ADMINISTRADOR</span>

        <h1>Manicuristas</h1>

        <p>Cuántas manicuristas activas hay = cuántas clientas se atienden a la misma hora.</p>

    </div>

</section>


<section class="panel-contenido">

    <?php mostrar_mensajes(); ?>

    <div class="admin-grid">

        <div class="form-card admin-form">

            <h2><?= $editar ? 'Editar manicurista' : 'Nueva manicurista' ?></h2>

            <form action="guardar_manicurista.php" method="POST">

                <input type="hidden" name="accion" value="guardar">

                <input type="hidden" name="id_manicurista" value="<?= $editar['id_manicurista'] ?? 0 ?>">

                <label>Nombre completo</label>
                <input
                    type="text"
                    name="nombre"
                    maxlength="100"
                    required
                    value="<?= htmlspecialchars(valor('nombre', $editar, $viejo)) ?>"
                >

                <label>Porcentaje que gana por servicio (%)</label>
                <input
                    type="number"
                    name="porcentaje"
                    min="0"
                    max="100"
                    step="1"
                    required
                    value="<?= htmlspecialchars((string) valor('porcentaje', $editar, $viejo, 50)) ?>"
                >

                <p class="nota">
                    Ejemplo: con 50%, por un servicio de $40.000 se le pagan $20.000.
                    Cambiarlo no altera lo que ya se le pagó.
                </p>

                <p class="nota">
                    Una manicurista nueva empieza a recibir citas de inmediato.
                    Para que entre a ver su agenda, créele una cuenta en <a href="usuarios.php">Usuarios</a>.
                </p>

                <button type="submit" class="btn-principal btn-completo">
                    <?= $editar ? 'Guardar cambios' : 'Crear manicurista' ?>
                </button>

                <?php if ($editar): ?>
                    <a href="manicuristas.php" class="btn-secundario">Cancelar</a>
                <?php endif; ?>

            </form>

        </div>


        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Gana</th>
                        <th>Citas próximas</th>
                        <th>Cuenta</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($manicuristas as $m): ?>

                    <tr class="<?= $m['activa'] ? '' : 'fila-inactiva' ?>">

                        <td><strong><?= htmlspecialchars($m['nombre']) ?></strong></td>

                        <td><?= $m['porcentaje'] ?>%</td>

                        <td><?= $m['citas_proximas'] ?></td>

                        <td>
                            <?php if ($m['correo']): ?>
                                <?= htmlspecialchars($m['correo']) ?>
                            <?php else: ?>
                                <a href="usuarios.php?nueva_para=<?= $m['id_manicurista'] ?>">Crear cuenta</a>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="estado <?= $m['activa'] ? 'estado-confirmada' : 'estado-cancelada' ?>">
                                <?= $m['activa'] ? 'Activa' : 'Inactiva' ?>
                            </span>
                        </td>

                        <td class="acciones-fila">

                            <a href="manicuristas.php?id=<?= $m['id_manicurista'] ?>" class="btn-pequeno">Editar</a>

                            <form action="guardar_manicurista.php" method="POST">
                                <input type="hidden" name="accion" value="cambiar_activa">
                                <input type="hidden" name="id_manicurista" value="<?= $m['id_manicurista'] ?>">
                                <button type="submit" class="btn-pequeno">
                                    <?= $m['activa'] ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
