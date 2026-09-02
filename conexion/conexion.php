<?php

$servidor = "localhost";
$usuario = "root";
$password = "";
$base_datos = "nails_spa";

$conexion = new mysqli(
    $servidor,
    $usuario,
    $password,
    $base_datos
);

if ($conexion->connect_error) {
    die("Error de conexión con la base de datos: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");

?>