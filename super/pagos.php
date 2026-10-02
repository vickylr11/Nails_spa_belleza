<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

$base = "../";

$titulo = "Pagos - Nails Spa Belleza";


// ==========================================
// PERIODO (por defecto, la quincena actual: 1 al 15, o 16 a fin de mes)
// ==========================================

function fecha_valida($texto, $defecto)
{
    $t = strtotime($texto ?? '');

    return $t === false ? $defecto : date('Y-m-d', $t);
}

$hoy = date('Y-m-d');

if (intval(date('j')) <= 15) {
    $quincena_desde = date('Y-m-01');
    $quincena_hasta = date('Y-m-15');
    $anterior_desde = date('Y-m-16', strtotime('first day of last month'));
    $anterior_hasta = date('Y-m-t', strtotime('first day of last month'));
} else {
    $quincena_desde = date('Y-m-16');
    $quincena_hasta = date('Y-m-t');
    $anterior_desde = date('Y-m-01');
    $anterior_hasta = date('Y-m-15');
}

$desde = fecha_valida($_GET['desde'] ?? null, $quincena_desde);
$hasta = fecha_valida($_GET['hasta'] ?? null, $quincena_hasta);

if ($desde > $hasta) {
    [$desde, $hasta] = [$hasta, $desde];
}


// ==========================================
// ¿DE QUIÉN?
// La manicurista SIEMPRE ve solo lo suyo (el número sale de su sesión).
// El administrador ve el resumen de todas, o el detalle de una.
// ==========================================

if (es_admin()) {
    $id_manicurista = intval($_GET['manicurista'] ?? 0);
} else {
    $id_manicurista = intval($_SESSION['id_manicurista']);
}

$ver_pago = intval($_GET['pago'] ?? 0);


// Lo que se le paga por cada cita: precio de la cita × porcentaje de la manicurista.
// (El precio es el que quedó guardado en la cita el día que se reservó.)
$VALOR = "ROUND(r.precio * m.porcentaje / 100, 0)";


// ==========================================
// 1. RESUMEN: lo pendiente de cada manicurista en el periodo (solo admin)
// ==========================================

$resumen = [];

