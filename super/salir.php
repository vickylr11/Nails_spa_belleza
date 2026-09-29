<?php

require_once "../conexion/conexion.php";

$_SESSION = [];

session_destroy();

header("Location: login.php");

exit;
