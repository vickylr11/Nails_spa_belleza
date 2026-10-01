<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/whatsapp.php";

$base = "../";

$titulo = "Agenda - Nails Spa Belleza";


// ==========================================
// FECHA (si llega algo raro, se usa hoy)
// ==========================================

$fecha = $_GET['fecha'] ?? date('Y-m-d');

if (strtotime($fecha) === false) {
    $fecha = date('Y-m-d');
}

$fecha = date('Y-m-d', strtotime($fecha));


// ==========================================
// ¿DE QUIÉN SE VEN LAS CITAS?
// La manicurista SIEMPRE ve solo las suyas: el número sale de su
// sesión, no del formulario, así no puede cambiarlo con F12.
// El administrador ve todas, o filtra por una manicurista.
// 0 = todas.
// ==========================================

if (es_admin()) {
    $filtro_manicurista = intval($_GET['manicurista'] ?? 0);
} else {
    $filtro_manicurista = intval($_SESSION['id_manicurista']);
}


// Estados que puede poner cada rol.
if (es_admin()) {
    $estados_permitidos = ['Pendiente', 'Confirmada', 'Completada', 'No asistió', 'Cancelada'];
} else {
    $estados_permitidos = ['Confirmada', 'Completada', 'No asistió'];
}


// ==========================================
// CITAS DEL DÍA
// (? = 0 OR r.id_manicurista = ?) -> si el filtro es 0, trae todas.
// ==========================================

