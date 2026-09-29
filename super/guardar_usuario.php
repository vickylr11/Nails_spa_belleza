<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

exigir_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: usuarios.php");
    exit;
}

$accion = $_POST['accion'] ?? '';

$id = intval($_POST['id_usuario'] ?? 0);

$yo = intval($_SESSION['usuario_id']);


// Cuántos administradores activos hay, sin contar a $excepto.
function otros_admins_activos($conexion, $excepto)
{
    $stmt = $conexion->prepare("
        SELECT COUNT(*) AS n
        FROM usuarios
        WHERE rol = 'administrador' AND activo = 1 AND id_usuario <> ?
    ");
    $stmt->bind_param("i", $excepto);
    $stmt->execute();

    return intval($stmt->get_result()->fetch_assoc()['n']);
}


// ==========================================
// DAR / QUITAR ACCESO
// Un usuario nunca se borra: se le quita el acceso (activo = 0).
// Como seguridad.php revisa la base en cada página, si tenía la
// sesión abierta queda por fuera en su siguiente clic.
// ==========================================

if ($accion === 'cambiar_activo') {

    if ($id === $yo) {
        header("Location: usuarios.php?error=" . urlencode("No puedes quitarte el acceso a ti mismo."));
        exit;
    }

    $stmt = $conexion->prepare("SELECT nombre, rol, activo FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $u = $stmt->get_result()->fetch_assoc();

    if (!$u) {
        volver_con_mensaje("usuarios.php", "Ese usuario no existe.");
    }

    if ($u['activo'] && $u['rol'] === 'administrador' && otros_admins_activos($conexion, $id) === 0) {
        header("Location: usuarios.php?error=" . urlencode("Debe quedar al menos un administrador con acceso."));
        exit;
    }

    $stmt = $conexion->prepare("UPDATE usuarios SET activo = 1 - activo WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    volver_con_mensaje(
        "usuarios.php",
        $u['activo'] ? $u['nombre'] . " ya no puede entrar." : $u['nombre'] . " puede entrar de nuevo."
    );
}


if ($accion !== 'guardar') {
    header("Location: usuarios.php");
    exit;
}


// ==========================================
// RECIBIR Y VALIDAR (todo antes de guardar)
// ==========================================

$nombre = trim($_POST['nombre'] ?? '');

$correo = strtolower(trim($_POST['correo'] ?? ''));

$rol = $_POST['rol'] ?? '';

$id_manicurista = intval($_POST['id_manicurista'] ?? 0);

$clave = $_POST['clave'] ?? '';


if (mb_strlen_seguro($nombre) < 3 || mb_strlen_seguro($nombre) > 100) {
    volver_con_error("usuarios.php", "El nombre debe tener entre 3 y 100 caracteres.", $id);
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 100) {
    volver_con_error("usuarios.php", "Escribe un correo válido.", $id);
}

if (!in_array($rol, ['administrador', 'manicurista'], true)) {
    volver_con_error("usuarios.php", "Rol no válido.", $id);
}

// Si se edita, debe existir.
$anterior = null;

if ($id > 0) {

    $stmt = $conexion->prepare("SELECT rol, activo FROM usuarios WHERE id_usuario = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $anterior = $stmt->get_result()->fetch_assoc();

    if (!$anterior) {
        volver_con_mensaje("usuarios.php", "Ese usuario no existe.");
    }
}

// Nadie se quita a sí mismo el rol de administrador
// (se quedaría por fuera de esta misma página).
if ($id === $yo && $rol !== 'administrador') {
    volver_con_error("usuarios.php", "No puedes quitarte el rol de administrador.", $id);
}

// Si un administrador activo pasa a manicurista, debe quedar otro administrador.
if ($anterior && $anterior['rol'] === 'administrador' && $anterior['activo']
    && $rol !== 'administrador' && otros_admins_activos($conexion, $id) === 0) {
    volver_con_error("usuarios.php", "Debe quedar al menos un administrador con acceso.", $id);
}

// Correo único.
$stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE correo = ? AND id_usuario <> ?");
$stmt->bind_param("si", $correo, $id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    volver_con_error("usuarios.php", "Ya hay un usuario con ese correo.", $id);
}

// Manicurista: debe escoger cuál es, y esa no puede tener otra cuenta.
if ($rol === 'manicurista') {

    $stmt = $conexion->prepare("SELECT id_manicurista FROM manicuristas WHERE id_manicurista = ?");
    $stmt->bind_param("i", $id_manicurista);
    $stmt->execute();

    if ($stmt->get_result()->num_rows === 0) {
        volver_con_error("usuarios.php", "Escoge cuál manicurista es.", $id);
    }

    $stmt = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_manicurista = ? AND id_usuario <> ?");
    $stmt->bind_param("ii", $id_manicurista, $id);
    $stmt->execute();

    if ($stmt->get_result()->num_rows > 0) {
        volver_con_error("usuarios.php", "Esa manicurista ya tiene cuenta.", $id);
    }

} else {

    // El administrador no está ligado a ninguna manicurista.
    $id_manicurista = null;
}

// Contraseña: obligatoria al crear; al editar, solo si se escribió una.
if ($id === 0 || $clave !== '') {

    if (strlen($clave) < 8) {
        volver_con_error("usuarios.php", "La contraseña debe tener mínimo 8 caracteres.", $id);
    }
}


// ==========================================
// GUARDAR
// ==========================================

if ($id > 0) {

    $stmt = $conexion->prepare("
        UPDATE usuarios
        SET nombre = ?, correo = ?, rol = ?, id_manicurista = ?
        WHERE id_usuario = ?
    ");
    $stmt->bind_param("sssii", $nombre, $correo, $rol, $id_manicurista, $id);
    $stmt->execute();

    if ($clave !== '') {

        // Sal nueva al azar cada vez que cambia la clave.
        $sal = bin2hex(random_bytes(8));

        $stmt = $conexion->prepare("
            UPDATE usuarios
            SET sal = ?, clave = SHA2(CONCAT(?, ?), 256)
            WHERE id_usuario = ?
        ");
        $stmt->bind_param("sssi", $sal, $sal, $clave, $id);
        $stmt->execute();
    }

    volver_con_mensaje("usuarios.php", "Usuario actualizado.");

} else {

    $sal = bin2hex(random_bytes(8));

    $stmt = $conexion->prepare("
        INSERT INTO usuarios (nombre, correo, sal, clave, rol, id_manicurista)
        VALUES (?, ?, ?, SHA2(CONCAT(?, ?), 256), ?, ?)
    ");
    $stmt->bind_param("ssssssi", $nombre, $correo, $sal, $sal, $clave, $rol, $id_manicurista);
    $stmt->execute();

    volver_con_mensaje("usuarios.php", "Usuario creado: $correo ya puede entrar por /super/.");
}
