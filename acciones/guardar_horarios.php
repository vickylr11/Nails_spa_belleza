<?php

require_once "../conexion/conexion.php";


$ids = $_POST['id'] ?? [];

$aperturas = $_POST['apertura'] ?? [];

$cierres = $_POST['cierre'] ?? [];

$disponibles = $_POST['disponible'] ?? [];


foreach ($ids as $indice => $id) {

    $id = intval($id);

    $apertura = $aperturas[$indice] ?? null;

    $cierre = $cierres[$indice] ?? null;

    $disponible = isset(
        $disponibles[$id]
    ) ? 1 : 0;


    if (!$disponible) {

        $apertura = null;

        $cierre = null;

    }


    $stmt = $conexion->prepare("
        UPDATE horarios
        SET
            hora_apertura = ?,
            hora_cierre = ?,
            disponible = ?
        WHERE id_horario = ?
    ");

    $stmt->bind_param(
        "ssii",
        $apertura,
        $cierre,
        $disponible,
        $id
    );

    $stmt->execute();

}


header(
    "Location: ../paginas/horarios.php?mensaje=" .
    urlencode("Los horarios fueron actualizados correctamente.")
);

exit;

?>