$stmt = $conexion->prepare("
    SELECT
        r.*,
        c.nombre AS cliente,
        c.telefono,
        s.nombre AS servicio,
        m.nombre AS manicurista
    FROM reservas r
    INNER JOIN clientes c ON r.id_cliente = c.id_cliente
    INNER JOIN servicios s ON r.id_servicio = s.id_servicio
    INNER JOIN manicuristas m ON r.id_manicurista = m.id_manicurista
    WHERE r.fecha = ?
    AND (? = 0 OR r.id_manicurista = ?)
    ORDER BY r.hora ASC, m.nombre ASC
");

$stmt->bind_param("sii", $fecha, $filtro_manicurista, $filtro_manicurista);
$stmt->execute();

$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Bloqueos del día (ratos en que una manicurista no puede atender).
$stmt = $conexion->prepare("
    SELECT b.hora_inicio, b.hora_fin, b.motivo, m.nombre AS manicurista
    FROM bloqueos b
    INNER JOIN manicuristas m ON m.id_manicurista = b.id_manicurista
    WHERE b.fecha = ?
    AND (? = 0 OR b.id_manicurista = ?)
    ORDER BY b.hora_inicio, m.nombre
");
$stmt->bind_param("sii", $fecha, $filtro_manicurista, $filtro_manicurista);
$stmt->execute();

$bloqueos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$activas = 0;

foreach ($reservas as $r) {
    if (!in_array($r['estado'], ['Cancelada', 'No asistió'])) {
        $activas++;
    }
}

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>
            <?= es_admin() ? 'ADMINISTRADOR' : 'MANICURISTA' ?>
            · <?= htmlspecialchars($_SESSION['usuario_nombre']) ?>
        </span>

        <h1>
            <?= es_admin() ? 'Agenda del salón' : 'Mi agenda' ?>
        </h1>

        <p>
            Consulta y administra las citas.
        </p>

    </div>

    <?php if (es_admin()): ?>

        <a
            href="cita_nueva.php"
            class="btn-principal"
        >
            + Nueva cita
        </a>

    <?php endif; ?>

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
                Fecha
            </label>

            <input
                type="date"
                name="fecha"
                value="<?= htmlspecialchars($fecha) ?>"
            >

            <?php if (es_admin()): ?>

                <label>
                    Manicurista
                </label>

                <select name="manicurista">

                    <option value="0">Todas</option>

                    <?php

                    $lista = $conexion->query("
                        SELECT id_manicurista, nombre
                        FROM manicuristas
                        ORDER BY nombre
                    ");

                    while ($m = $lista->fetch_assoc()):

                    ?>

                        <option
                            value="<?= $m['id_manicurista'] ?>"
                            <?= $filtro_manicurista == $m['id_manicurista'] ? 'selected' : '' ?>
                        >
                            <?= htmlspecialchars($m['nombre']) ?>
                        </option>

                    <?php endwhile; ?>

                </select>

            <?php endif; ?>

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
                <?= date('d/m/Y', strtotime($fecha)) ?>
            </h2>

        </div>

        <div class="contador-citas">

            <?= $activas ?>

            <span>
                <?= $activas === 1 ? 'cita' : 'citas' ?>
            </span>

        </div>

    </div>


    <?php if (count($bloqueos) > 0): ?>

        <!-- Ratos bloqueados ese día: nadie puede agendar ahí -->
        <div class="bloqueos-dia">

            <strong>⛔ No disponibles este día</strong>

            <?php foreach ($bloqueos as $b): ?>
                <span>
                    <?= es_admin() ? htmlspecialchars($b['manicurista']) . ' · ' : '' ?>
                    <?= date('g:i A', strtotime($b['hora_inicio'])) ?> – <?= date('g:i A', strtotime($b['hora_fin'])) ?>
                    <?= $b['motivo'] ? '· ' . htmlspecialchars($b['motivo']) : '' ?>
                </span>
            <?php endforeach; ?>

            <a href="bloqueos.php">Ver bloqueos</a>

        </div>

    <?php endif; ?>


    <div class="tabla-contenedor">

        <table>

            <thead>

                <tr>
                    <th>Hora</th>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>Servicio</th>

                    <?php if (es_admin()): ?>
                        <th>Manicurista</th>
                        <th>Precio</th>
                    <?php endif; ?>

                    <th>Estado</th>
                    <th>Acción</th>
                    <th>WhatsApp</th>
                </tr>

            </thead>

            <tbody>

            <?php if (count($reservas) === 0): ?>

                <tr>
                    <td colspan="9" class="sin-datos">
                        No hay citas para este día.
                    </td>
                </tr>

            <?php endif; ?>


            <?php foreach ($reservas as $reserva): ?>

                <tr>

                    <td>
                        <strong>
                            <?= date('h:i A', strtotime($reserva['hora'])) ?>
                        </strong>
                    </td>

                    <td>
                        <?= htmlspecialchars($reserva['cliente']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($reserva['telefono']) ?>
                    </td>

                    <td>
                        <?= htmlspecialchars($reserva['servicio']) ?>

                        <?php if ($reserva['origen'] === 'salon'): ?>
                            <br><small class="marca">En el salón</small>
                        <?php endif; ?>

                        <?php if ($reserva['id_pago']): ?>
                            <br><small class="marca marca-pagada">Pagada a la manicurista</small>
                        <?php endif; ?>
                    </td>

                    <?php if (es_admin()): ?>

                        <td>
                            <?= htmlspecialchars($reserva['manicurista']) ?>
                        </td>

                        <td>
                            <?php if ($reserva['gratis']): ?>
                                <s>$<?= number_format($reserva['precio'], 0, ',', '.') ?></s>
                                <br><small class="marca marca-gratis">🎁 Gratis</small>
                            <?php else: ?>
                                $<?= number_format($reserva['precio'], 0, ',', '.') ?>
                            <?php endif; ?>
                        </td>

                    <?php endif; ?>

                    <td>
                        <span class="estado estado-<?= strtolower(str_replace(
                            [' ', 'ó'],
                            ['-', 'o'],
                            $reserva['estado']
                        )) ?>">
                            <?= htmlspecialchars($reserva['estado']) ?>
                        </span>
                    </td>

                    <td>

                        <?php if (in_array($reserva['estado'], ['Cancelada', 'No asistió']) || $reserva['id_pago']): ?>

                            <span>—</span>

                        <?php else: ?>

                            <form
                                action="cambiar_estado.php"
                                method="POST"
                                class="form-estado"
                            >

                                <input type="hidden" name="id" value="<?= $reserva['id_reserva'] ?>">

                                <input type="hidden" name="fecha" value="<?= htmlspecialchars($fecha) ?>">

                                <select name="estado" onchange="this.form.submit()">

                                    <?php

                                    // El estado actual siempre se muestra, aunque este
                                    // rol no pueda ponerlo (ej. Pendiente para la manicurista).
                                    $opciones = $estados_permitidos;

                                    if (!in_array($reserva['estado'], $opciones)) {
                                        array_unshift($opciones, $reserva['estado']);
                                    }

                                    foreach ($opciones as $opcion):

                                    ?>

                                        <option
                                            value="<?= $opcion ?>"
                                            <?= $reserva['estado'] === $opcion ? 'selected' : '' ?>
                                        >
                                            <?= $opcion ?>
                                        </option>

                                    <?php endforeach; ?>

                                </select>

                            </form>

                        <?php endif; ?>

                    </td>

                    <td>

                        <?php

                        // Solo para citas que siguen en pie.
                        $enlace = in_array($reserva['estado'], ['Pendiente', 'Confirmada'])
                            ? enlace_whatsapp($reserva)
                            : '';

                        ?>

                        <?php if ($enlace !== ''): ?>

                            <a
                                href="<?= htmlspecialchars($enlace) ?>"
                                target="_blank"
                                rel="noopener"
                                class="btn-whatsapp"
                                title="Abre WhatsApp con el mensaje ya escrito"
                            >
                                <?= $reserva['estado'] === 'Confirmada' ? 'Recordar' : 'Confirmar' ?>
                            </a>

                        <?php elseif (in_array($reserva['estado'], ['Pendiente', 'Confirmada'])): ?>

                            <small>Teléfono no válido</small>

                        <?php else: ?>

                            <span>—</span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>

<?php

require_once "../includes/footer.php";

?>
