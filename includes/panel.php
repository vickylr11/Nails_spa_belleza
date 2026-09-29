<?php

// ==========================================================
// AYUDAS PARA LOS FORMULARIOS DEL ADMINISTRADOR
// (servicios, manicuristas, usuarios)
// ==========================================================


// Cuántos caracteres tiene un texto (las tildes y la ñ cuentan como 1).
// Usa mbstring si el servidor la tiene (XAMPP sí); si no, cuenta con una expresión regular.
function mb_strlen_seguro($texto)
{
    if (function_exists('mb_strlen')) {
        return mb_strlen($texto, 'UTF-8');
    }

    return preg_match_all('/./us', $texto);
}


// Vuelve al formulario con un error, SIN perder lo que se escribió:
// lo escrito se guarda un momento en la sesión y el formulario lo vuelve a mostrar.
function volver_con_error($pagina, $mensaje, $id = 0)
{
    $_SESSION['viejo'] = $_POST;

    $url = $pagina . "?error=" . urlencode($mensaje);

    if ($id > 0) {
        $url .= "&id=" . $id;
    }

    header("Location: " . $url);

    exit;
}


// Valor que se muestra en un campo del formulario:
// lo que se escribió antes del error, o lo que está guardado, o el valor por defecto.
function valor($campo, $editar, $viejo, $defecto = '')
{
    if ($viejo !== null) {
        return $viejo[$campo] ?? $defecto;
    }

    return $editar[$campo] ?? $defecto;
}


// Vuelve a la lista con un mensaje de éxito.
function volver_con_mensaje($pagina, $mensaje)
{
    header("Location: " . $pagina . "?mensaje=" . urlencode($mensaje));

    exit;
}


// Muestra los mensajes (?mensaje= en verde, ?error= en rojo).
function mostrar_mensajes()
{
    if (isset($_GET['mensaje'])) {
        echo '<div class="alerta exito">' . htmlspecialchars($_GET['mensaje']) . '</div>';
    }

    if (isset($_GET['error'])) {
        echo '<div class="alerta error">' . htmlspecialchars($_GET['error']) . '</div>';
    }
}


// Sube la foto de un servicio.
// Devuelve la ruta para guardar en la base, o un texto que empieza por "ERROR: ".
function subir_imagen($archivo)
{
    if ($archivo['error'] === UPLOAD_ERR_INI_SIZE || $archivo['error'] === UPLOAD_ERR_FORM_SIZE) {
        return "ERROR: La foto pesa demasiado (máximo 5 MB).";
    }

    if ($archivo['error'] !== UPLOAD_ERR_OK) {
        return "ERROR: No se pudo subir la foto. Intenta de nuevo.";
    }

    if ($archivo['size'] > 5 * 1024 * 1024) {
        return "ERROR: La foto pesa demasiado (máximo 5 MB).";
    }

    // getimagesize() abre el archivo y mira si de verdad es una imagen.
    // No se confía en el nombre: un "foto.jpg" puede ser un programa disfrazado.
    $info = getimagesize($archivo['tmp_name']);

    $tipos = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if ($info === false || !isset($tipos[$info['mime']])) {
        return "ERROR: El archivo no es una foto JPG, PNG o WEBP.";
    }

    $carpeta = __DIR__ . "/../assets/img/servicios/";

    if (!is_writable($carpeta)) {
        return "ERROR: La carpeta assets/img/servicios no deja guardar archivos. " .
            "En la Terminal, dentro del proyecto: chmod 777 assets/img/servicios";
    }

    // Nombre nuevo al azar: nunca se usa el nombre que trae el archivo.
    $nombre = "servicio_" . bin2hex(random_bytes(6)) . "." . $tipos[$info['mime']];

    if (!move_uploaded_file($archivo['tmp_name'], $carpeta . $nombre)) {
        return "ERROR: No se pudo guardar la foto en el servidor.";
    }

    return "assets/img/servicios/" . $nombre;
}


// Borra una foto que se subió desde el panel (las de assets/img/servicios/).
// Las fotos originales del proyecto (assets/img/foto3.png...) nunca se borran.
function borrar_imagen_subida($ruta)
{
    if ($ruta && str_starts_with($ruta, "assets/img/servicios/")) {

        $archivo = __DIR__ . "/../" . $ruta;

        if (is_file($archivo)) {
            unlink($archivo);
        }
    }
}
