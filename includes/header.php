<?php

if (!isset($titulo)) {
    $titulo = "Nails Spa Belleza";
}

// Cuánto hay que "subir" para llegar a la raíz del proyecto.
// index.php no la define (queda vacía); las de paginas/ y super/ ponen "../".
// Así el proyecto funciona en cualquier carpeta de htdocs.
if (!isset($base)) {
    $base = "";
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

    <!-- Ícono de la pestaña del navegador (y del acceso directo en el celular) -->
    <link rel="icon" type="image/png" sizes="64x64" href="<?= $base ?>assets/img/favicon.png">
    <link rel="icon" href="<?= $base ?>assets/img/favicon.ico" sizes="any">
    <link rel="apple-touch-icon" href="<?= $base ?>assets/img/apple-touch-icon.png">

    <!-- Letras: Playfair Display (títulos) y Poppins (texto).
         Sin internet se usan Georgia y Arial, y la página se ve bien igual. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,700;1,700&family=Poppins:wght@400;500;600;700&display=swap"
    >

    <!-- ?v= cambia cada vez que se guarda el archivo: así el navegador
         no se queda con la versión vieja del CSS en la memoria (caché). -->
    <link
        rel="stylesheet"
        href="<?= $base ?>assets/css/estilo.css?v=<?= filemtime(__DIR__ . '/../assets/css/estilo.css') ?>"
    >

</head>

<body>

<header class="navbar">

    <a
    href="<?= $base ?>index.php"
    class="logo"
>
    <img
        src="<?= $base ?>assets/img/logo.png"
        alt="Nails Spa Belleza"
    >

    <span>Nails Spa Belleza</span>
</a>


    <nav class="menu">

    <a href="<?= $base ?>index.php">Inicio</a>

    <a href="<?= $base ?>paginas/reservar.php">Reservar</a>

    <?php // No hay enlace "Ingresar" público: el personal entra por /super/ ?>

</nav>


<<<<<<< HEAD
    
=======
    <a
        href="<?= $base ?>paginas/reservar.php"
        class="btn-reserva"
    >
        + Nueva cita
    </a>
>>>>>>> profe

</header>

<?php if (isset($_SESSION['usuario_id'])): ?>

    <?php // Barra de la zona del personal (solo con sesión). El menú cambia según el rol. ?>

    <nav class="barra-panel">

        <span class="barra-panel-quien">
            <?= htmlspecialchars($_SESSION['usuario_nombre']) ?>
            · <?= $_SESSION['usuario_rol'] === 'administrador' ? 'Administrador' : 'Manicurista' ?>
        </span>

        <a href="<?= $base ?>super/agenda.php">
            <?= $_SESSION['usuario_rol'] === 'administrador' ? 'Agenda' : 'Mi agenda' ?>
        </a>

        <a href="<?= $base ?>super/bloqueos.php">
            <?= $_SESSION['usuario_rol'] === 'administrador' ? 'Bloqueos' : 'Mis bloqueos' ?>
        </a>

        <?php if ($_SESSION['usuario_rol'] === 'administrador'): ?>

            <a href="<?= $base ?>super/cita_nueva.php">Cita en el salón</a>

            <a href="<?= $base ?>super/pagos.php">Pagos</a>

            <a href="<?= $base ?>super/horarios.php">Horarios</a>

            <a href="<?= $base ?>super/clientes.php">Clientes</a>

            <a href="<?= $base ?>super/servicios.php">Servicios</a>

            <a href="<?= $base ?>super/manicuristas.php">Manicuristas</a>

            <a href="<?= $base ?>super/usuarios.php">Usuarios</a>

        <?php endif; ?>

        <?php if ($_SESSION['usuario_rol'] !== 'administrador'): ?>

            <a href="<?= $base ?>super/pagos.php">Mis pagos</a>

        <?php endif; ?>

        <a href="<?= $base ?>super/clave.php">Mi contraseña</a>

        <a href="<?= $base ?>super/salir.php">Salir</a>

    </nav>

<?php endif; ?>

<main>