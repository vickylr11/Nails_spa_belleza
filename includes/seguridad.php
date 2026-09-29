<?php

// ==========================================================
// SEGURIDAD DE LA ZONA DEL PERSONAL (carpeta super/)
// Se incluye al principio de CADA archivo de super/,
// menos login.php. (session_start() ya se hizo en conexion.php)
//
// Dos roles:
//   administrador -> todo: agenda de todas, horarios, clientes, todos los estados.
//   manicurista   -> solo SU agenda y marcar sus citas como
//                    Confirmada, Completada o No asistió.
// ==========================================================

// Si nadie ha iniciado sesión, al login. El exit es obligatorio:
// sin él, el resto de la página SÍ se ejecuta aunque se redirija.
if (!isset($_SESSION['usuario_id'])) {

    header("Location: login.php");

    exit;
}


// Se vuelve a leer el usuario de la base EN CADA PÁGINA.
// Así, si el administrador lo desactiva o le cambia el rol,
// el cambio vale de inmediato (no cuando la persona cierre sesión).
$stmt_sesion = $conexion->prepare("
    SELECT nombre, rol, id_manicurista, activo
    FROM usuarios
    WHERE id_usuario = ?
");
$stmt_sesion->bind_param("i", $_SESSION['usuario_id']);
$stmt_sesion->execute();
$usuario_actual = $stmt_sesion->get_result()->fetch_assoc();

if (!$usuario_actual || !$usuario_actual['activo']) {

    $_SESSION = [];

    session_destroy();

    header("Location: login.php");

    exit;
}

$_SESSION['usuario_nombre'] = $usuario_actual['nombre'];
$_SESSION['usuario_rol'] = $usuario_actual['rol'];
$_SESSION['id_manicurista'] = $usuario_actual['id_manicurista'];


function es_admin()
{
    return $_SESSION['usuario_rol'] === 'administrador';
}


// Se llama en las páginas que son solo del administrador.
function exigir_admin()
{
    if (!es_admin()) {

        header(
            "Location: agenda.php?mensaje=" .
            urlencode("Esa sección es solo para el administrador.")
        );

        exit;
    }
}
