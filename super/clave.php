<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";

$base = "../";

$titulo = "Cambiar contraseña - Nails Spa Belleza";

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>ZONA DEL PERSONAL</span>

        <h1>Cambiar mi contraseña</h1>

        <p>Cada persona cambia la suya. Mínimo 8 caracteres.</p>

    </div>

</section>


<section class="form-section">

    <div class="form-card">

        <?php if (isset($_GET['error'])): ?>
            <div class="alerta error"><?= htmlspecialchars($_GET['error']) ?></div>
        <?php endif; ?>

        <?php if (isset($_GET['mensaje'])): ?>
            <div class="alerta exito"><?= htmlspecialchars($_GET['mensaje']) ?></div>
        <?php endif; ?>

        <form action="guardar_clave.php" method="POST">

            <label>Contraseña actual</label>
            <input type="password" name="actual" required>

            <label>Contraseña nueva</label>
            <input type="password" name="nueva" minlength="8" required>

            <label>Repetir la contraseña nueva</label>
            <input type="password" name="repetir" minlength="8" required>

            <button type="submit" class="btn-principal btn-completo">
                Guardar
            </button>

        </form>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