if (es_admin() && $id_manicurista === 0 && $ver_pago === 0) {

    $stmt = $conexion->prepare("
        SELECT m.id_manicurista, m.nombre, m.porcentaje, m.activa,
            COUNT(r.id_reserva) AS servicios,
            COALESCE(SUM(r.precio), 0) AS ventas,
            COALESCE(SUM($VALOR), 0) AS a_pagar
        FROM manicuristas m
        LEFT JOIN reservas r
            ON r.id_manicurista = m.id_manicurista
            AND r.estado = 'Completada'
            AND r.id_pago IS NULL
            AND r.fecha BETWEEN ? AND ?
        GROUP BY m.id_manicurista
        ORDER BY m.activa DESC, m.nombre
    ");
    $stmt->bind_param("ss", $desde, $hasta);
    $stmt->execute();

    $resumen = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}


// ==========================================
// 2. DETALLE de una manicurista: servicios completados sin pagar
// ==========================================

$manicurista = null;
$detalle = [];
$total_detalle = 0;
$ventas_detalle = 0;
$pendientes_antes = 0;

if ($id_manicurista > 0 && $ver_pago === 0) {

    $stmt = $conexion->prepare("SELECT id_manicurista, nombre, porcentaje FROM manicuristas WHERE id_manicurista = ?");
    $stmt->bind_param("i", $id_manicurista);
    $stmt->execute();

    $manicurista = $stmt->get_result()->fetch_assoc();

    if ($manicurista) {

        $stmt = $conexion->prepare("
            SELECT r.id_reserva, r.fecha, r.hora, r.precio, r.origen, r.gratis,
                c.nombre AS cliente, s.nombre AS servicio,
                $VALOR AS valor
            FROM reservas r
            INNER JOIN manicuristas m ON m.id_manicurista = r.id_manicurista
            INNER JOIN clientes c ON c.id_cliente = r.id_cliente
            INNER JOIN servicios s ON s.id_servicio = r.id_servicio
            WHERE r.id_manicurista = ?
            AND r.estado = 'Completada'
            AND r.id_pago IS NULL
            AND r.fecha BETWEEN ? AND ?
            ORDER BY r.fecha, r.hora
        ");
        $stmt->bind_param("iss", $id_manicurista, $desde, $hasta);
        $stmt->execute();

        $detalle = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

        foreach ($detalle as $d) {
            $total_detalle += $d['valor'];
            $ventas_detalle += $d['precio'];
        }

        // Servicios sin pagar de ANTES del periodo: que no se olviden.
        $stmt = $conexion->prepare("
            SELECT COUNT(*) AS n
            FROM reservas
            WHERE id_manicurista = ? AND estado = 'Completada' AND id_pago IS NULL AND fecha < ?
        ");
        $stmt->bind_param("is", $id_manicurista, $desde);
        $stmt->execute();

        $pendientes_antes = intval($stmt->get_result()->fetch_assoc()['n']);
    }
}


// ==========================================
// 3. UN PAGO YA HECHO (comprobante): pagos.php?pago=ID
// ==========================================

$pago = null;
$pago_detalle = [];

if ($ver_pago > 0) {

    $stmt = $conexion->prepare("
        SELECT p.*, m.nombre AS manicurista, u.nombre AS registro
        FROM pagos p
        INNER JOIN manicuristas m ON m.id_manicurista = p.id_manicurista
        INNER JOIN usuarios u ON u.id_usuario = p.id_usuario
        WHERE p.id_pago = ?
    ");
    $stmt->bind_param("i", $ver_pago);
    $stmt->execute();

    $pago = $stmt->get_result()->fetch_assoc();

    // La manicurista solo puede ver SUS pagos.
    if ($pago && !es_admin() && intval($pago['id_manicurista']) !== intval($_SESSION['id_manicurista'])) {
        $pago = null;
    }

    if ($pago) {

        $stmt = $conexion->prepare("
            SELECT r.fecha, r.hora, r.precio, r.valor_manicurista, r.origen, r.gratis,
                c.nombre AS cliente, s.nombre AS servicio
            FROM reservas r
            INNER JOIN clientes c ON c.id_cliente = r.id_cliente
            INNER JOIN servicios s ON s.id_servicio = r.id_servicio
            WHERE r.id_pago = ?
            ORDER BY r.fecha, r.hora
        ");
        $stmt->bind_param("i", $ver_pago);
        $stmt->execute();

        $pago_detalle = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}


// ==========================================
// 4. HISTORIAL DE PAGOS
// ==========================================

$stmt = $conexion->prepare("
    SELECT p.id_pago, p.desde, p.hasta, p.servicios, p.total, p.fecha_pago, m.nombre AS manicurista
    FROM pagos p
    INNER JOIN manicuristas m ON m.id_manicurista = p.id_manicurista
    WHERE (? = 0 OR p.id_manicurista = ?)
    ORDER BY p.fecha_pago DESC
    LIMIT 30
");
$stmt->bind_param("ii", $id_manicurista, $id_manicurista);
$stmt->execute();

$historial = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


$manicuristas = es_admin()
    ? $conexion->query("SELECT id_manicurista, nombre FROM manicuristas ORDER BY nombre")->fetch_all(MYSQLI_ASSOC)
    : [];

function pesos($valor)
{
    return '$' . number_format($valor, 0, ',', '.');
}

function fecha_corta($fecha)
{
    return date('d/m/Y', strtotime($fecha));
}

function enlace_periodo($desde, $hasta, $id_manicurista)
{
    return "pagos.php?desde=$desde&hasta=$hasta" . ($id_manicurista ? "&manicurista=$id_manicurista" : "");
}

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span><?= es_admin() ? 'ADMINISTRADOR' : 'MANICURISTA · ' . htmlspecialchars($_SESSION['usuario_nombre']) ?></span>

        <h1><?= es_admin() ? 'Pagos a manicuristas' : 'Mis pagos' ?></h1>

        <p>Servicios <strong>Completados</strong> por cada manicurista y lo que se le paga.</p>

    </div>

</section>


<section class="panel-contenido">

    <?php mostrar_mensajes(); ?>


    <?php if (!$pago): ?>

        <!-- ================= PERIODO ================= -->

        <div class="filtros no-imprimir">

            <form method="GET">

                <label>Desde</label>
                <input type="date" name="desde" value="<?= $desde ?>">

                <label>Hasta</label>
                <input type="date" name="hasta" value="<?= $hasta ?>">

                <?php if (es_admin()): ?>

                    <label>Manicurista</label>
                    <select name="manicurista">
                        <option value="0">Todas (resumen)</option>
                        <?php foreach ($manicuristas as $m): ?>
                            <option value="<?= $m['id_manicurista'] ?>" <?= $id_manicurista === intval($m['id_manicurista']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($m['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php endif; ?>

                <button type="submit" class="btn-principal">Ver</button>

            </form>

            <div class="atajos">
                <a href="<?= enlace_periodo($quincena_desde, $quincena_hasta, es_admin() ? $id_manicurista : 0) ?>">Esta quincena</a>
                <a href="<?= enlace_periodo($anterior_desde, $anterior_hasta, es_admin() ? $id_manicurista : 0) ?>">Quincena anterior</a>
                <a href="<?= enlace_periodo(date('Y-m-01'), date('Y-m-t'), es_admin() ? $id_manicurista : 0) ?>">Este mes</a>
            </div>

        </div>

    <?php endif; ?>


    <?php if ($resumen): ?>

        <!-- ================= RESUMEN (admin) ================= -->

        <div class="agenda-titulo">
            <div>
                <span>PENDIENTE POR PAGAR</span>
                <h2><?= fecha_corta($desde) ?> al <?= fecha_corta($hasta) ?></h2>
            </div>
        </div>

        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>Manicurista</th>
                        <th>Servicios</th>
                        <th>Ventas</th>
                        <th>Gana</th>
                        <th>A pagar</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>

                <?php $gran_total = 0; ?>

                <?php foreach ($resumen as $f): ?>

                    <?php $gran_total += $f['a_pagar']; ?>

                    <tr class="<?= $f['activa'] ? '' : 'fila-inactiva' ?>">
                        <td><?= htmlspecialchars($f['nombre']) ?></td>
                        <td><?= $f['servicios'] ?></td>
                        <td><?= pesos($f['ventas']) ?></td>
                        <td><?= $f['porcentaje'] ?>%</td>
                        <td><strong><?= pesos($f['a_pagar']) ?></strong></td>
                        <td>
                            <?php if ($f['servicios'] > 0): ?>
                                <a class="btn-pequeno" href="<?= enlace_periodo($desde, $hasta, $f['id_manicurista']) ?>">Ver y pagar</a>
                            <?php endif; ?>
                        </td>
                    </tr>

                <?php endforeach; ?>

                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="4">Total a pagar en el periodo</td>
                        <td colspan="2"><strong><?= pesos($gran_total) ?></strong></td>
                    </tr>
                </tfoot>

            </table>

        </div>

    <?php endif; ?>


    <?php if ($manicurista): ?>

        <!-- ================= DETALLE DE UNA MANICURISTA ================= -->

        <div class="agenda-titulo">
            <div>
                <span>SIN PAGAR · <?= fecha_corta($desde) ?> AL <?= fecha_corta($hasta) ?></span>
                <h2><?= htmlspecialchars($manicurista['nombre']) ?></h2>
            </div>
            <div class="contador-citas">
                <?= pesos($total_detalle) ?> <span>a pagar</span>
            </div>
        </div>

        <?php if ($pendientes_antes > 0): ?>
            <div class="alerta error">
                Ojo: tiene <?= $pendientes_antes ?> <?= $pendientes_antes === 1 ? 'servicio' : 'servicios' ?>
                sin pagar de antes del <?= fecha_corta($desde) ?>. Cambia la fecha «Desde» para verlos.
            </div>
        <?php endif; ?>

        <div class="tabla-contenedor">

            <table>

                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Clienta</th>
                        <th>Servicio</th>
                        <th>Precio</th>
                        <th>Gana (<?= $manicurista['porcentaje'] ?>%)</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (!$detalle): ?>
                    <tr><td colspan="6" class="sin-datos">No hay servicios completados sin pagar en este periodo.</td></tr>
                <?php endif; ?>

                <?php foreach ($detalle as $d): ?>
                    <tr>
                        <td><?= fecha_corta($d['fecha']) ?></td>
                        <td><?= date('h:i A', strtotime($d['hora'])) ?></td>
                        <td><?= htmlspecialchars($d['cliente']) ?></td>
                        <td>
                            <?= htmlspecialchars($d['servicio']) ?>
                            <?php if ($d['origen'] === 'salon'): ?><br><small class="marca">En el salón</small><?php endif; ?>
                            <?php if ($d['gratis']): ?><br><small class="marca marca-gratis">🎁 Gratis para la clienta</small><?php endif; ?>
                        </td>
                        <td><?= pesos($d['precio']) ?></td>
                        <td><strong><?= pesos($d['valor']) ?></strong></td>
                    </tr>
                <?php endforeach; ?>

                </tbody>

                <?php if ($detalle): ?>
                    <tfoot>
                        <tr>
                            <td colspan="4"><?= count($detalle) ?> <?= count($detalle) === 1 ? 'servicio' : 'servicios' ?></td>
                            <td><?= pesos($ventas_detalle) ?></td>
                            <td><strong><?= pesos($total_detalle) ?></strong></td>
                        </tr>
                    </tfoot>
                <?php endif; ?>

            </table>

        </div>

        <?php if (es_admin() && $detalle): ?>

            <form
                action="registrar_pago.php"
                method="POST"
                class="barra-pago no-imprimir"
                onsubmit="return confirm('¿Registrar el pago de <?= pesos($total_detalle) ?> a <?= htmlspecialchars(addslashes($manicurista['nombre'])) ?>? Estos servicios quedarán marcados como pagados.');"
            >
                <input type="hidden" name="id_manicurista" value="<?= $manicurista['id_manicurista'] ?>">
                <input type="hidden" name="desde" value="<?= $desde ?>">
                <input type="hidden" name="hasta" value="<?= $hasta ?>">
                <!-- El total que el administrador está viendo. El servidor lo vuelve a calcular
                     y, si no coincide (alguien cambió algo mientras tanto), no registra nada. -->
                <input type="hidden" name="total_visto" value="<?= $total_detalle ?>">

                <a href="<?= enlace_periodo($desde, $hasta, 0) ?>" class="btn-secundario">Volver al resumen</a>

                <button type="submit" class="btn-principal">
                    Registrar pago de <?= pesos($total_detalle) ?>
                </button>
            </form>

        <?php endif; ?>

    <?php endif; ?>


    <?php if ($pago): ?>

        <!-- ================= COMPROBANTE DE UN PAGO ================= -->

        <div class="form-card comprobante">

            <div class="comprobante-cabeza">
                <div>
                    <span class="marca">COMPROBANTE DE PAGO N.º <?= $pago['id_pago'] ?></span>
                    <h2><?= htmlspecialchars($pago['manicurista']) ?></h2>
                    <p>
                        Servicios del <?= fecha_corta($pago['desde']) ?> al <?= fecha_corta($pago['hasta']) ?> ·
                        Pagado el <?= date('d/m/Y h:i A', strtotime($pago['fecha_pago'])) ?> ·
                        Registró: <?= htmlspecialchars($pago['registro']) ?>
                    </p>
                </div>
                <div class="contador-citas"><?= pesos($pago['total']) ?></div>
            </div>

            <div class="tabla-scroll">

            <table>

                <thead>
                    <tr>
                        <th>Fecha</th>
                        <th>Hora</th>
                        <th>Clienta</th>
                        <th>Servicio</th>
                        <th>Precio</th>
                        <th>Se le pagó</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($pago_detalle as $d): ?>
                        <tr>
                            <td><?= fecha_corta($d['fecha']) ?></td>
                            <td><?= date('h:i A', strtotime($d['hora'])) ?></td>
                            <td><?= htmlspecialchars($d['cliente']) ?></td>
                            <td>
                                <?= htmlspecialchars($d['servicio']) ?>
                                <?php if ($d['gratis']): ?><br><small class="marca marca-gratis">🎁 Gratis para la clienta</small><?php endif; ?>
                            </td>
                            <td><?= pesos($d['precio']) ?></td>
                            <td><?= pesos($d['valor_manicurista']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>

                <tfoot>
                    <tr>
                        <td colspan="5"><?= $pago['servicios'] ?> <?= intval($pago['servicios']) === 1 ? 'servicio' : 'servicios' ?></td>
                        <td><strong><?= pesos($pago['total']) ?></strong></td>
                    </tr>
                </tfoot>

            </table>

            </div>

            <p class="firma solo-imprimir">
                Recibí: ____________________________ &nbsp;&nbsp; Entregó: ____________________________
            </p>

            <div class="barra-pago no-imprimir">
                <a href="pagos.php" class="btn-secundario">Volver</a>
                <button type="button" class="btn-principal" onclick="window.print()">Imprimir comprobante</button>
            </div>

        </div>

    <?php endif; ?>


    <!-- ================= HISTORIAL ================= -->

    <div class="agenda-titulo historial no-imprimir">
        <div>
            <span>HISTORIAL</span>
            <h2>Pagos registrados</h2>
        </div>
    </div>

    <div class="tabla-contenedor no-imprimir">

        <table>

            <thead>
                <tr>
                    <th>N.º</th>
                    <th>Fecha del pago</th>
                    <th>Manicurista</th>
                    <th>Periodo</th>
                    <th>Servicios</th>
                    <th>Total</th>
                    <th></th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$historial): ?>
                <tr><td colspan="7" class="sin-datos">Todavía no hay pagos registrados.</td></tr>
            <?php endif; ?>

            <?php foreach ($historial as $h): ?>
                <tr>
                    <td>#<?= $h['id_pago'] ?></td>
                    <td><?= date('d/m/Y', strtotime($h['fecha_pago'])) ?></td>
                    <td><?= htmlspecialchars($h['manicurista']) ?></td>
                    <td><?= fecha_corta($h['desde']) ?> – <?= fecha_corta($h['hasta']) ?></td>
                    <td><?= $h['servicios'] ?></td>
                    <td><strong><?= pesos($h['total']) ?></strong></td>
                    <td><a class="btn-pequeno" href="pagos.php?pago=<?= $h['id_pago'] ?>">Ver</a></td>
                </tr>
            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
