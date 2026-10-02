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


// Todos los servicios activos, para las casillas «¿Qué servicios hace?».
$servicios = $conexion->query("
    SELECT id_servicio, nombre
    FROM servicios
    WHERE activo = 1
    ORDER BY nombre
")->fetch_all(MYSQLI_ASSOC);

// Cuáles van marcados:
//   volvió de un error -> los que había marcado
//   editando           -> los que hace (tabla manicurista_servicio)
//   nueva              -> todos (lo normal es que haga casi todo; se desmarca lo que no)
if ($viejo !== null) {

    $marcados = array_map('intval', $viejo['servicios'] ?? []);

} elseif ($editar) {

    $stmt = $conexion->prepare("SELECT id_servicio FROM manicurista_servicio WHERE id_manicurista = ?");
    $stmt->bind_param("i", $editar['id_manicurista']);
    $stmt->execute();

    $marcados = array_map('intval', array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'id_servicio'));

} else {

    $marcados = array_map('intval', array_column($servicios, 'id_servicio'));
}


// Lista con: citas próximas y si tiene cuenta para entrar.
$manicuristas = $conexion->query("
    SELECT m.*,
        (SELECT COUNT(*) FROM reservas r
         WHERE r.id_manicurista = m.id_manicurista
         AND r.fecha >= CURDATE()
         AND r.estado IN ('Pendiente', 'Confirmada')) AS citas_proximas,
        (SELECT GROUP_CONCAT(s.nombre ORDER BY s.nombre SEPARATOR ', ')
         FROM manicurista_servicio ms
         JOIN servicios s ON s.id_servicio = ms.id_servicio AND s.activo = 1
         WHERE ms.id_manicurista = m.id_manicurista) AS que_hace,
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

            <form action="guardar_manicurista.php" method="POST" enctype="multipart/form-data">

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

                <label>Foto (JPG, PNG o WEBP, máximo 5 MB)</label>

                <?php if (!empty($editar['foto'])): ?>
                    <img
                        src="../<?= htmlspecialchars($editar['foto']) ?>"
                        alt="Foto actual"
                        class="foto-manicurista-grande"
                    >
                <?php endif; ?>

                <input type="file" name="foto" accept="image/jpeg,image/png,image/webp">

                <p class="nota">
                    Sale en la página de reservar. Mejor una foto cuadrada, de la cara.
                    <?= $editar ? 'Si no escoges una nueva, se conserva la actual.' : 'Sin foto, se muestran sus iniciales.' ?>
                </p>

                <?php if (!empty($editar['foto'])): ?>
                    <label class="casilla">
                        <input type="checkbox" name="quitar_foto" value="1">
                        Quitar la foto (mostrar sus iniciales)
                    </label>
                <?php endif; ?>

                <label>¿Qué servicios hace?</label>

                <div class="casillas-servicios">
                    <?php foreach ($servicios as $s): ?>
                        <label class="casilla">
                            <input
                                type="checkbox"
                                name="servicios[]"
                                value="<?= $s['id_servicio'] ?>"
                                <?= in_array(intval($s['id_servicio']), $marcados, true) ? 'checked' : '' ?>
                            >
                            <?= htmlspecialchars($s['nombre']) ?>
                        </label>
                    <?php endforeach; ?>
                </div>

                <p class="nota">
                    En la página, al escogerla, la clienta solo ve estos servicios.
                    Quitar uno no cambia las citas que ya tiene agendadas.
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
                        <th>Manicurista</th>
                        <th>Gana</th>
                        <th>Por atender</th>
                        <th>Cuenta</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($manicuristas as $m): ?>

                    <tr class="<?= $m['activa'] ? '' : 'fila-inactiva' ?>">

                        <td>
                            <div class="celda-manicurista">

                                <?php if ($m['foto']): ?>
                                    <img src="../<?= htmlspecialchars($m['foto']) ?>" alt="" class="foto-manicurista">
                                <?php else: ?>
                                    <span class="foto-manicurista avatar-iniciales"><?= htmlspecialchars(iniciales($m['nombre'])) ?></span>
                                <?php endif; ?>

                                <div>
                                    <strong><?= htmlspecialchars($m['nombre']) ?></strong>

                                    <?php if (!$m['activa']): ?>
                                        <span class="estado estado-cancelada">Inactiva</span>
                                    <?php endif; ?>

                                    <small><?= $m['que_hace'] ? htmlspecialchars($m['que_hace']) : '⚠ Sin servicios: no sale en la página' ?></small>
                                </div>

                            </div>
                        </td>

                        <td><?= $m['porcentaje'] ?>%</td>

                        <td><?= $m['citas_proximas'] ?></td>

                        <td>
                            <?php if ($m['correo']): ?>
                                <small><?= htmlspecialchars($m['correo']) ?></small>
                            <?php else: ?>
                                <a href="usuarios.php?nueva_para=<?= $m['id_manicurista'] ?>">Crear cuenta</a>
                            <?php endif; ?>
                        </td>

                        <td class="acciones-fila">

                            <a href="manicuristas.php?id=<?= $m['id_manicurista'] ?>" class="btn-pequeno">Editar</a>

                            <form action="guardar_manicurista.php" method="POST" enctype="multipart/form-data">
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
