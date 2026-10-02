<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";

exigir_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: pagos.php");
    exit;
}

$id_manicurista = intval($_POST['id_manicurista'] ?? 0);

$desde = $_POST['desde'] ?? '';

$hasta = $_POST['hasta'] ?? '';

$total_visto = floatval($_POST['total_visto'] ?? -1);


function volver($mensaje, $id_manicurista, $desde, $hasta)
{
    header(
        "Location: pagos.php?manicurista=$id_manicurista&desde=" . urlencode($desde) .
        "&hasta=" . urlencode($hasta) . "&error=" . urlencode($mensaje)
    );
    exit;
}

if (strtotime($desde) === false || strtotime($hasta) === false || $desde > $hasta) {
    volver("El periodo no es válido.", $id_manicurista, $desde, $hasta);
}

$desde = date('Y-m-d', strtotime($desde));
$hasta = date('Y-m-d', strtotime($hasta));


// ==========================================
// TODO EN UNA TRANSACCIÓN
// Se bloquea la manicurista (FOR UPDATE): si dos personas tocan "Registrar pago"
// al mismo tiempo, la segunda espera y ya no encuentra servicios sin pagar.
// ==========================================

$conexion->begin_transaction();

$stmt = $conexion->prepare("SELECT nombre, porcentaje FROM manicuristas WHERE id_manicurista = ? FOR UPDATE");
$stmt->bind_param("i", $id_manicurista);
$stmt->execute();

$manicurista = $stmt->get_result()->fetch_assoc();

if (!$manicurista) {
    $conexion->rollback();
    header("Location: pagos.php?error=" . urlencode("Esa manicurista no existe."));
    exit;
}

// El servidor vuelve a calcular: NUNCA se usa un total que venga del formulario.
$stmt = $conexion->prepare("
    SELECT COUNT(*) AS servicios,
        COALESCE(SUM(ROUND(r.precio * m.porcentaje / 100, 0)), 0) AS total
    FROM reservas r
    INNER JOIN manicuristas m ON m.id_manicurista = r.id_manicurista
    WHERE r.id_manicurista = ?
    AND r.estado = 'Completada'
    AND r.id_pago IS NULL
    AND r.fecha BETWEEN ? AND ?
");
$stmt->bind_param("iss", $id_manicurista, $desde, $hasta);
$stmt->execute();

$calculo = $stmt->get_result()->fetch_assoc();

if (intval($calculo['servicios']) === 0) {
    $conexion->rollback();
    volver("No hay servicios completados sin pagar en ese periodo.", $id_manicurista, $desde, $hasta);
}

// Si el total cambió mientras se revisaba (otra cita pasó a Completada,
// o cambió el porcentaje), no se registra: hay que mirar de nuevo.
if (abs(floatval($calculo['total']) - $total_visto) > 0.5) {
    $conexion->rollback();
    volver(
        "El total cambió mientras revisabas (ahora es $" . number_format($calculo['total'], 0, ',', '.') .
        "). Revisa la lista y vuelve a registrar el pago.",
        $id_manicurista, $desde, $hasta
    );
}

$servicios = intval($calculo['servicios']);
$total = floatval($calculo['total']);
$id_usuario = intval($_SESSION['usuario_id']);

$stmt = $conexion->prepare("
    INSERT INTO pagos (id_manicurista, desde, hasta, servicios, total, id_usuario)
    VALUES (?, ?, ?, ?, ?, ?)
");
$stmt->bind_param("issidi", $id_manicurista, $desde, $hasta, $servicios, $total, $id_usuario);
$stmt->execute();

$id_pago = $conexion->insert_id;

// Cada cita queda marcada con su pago y con lo que se le pagó por ella.
// Si después cambia el porcentaje, lo pagado no cambia.
$stmt = $conexion->prepare("
    UPDATE reservas r
    INNER JOIN manicuristas m ON m.id_manicurista = r.id_manicurista
    SET r.id_pago = ?,
        r.valor_manicurista = ROUND(r.precio * m.porcentaje / 100, 0)
    WHERE r.id_manicurista = ?
    AND r.estado = 'Completada'
    AND r.id_pago IS NULL
    AND r.fecha BETWEEN ? AND ?
");
$stmt->bind_param("iiss", $id_pago, $id_manicurista, $desde, $hasta);
$stmt->execute();

$conexion->commit();

header(
    "Location: pagos.php?pago=$id_pago&mensaje=" .
    urlencode("Pago registrado: $" . number_format($total, 0, ',', '.') . " a " . $manicurista['nombre'] . " por $servicios " . ($servicios === 1 ? "servicio." : "servicios."))
);

exit;
