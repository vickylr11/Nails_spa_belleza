<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";

exigir_admin();


$ids = $_POST['id'] ?? [];

$aperturas = $_POST['apertura'] ?? [];

$cierres = $_POST['cierre'] ?? [];

$disponibles = $_POST['disponible'] ?? [];


// ==========================================
// PASO 1: REVISAR LOS 7 DÍAS SIN GUARDAR NADA
// Si se guardara mientras se revisa y el jueves
// estuviera mal, lunes a miércoles ya quedarían
// guardados. Por eso primero se revisa todo.
// ==========================================

$dias = [];

foreach ($ids as $indice => $id) {

    $id = intval($id);

    $apertura = $aperturas[$indice] ?? '';

    $cierre = $cierres[$indice] ?? '';

    $disponible = isset($disponibles[$id]) ? 1 : 0;

    if ($disponible) {

        if ($apertura === '' || $cierre === '' || $apertura >= $cierre) {

            header(
                "Location: horarios.php?error=" .
                urlencode("Revisa las horas: en cada día que se atiende, la apertura debe ser antes del cierre. No se guardó nada.")
            );

            exit;
        }

    } else {

        $apertura = null;

        $cierre = null;
    }

    $dias[] = [$apertura, $cierre, $disponible, $id];
}


// ==========================================
// PASO 2: TODO ESTÁ BIEN, AHORA SÍ SE GUARDA
// ==========================================

$stmt = $conexion->prepare("
    UPDATE horarios
    SET
        hora_apertura = ?,
        hora_cierre = ?,
        disponible = ?
    WHERE id_horario = ?
");

foreach ($dias as [$apertura, $cierre, $disponible, $id]) {

    $stmt->bind_param("ssii", $apertura, $cierre, $disponible, $id);

    $stmt->execute();
}


header(
    "Location: horarios.php?mensaje=" .
    urlencode("Los horarios fueron actualizados correctamente.")
);

exit;

?>