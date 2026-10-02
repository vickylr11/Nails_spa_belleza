<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

$base = "../";

$titulo = (es_admin() ? "Bloqueos" : "Mis bloqueos") . " - Nails Spa Belleza";


// ==========================================
// BLOQUEOS: ratos en que una manicurista no puede atender
// (cita médica, reunión, diligencia...). Mientras exista el bloqueo,
// esas horas salen «No disponible» en la página y en el salón.
//
// manicurista   -> ve y crea solo los SUYOS (el número sale de su sesión)
// administrador -> ve los de todas y crea para cualquiera
// ==========================================

$viejo = $_SESSION['viejo'] ?? null;
unset($_SESSION['viejo']);


// Manicuristas activas (solo las necesita el administrador para escoger).
$manicuristas = es_admin()
    ? $conexion->query("SELECT id_manicurista, nombre FROM manicuristas WHERE activa = 1 ORDER BY nombre")
        ->fetch_all(MYSQLI_ASSOC)
    : [];


// Bloqueos de hoy en adelante.
// (? = 0 OR ...) -> el administrador ve todos; la manicurista, solo los suyos.
$filtro = es_admin() ? 0 : intval($_SESSION['id_manicurista']);

$stmt = $conexion->prepare("
    SELECT b.*, m.nombre AS manicurista, u.nombre AS registro
    FROM bloqueos b
    INNER JOIN manicuristas m ON m.id_manicurista = b.id_manicurista
    INNER JOIN usuarios u ON u.id_usuario = b.id_usuario
    WHERE b.fecha >= CURDATE()
    AND (? = 0 OR b.id_manicurista = ?)
    ORDER BY b.fecha, b.hora_inicio, m.nombre
");
$stmt->bind_param("ii", $filtro, $filtro);
$stmt->execute();

$bloqueos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$dias = [1 => 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span><?= es_admin() ? 'ADMINISTRADOR' : 'MANICURISTA' ?></span>

        <h1><?= es_admin() ? 'Bloqueos de horario' : 'Mis bloqueos' ?></h1>

        <p>Cuando una manicurista no puede atender (cita médica, reunión…), se bloquea ese rato y nadie la puede agendar.</p>

    </div>

</section>


<section class="panel-contenido">

    <?php mostrar_mensajes(); ?>

    <div class="admin-grid">

        <!-- ================= FORMULARIO ================= -->

        <div class="form-card admin-form">

            <h2>Bloquear un rato</h2>

            <form action="guardar_bloqueo.php" method="POST">

                <input type="hidden" name="accion" value="crear">

                <?php if (es_admin()): ?>

                    <label>Manicurista</label>
                    <select name="id_manicurista" required>
                        <option value="">Escoge…</option>
                        <?php foreach ($manicuristas as $m): ?>
                            <option value="<?= $m['id_manicurista'] ?>"
                                <?= intval($viejo['id_manicurista'] ?? 0) === intval($m['id_manicurista']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php endif; ?>

                <label>Fecha</label>
                <input type="date" name="fecha" min="<?= date('Y-m-d') ?>" required
                       value="<?= htmlspecialchars($viejo['fecha'] ?? '') ?>">

                <label class="casilla">
                    <input type="checkbox" name="todo_el_dia" value="1" id="todo-el-dia"
                        <?= isset($viejo['todo_el_dia']) ? 'checked' : '' ?>>
                    Todo el día
                </label>

                <div class="horas-bloqueo" id="horas-bloqueo">

                    <div>
                        <label>Desde</label>
                        <input type="time" name="hora_inicio" step="900"
                               value="<?= htmlspecialchars($viejo['hora_inicio'] ?? '') ?>">
                    </div>

                    <div>
                        <label>Hasta</label>
                        <input type="time" name="hora_fin" step="900"
                               value="<?= htmlspecialchars($viejo['hora_fin'] ?? '') ?>">
                    </div>

                </div>

                <label>Motivo (solo lo ve el personal)</label>
                <input type="text" name="motivo" maxlength="100" placeholder="Ej: cita médica, reunión"
                       value="<?= htmlspecialchars($viejo['motivo'] ?? '') ?>">

                <p class="nota">
                    Si ya tiene una cita en ese rato, no se puede bloquear: primero hay que
                    mover o cancelar la cita, para que ninguna clienta quede sin quién la atienda.
                    En la página, la clienta solo ve «No disponible».
                </p>

                <button type="submit" class="btn-principal btn-completo">Bloquear</button>

            </form>

        </div>


        <!-- ================= LISTA ================= -->

        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Horario</th>
                        <?php if (es_admin()): ?>
                            <th>Manicurista</th>
                        <?php endif; ?>
                        <th>Motivo</th>
                        <th>Acción</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (count($bloqueos) === 0): ?>
                    <tr>
                        <td colspan="5" class="sin-datos">No hay bloqueos de hoy en adelante.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($bloqueos as $b): ?>

                    <tr>

                        <td>
                            <strong><?= date('d/m/Y', strtotime($b['fecha'])) ?></strong>
                            <br><small><?= $dias[date('N', strtotime($b['fecha']))] ?></small>
                        </td>

                        <td>
                            <?= date('g:i A', strtotime($b['hora_inicio'])) ?> –
                            <?= date('g:i A', strtotime($b['hora_fin'])) ?>
                        </td>

                        <?php if (es_admin()): ?>
                            <td><?= htmlspecialchars($b['manicurista']) ?></td>
                        <?php endif; ?>

                        <td>
                            <?= $b['motivo'] !== null && $b['motivo'] !== '' ? htmlspecialchars($b['motivo']) : '—' ?>
                            <br><small>Lo registró: <?= htmlspecialchars($b['registro']) ?></small>
                        </td>

                        <td>
                            <form action="guardar_bloqueo.php" method="POST"
                                  onsubmit="return confirm('¿Quitar este bloqueo? Esas horas vuelven a quedar libres.');">
                                <input type="hidden" name="accion" value="quitar">
                                <input type="hidden" name="id_bloqueo" value="<?= $b['id_bloqueo'] ?>">
                                <button type="submit" class="btn-pequeno">Quitar</button>
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
