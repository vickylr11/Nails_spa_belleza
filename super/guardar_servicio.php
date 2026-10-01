<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/panel.php";

exigir_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: servicios.php");
    exit;
}

$accion = $_POST['accion'] ?? '';

$id = intval($_POST['id_servicio'] ?? 0);


// ==========================================
// ACTIVAR / DESACTIVAR
// Un servicio nunca se borra: tiene citas en su historia.
// Desactivado, deja de aparecer en la página y en el formulario de reserva;
// las citas que ya tiene agendadas siguen igual.
// ==========================================

if ($accion === 'cambiar_activo') {

    $stmt = $conexion->prepare("
        UPDATE servicios
        SET activo = 1 - activo
        WHERE id_servicio = ?
    ");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    volver_con_mensaje("servicios.php", "Listo: se cambió el estado del servicio.");
}


if ($accion !== 'guardar') {
    header("Location: servicios.php");
    exit;
}


// ==========================================
// RECIBIR Y VALIDAR (todo antes de guardar nada)
// ==========================================

$nombre = trim($_POST['nombre'] ?? '');

$descripcion = trim($_POST['descripcion'] ?? '');

$precio = intval($_POST['precio'] ?? 0);

$duracion = intval($_POST['duracion'] ?? 0);


if (mb_strlen_seguro($nombre) < 3 || mb_strlen_seguro($nombre) > 100) {
    volver_con_error("servicios.php", "El nombre debe tener entre 3 y 100 caracteres.", $id);
}

if (mb_strlen_seguro($descripcion) > 500) {
    volver_con_error("servicios.php", "La descripción puede tener máximo 500 caracteres.", $id);
}

if ($precio < 1000 || $precio > 10000000) {
    volver_con_error("servicios.php", "El precio debe estar entre $1.000 y $10.000.000.", $id);
}

// Múltiplo de 5 y entre 15 minutos y 8 horas.
if ($duracion < 15 || $duracion > 480 || $duracion % 5 !== 0) {
    volver_con_error("servicios.php", "La duración debe estar entre 15 y 480 minutos, de 5 en 5.", $id);
}

// No puede haber dos servicios con el mismo nombre.
$stmt = $conexion->prepare("
    SELECT id_servicio
    FROM servicios
    WHERE nombre = ?
    AND id_servicio <> ?
");
$stmt->bind_param("si", $nombre, $id);
$stmt->execute();

if ($stmt->get_result()->num_rows > 0) {
    volver_con_error("servicios.php", "Ya existe un servicio con ese nombre.", $id);
}

// Si se edita, el servicio debe existir.
$anterior = null;

if ($id > 0) {

    $stmt = $conexion->prepare("SELECT imagen FROM servicios WHERE id_servicio = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    $anterior = $stmt->get_result()->fetch_assoc();

    if (!$anterior) {
        volver_con_mensaje("servicios.php", "Ese servicio no existe.");
    }
}


// ==========================================
// FOTO (solo si se escogió una)
// Se sube al final, cuando todo lo demás ya está bien:
// así no quedan fotos sueltas de formularios con error.
// ==========================================

$imagen = $anterior['imagen'] ?? null;

$foto_nueva = !empty($_FILES['imagen']['name']);

if ($foto_nueva) {

    $resultado = subir_imagen($_FILES['imagen']);

    if (str_starts_with($resultado, "ERROR: ")) {
        volver_con_error("servicios.php", substr($resultado, 7), $id);
    }

    $imagen = $resultado;
}


// ==========================================
// GUARDAR
// ==========================================

if ($id > 0) {

    $stmt = $conexion->prepare("
        UPDATE servicios
        SET nombre = ?, descripcion = ?, precio = ?, duracion = ?, imagen = ?
        WHERE id_servicio = ?
    ");
    $stmt->bind_param("ssiisi", $nombre, $descripcion, $precio, $duracion, $imagen, $id);
    $stmt->execute();

    // La foto vieja ya no se usa: se borra (solo si se había subido desde el panel).
    if ($foto_nueva) {
        borrar_imagen_subida($anterior['imagen']);
    }

    volver_con_mensaje("servicios.php", "Servicio actualizado.");

} else {

    $stmt = $conexion->prepare("
        INSERT INTO servicios (nombre, descripcion, precio, duracion, imagen)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssiis", $nombre, $descripcion, $precio, $duracion, $imagen);
    $stmt->execute();

    // Un servicio nuevo queda asignado a TODAS las manicuristas activas
    // (si no, nadie lo haría y no se podría reservar). A la que no lo haga,
    // se le quita en Manicuristas > Editar.
    $id_nuevo = $conexion->insert_id;

    $stmt = $conexion->prepare("
        INSERT INTO manicurista_servicio (id_manicurista, id_servicio)
        SELECT id_manicurista, ? FROM manicuristas WHERE activa = 1
    ");
    $stmt->bind_param("i", $id_nuevo);
    $stmt->execute();

    volver_con_mensaje("servicios.php", "Servicio creado y asignado a todas las manicuristas activas. " .
        "A la que no lo haga, quíteselo en Manicuristas > Editar.");
}
