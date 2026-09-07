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
        href="/NAILS_SPA_BELLEZA/css/estilo.css"
    >

</head>

<body>

<header class="navbar">

    <a
        href="/NAILS_SPA_BELLEZA/index.php"
        class="logo"
    >
        💅
        <span>Nails Spa Belleza</span>
    </a>


    <nav class="menu">

    <a href="/NAILS_SPA_BELLEZA/">Inicio</a>

    <a href="/NAILS_SPA_BELLEZA/paginas/reservar.php">
        Reservar
    </a>

    <a href="/NAILS_SPA_BELLEZA/paginas/agenda.php">
        Agenda
    </a>

    <a href="/NAILS_SPA_BELLEZA/paginas/horarios.php">
        Horarios
    </a>

    <a href="/NAILS_SPA_BELLEZA/paginas/clientes.php">
        Clientes
    </a>

</nav>

<style>
.navbar .menu {
    display: flex;
    align-items: center;
    gap: 10px;
}

.navbar .menu a {
    display: inline-block;
    padding: 12px 18px;
    border-radius: 25px;
    background: #fff0f6;
    color: #b7195b;
    text-decoration: none;
    font-size: 14px;
    font-weight: 700;
    transition: .2s;
}

.navbar .menu a:hover {
    background: #b7195b;
    color: #fff;
}
</style>

    <a
        href="/NAILS_SPA_BELLEZA/paginas/reservar.php"
        class="btn-reserva"
    >
        + Nueva cita
    </a>

</header>

<main>