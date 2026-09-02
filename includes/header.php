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

    <link
        rel="stylesheet"
        href="/NAILS-SPA-BELLEZA/css/estilos.css"
    >

</head>

<body>

<header class="navbar">

    <a
        href="/NAILS-SPA-BELLEZA/"
        class="logo"
    >
        💅
        <span>Nails Spa Belleza</span>
    </a>

    <nav>

        <a href="/NAILS-SPA-BELLEZA/">
            Inicio
        </a>

        <a href="/NAILS-SPA-BELLEZA/paginas/reservar.php">
            Reservar
        </a>

        <a href="/NAILS-SPA-BELLEZA/paginas/agenda.php">
            Agenda
        </a>

        <a href="/NAILS-SPA-BELLEZA/paginas/horarios.php">
            Horarios
        </a>

        <a href="/NAILS-SPA-BELLEZA/paginas/clientes.php">
            Clientes
        </a>

    </nav>

    <a
        href="/NAILS-SPA-BELLEZA/paginas/reservar.php"
        class="btn-reserva"
    >
        + Nueva cita
    </a>

</header>

<main>
    