<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

exigir_admin();

$base = "../";

$titulo = "Servicios - Nails Spa Belleza";


// ==========================================
// ¿SE ESTÁ EDITANDO UNO? (servicios.php?id=3)
// ==========================================

$editar = null;

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    $stmt = $conexion->prepare("SELECT * FROM servicios WHERE id_servicio = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $editar = $stmt->get_result()->fetch_assoc();
}


// Lo que se escribió antes de un error (para no perderlo).
$viejo = $_SESSION['viejo'] ?? null;
unset($_SESSION['viejo']);

// ==========================================
// LISTA (activos primero)
// ==========================================

$servicios = $conexion->query("
    SELECT s.*,
        (SELECT COUNT(*) FROM reservas r
         WHERE r.id_servicio = s.id_servicio
         AND r.fecha >= CURDATE()
         AND r.estado IN ('Pendiente', 'Confirmada')) AS citas_proximas
    FROM servicios s
    ORDER BY s.activo DESC, s.nombre ASC
")->fetch_all(MYSQLI_ASSOC);

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>ADMINISTRADOR</span>

        <h1>Servicios</h1>

        <p>Lo que el salón ofrece: precio, duración y foto.</p>

    </div>

</section>


<section class="panel-contenido">

    <?php mostrar_mensajes(); ?>

    <div class="admin-grid">

        <!-- ================= FORMULARIO ================= -->

        <div class="form-card admin-form">

            <h2><?= $editar ? 'Editar servicio' : 'Nuevo servicio' ?></h2>

            <form
                action="guardar_servicio.php"
                method="POST"
                enctype="multipart/form-data"
            >

                <input type="hidden" name="accion" value="guardar">

                <input type="hidden" name="id_servicio" value="<?= $editar['id_servicio'] ?? 0 ?>">

                <label>Nombre</label>
                <input
                    type="text"
                    name="nombre"
                    maxlength="100"
                    required
                    value="<?= htmlspecialchars(valor('nombre', $editar, $viejo)) ?>"
                >

                <label>Descripción</label>
                <textarea
                    name="descripcion"
                    rows="3"
                    maxlength="500"
                ><?= htmlspecialchars(valor('descripcion', $editar, $viejo)) ?></textarea>

                <label>Precio (pesos, sin puntos)</label>
                <input
                    type="number"
                    name="precio"
                    min="1000"
                    max="10000000"
                    step="500"
                    required
                    value="<?= htmlspecialchars((string) intval(valor('precio', $editar, $viejo, 0)) ?: '') ?>"
                >

                <label>Duración (minutos)</label>
                <input
                    type="number"
                    name="duracion"
                    min="15"
                    max="480"
                    step="5"
                    required
                    value="<?= htmlspecialchars((string) valor('duracion', $editar, $viejo)) ?>"
                >

                <?php if ($editar): ?>
                    <p class="nota">
                        Cambiar el precio o la duración NO cambia las citas ya agendadas:
                        cada cita guardó el precio y la duración del día en que se reservó.
                    </p>
                <?php endif; ?>

                <label>Foto (JPG, PNG o WEBP, máximo 5 MB)</label>

                <?php if (!empty($editar['imagen'])): ?>
                    <img
                        src="../<?= htmlspecialchars($editar['imagen']) ?>"
                        alt="Foto actual"
                        class="miniatura-grande"
                    >
                <?php endif; ?>

                <input type="file" name="imagen" accept="image/jpeg,image/png,image/webp">

                <?php if ($editar): ?>
                    <p class="nota">Si no escoges una foto nueva, se conserva la actual.</p>
                <?php endif; ?>

                <button type="submit" class="btn-principal btn-completo">
                    <?= $editar ? 'Guardar cambios' : 'Crear servicio' ?>
                </button>

                <?php if ($editar): ?>
                    <a href="servicios.php" class="btn-secundario">Cancelar</a>
                <?php endif; ?>

            </form>

        </div>


        <!-- ================= LISTA ================= -->

        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Servicio</th>
                        <th>Precio</th>
                        <th>Duración</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($servicios as $s): ?>

                    <tr class="<?= $s['activo'] ? '' : 'fila-inactiva' ?>">

                        <td>
                            <img
                                src="../<?= htmlspecialchars($s['imagen'] ?: 'assets/img/logo.png') ?>"
                                alt=""
                                class="miniatura"
                            >
                        </td>

                        <td>
                            <strong><?= htmlspecialchars($s['nombre']) ?></strong>

                            <?php if ($s['citas_proximas'] > 0): ?>
                                <br><small><?= $s['citas_proximas'] ?> citas próximas</small>
                            <?php endif; ?>
                        </td>

                        <td>$<?= number_format($s['precio'], 0, ',', '.') ?></td>

                        <td><?= $s['duracion'] ?> min</td>

                        <td>
                            <span class="estado <?= $s['activo'] ? 'estado-confirmada' : 'estado-cancelada' ?>">
                                <?= $s['activo'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>

                        <td class="acciones-fila">

                            <a href="servicios.php?id=<?= $s['id_servicio'] ?>" class="btn-pequeno">Editar</a>

                            <form action="guardar_servicio.php" method="POST">
                                <input type="hidden" name="accion" value="cambiar_activo">
                                <input type="hidden" name="id_servicio" value="<?= $s['id_servicio'] ?>">
                                <button type="submit" class="btn-pequeno">
                                    <?= $s['activo'] ? 'Desactivar' : 'Activar' ?>
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
