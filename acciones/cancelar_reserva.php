<?php

require_once "../conexion/conexion.php";


$id = intval($_GET['id'] ?? 0);


if ($id <= 0) {

    header(
        "Location: ../paginas/agenda.php"
    );

    exit;

}


$stmt = $conexion->prepare("
    UPDATE reservas
    SET estado = 'Cancelada'
    WHERE id_reserva = ?
");

$stmt->bind_param(
    "i",
    $id
);

$stmt->execute();


header(
    "Location: ../paginas/agenda.php?mensaje=" .
    urlencode("La reserva fue cancelada.")
);

exit;

?>