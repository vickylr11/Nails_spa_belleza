<?php

session_start();

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

// Hora de Colombia para PHP y para MySQL, sin importar cómo esté configurado
// el computador. Si no, "esa hora ya pasó" o "las citas de hoy" pueden quedar
// corridas 5 horas (por ejemplo, en un servidor que trabaja en hora UTC).
date_default_timezone_set('America/Bogota');
$conexion->query("SET time_zone = '-05:00'");

?>