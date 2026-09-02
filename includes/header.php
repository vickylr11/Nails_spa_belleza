<?php
if (!isset($titulo)) {
    $titulo = "Nails Spa Belleza";
}
?>

<!DOCTYPE html>
<html lang="es">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title><?= htmlspecialchars($titulo) ?></title>

    <link rel="stylesheet" href="css/estilo.css">
</head>

<body>
<header class="navbar">
    <nav class="menu">

    <a href="index.php">Inicio</a>

    <a href="paginas/reservar.php">Reservar</a>

    <a href="paginas/agenda.php">Agenda</a>

    <a href="paginas/horarios.php">Horarios</a>

    <a href="paginas/clientes.php">Clientes</a>

</nav>

<a href="paginas/reservar.php" class="btn-nueva-cita">
    + Nueva cita
</a>

    <a
        href="/NAILS-SPA-BELLEZA/index.php"
        class="logo"
    >
        💅
        <span>Nails Spa Belleza</span>
    </a>

</header>

<main>

<main>
    