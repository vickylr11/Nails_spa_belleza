<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

exigir_admin();

$base = "../";

$titulo = "Usuarios - Nails Spa Belleza";


$editar = null;

if (isset($_GET['id'])) {

    $id = intval($_GET['id']);

    $stmt = $conexion->prepare("
        SELECT id_usuario, nombre, correo, rol, id_manicurista, activo
        FROM usuarios
        WHERE id_usuario = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $editar = $stmt->get_result()->fetch_assoc();
}

$viejo = $_SESSION['viejo'] ?? null;
unset($_SESSION['viejo']);


// Desde Manicuristas: "Crear cuenta" llega con ?nueva_para=ID
// y el formulario sale listo para esa manicurista.
if (!$editar && $viejo === null && isset($_GET['nueva_para'])) {

    $para = intval($_GET['nueva_para']);

    $stmt = $conexion->prepare("SELECT nombre FROM manicuristas WHERE id_manicurista = ?");
    $stmt->bind_param("i", $para);
    $stmt->execute();

    $m = $stmt->get_result()->fetch_assoc();

    if ($m) {
        $viejo = ['nombre' => $m['nombre'], 'rol' => 'manicurista', 'id_manicurista' => $para, 'correo' => ''];
    }
}

$es_yo = $editar && intval($editar['id_usuario']) === intval($_SESSION['usuario_id']);


// Manicuristas que se pueden escoger: las que aún no tienen cuenta,
// más la que ya tiene este usuario (si se está editando).
$id_actual = intval($editar['id_manicurista'] ?? 0);

$stmt = $conexion->prepare("
    SELECT m.id_manicurista, m.nombre
    FROM manicuristas m
    LEFT JOIN usuarios u ON u.id_manicurista = m.id_manicurista
    WHERE u.id_usuario IS NULL OR m.id_manicurista = ?
    ORDER BY m.nombre
");
$stmt->bind_param("i", $id_actual);
$stmt->execute();
$disponibles = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


$usuarios = $conexion->query("
    SELECT u.id_usuario, u.nombre, u.correo, u.rol, u.activo, m.nombre AS manicurista
    FROM usuarios u
    LEFT JOIN manicuristas m ON m.id_manicurista = u.id_manicurista
    ORDER BY u.activo DESC, u.rol ASC, u.nombre ASC
")->fetch_all(MYSQLI_ASSOC);

$rol_elegido = valor('rol', $editar, $viejo, 'manicurista');

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>ADMINISTRADOR</span>

        <h1>Usuarios</h1>

        <p>Quién puede entrar a /super/ y qué puede hacer.</p>

    </div>

</section>


<section class="panel-contenido">

    <?php mostrar_mensajes(); ?>

    <div class="admin-grid">

        <div class="form-card admin-form">

            <h2><?= $editar ? 'Editar usuario' : 'Nuevo usuario' ?></h2>

            <form action="guardar_usuario.php" method="POST" autocomplete="off">

                <input type="hidden" name="accion" value="guardar">

                <input type="hidden" name="id_usuario" value="<?= $editar['id_usuario'] ?? 0 ?>">

                <label>Nombre</label>
                <input
                    type="text"
                    name="nombre"
                    maxlength="100"
                    required
                    value="<?= htmlspecialchars(valor('nombre', $editar, $viejo)) ?>"
                >

                <label>Correo (con este entra)</label>
                <input
                    type="email"
                    name="correo"
                    maxlength="100"
                    required
                    value="<?= htmlspecialchars(valor('correo', $editar, $viejo)) ?>"
                >

                <label>Rol</label>

                <?php if ($es_yo): ?>

                    <input type="hidden" name="rol" value="administrador">
                    <p class="nota">Eres tú: no puedes quitarte el rol de administrador.</p>

                <?php else: ?>

                    <select name="rol" id="rol" required>
                        <option value="manicurista" <?= $rol_elegido === 'manicurista' ? 'selected' : '' ?>>
                            Manicurista
                        </option>
                        <option value="administrador" <?= $rol_elegido === 'administrador' ? 'selected' : '' ?>>
                            Administrador
                        </option>
                    </select>

                    <p class="nota">
                        Manicurista: solo ve su agenda y marca sus citas.
                        Administrador: todo el panel.
                    </p>

                <?php endif; ?>

                <div id="campo-manicurista">

                    <label>¿Cuál manicurista es?</label>

                    <select name="id_manicurista">

                        <option value="0">Escoge…</option>

                        <?php foreach ($disponibles as $m): ?>
                            <option
                                value="<?= $m['id_manicurista'] ?>"
                                <?= intval(valor('id_manicurista', $editar, $viejo, 0)) === intval($m['id_manicurista']) ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($m['nombre']) ?>
                            </option>
                        <?php endforeach; ?>

                    </select>

                    <?php if (count($disponibles) === 0): ?>
                        <p class="nota">Todas las manicuristas ya tienen cuenta. Crea primero la manicurista en <a href="manicuristas.php">Manicuristas</a>.</p>
                    <?php endif; ?>

                </div>

                <label><?= $editar ? 'Contraseña nueva (déjala vacía para no cambiarla)' : 'Contraseña inicial (mínimo 8)' ?></label>
                <input
                    type="password"
                    name="clave"
                    minlength="8"
                    autocomplete="new-password"
                    <?= $editar ? '' : 'required' ?>
                >

                <p class="nota">Entrégasela a la persona y pídele que la cambie en «Mi contraseña».</p>

                <button type="submit" class="btn-principal btn-completo">
                    <?= $editar ? 'Guardar cambios' : 'Crear usuario' ?>
                </button>

                <?php if ($editar): ?>
                    <a href="usuarios.php" class="btn-secundario">Cancelar</a>
                <?php endif; ?>

            </form>

        </div>


        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>

                <tbody>

                <?php foreach ($usuarios as $u): ?>

                    <tr class="<?= $u['activo'] ? '' : 'fila-inactiva' ?>">

                        <td>
                            <strong><?= htmlspecialchars($u['nombre']) ?></strong>
                            <?php if (intval($u['id_usuario']) === intval($_SESSION['usuario_id'])): ?>
                                <small>(tú)</small>
                            <?php endif; ?>
                        </td>

                        <td><?= htmlspecialchars($u['correo']) ?></td>

                        <td>
                            <?= $u['rol'] === 'administrador' ? 'Administrador' : 'Manicurista' ?>
                            <?php if ($u['manicurista']): ?>
                                <br><small><?= htmlspecialchars($u['manicurista']) ?></small>
                            <?php endif; ?>
                        </td>

                        <td>
                            <span class="estado <?= $u['activo'] ? 'estado-confirmada' : 'estado-cancelada' ?>">
                                <?= $u['activo'] ? 'Puede entrar' : 'Sin acceso' ?>
                            </span>
                        </td>

                        <td class="acciones-fila">

                            <a href="usuarios.php?id=<?= $u['id_usuario'] ?>" class="btn-pequeno">Editar</a>

                            <?php if (intval($u['id_usuario']) !== intval($_SESSION['usuario_id'])): ?>

                                <form action="guardar_usuario.php" method="POST">
                                    <input type="hidden" name="accion" value="cambiar_activo">
                                    <input type="hidden" name="id_usuario" value="<?= $u['id_usuario'] ?>">
                                    <button type="submit" class="btn-pequeno">
                                        <?= $u['activo'] ? 'Quitar acceso' : 'Dar acceso' ?>
                                    </button>
                                </form>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
