<?php

require_once "../conexion/conexion.php";

$base = "../";
$titulo = "Ingresar - Nails Spa Belleza";
$error = "";

// Si ya inició sesión, no tiene que volver a ingresar.
if (isset($_SESSION['usuario_id'])) {
    header("Location: agenda.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $correo = trim($_POST['correo'] ?? '');
    $clave = $_POST['clave'] ?? '';

    // La clave nunca se guarda tal cual: se guarda SHA-256 de (sal + clave).
    $stmt = $conexion->prepare("
        SELECT id_usuario, nombre, rol, id_manicurista
        FROM usuarios
        WHERE correo = ?
        AND clave = SHA2(CONCAT(sal, ?), 256)
        AND activo = 1
    ");

    $stmt->bind_param("ss", $correo, $clave);
    $stmt->execute();

    $usuario = $stmt->get_result()->fetch_assoc();

    if ($usuario) {

        session_regenerate_id(true);

        $_SESSION['usuario_id'] = $usuario['id_usuario'];
        $_SESSION['usuario_nombre'] = $usuario['nombre'];
        $_SESSION['usuario_rol'] = $usuario['rol'];

        // Si es manicurista, a cuál manicurista corresponde (para ver solo SU agenda).
        $_SESSION['id_manicurista'] = $usuario['id_manicurista'];

        header("Location: agenda.php");
        exit;
    }

    $error = "Correo o contraseña incorrectos.";
}

require_once "../includes/header.php";

?>

<section class="pagina-header">
    <div>
        <span>ZONA DEL PERSONAL</span>
        <h1>Ingresar</h1>
        <p>Solo para el personal del salón.</p>
    </div>
</section>

<section class="form-section">

    <div class="form-card">

        <?php if ($error): ?>
            <div class="alerta error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">

            <label>Correo</label>
            <input type="email" name="correo" required>

            <label>Contraseña</label>
            <input type="password" name="clave" required>

            <button type="submit" class="btn-principal btn-completo">
                Ingresar
            </button>

        </form>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
