
<?php
session_start();

require_once "../conexion/conexion.php";

// Obtener manicuristas activas
$sql = "SELECT * FROM manicuristas WHERE estado = 1";
$resultado = $conexion->query($sql);

// Procesar selección de manicurista
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $id_manicurista = intval($_POST["id_manicurista"]);

    $stmt = $conexion->prepare(
        "SELECT id_manicurista
         FROM manicuristas
         WHERE id_manicurista = ? AND estado = 1"
    );

    $stmt->bind_param("i", $id_manicurista);
    $stmt->execute();

    $validacion = $stmt->get_result();

    if ($validacion->num_rows > 0) {

        $_SESSION["id_manicurista"] = $id_manicurista;

        header("Location: reservar.php");
        exit();
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Elige tu manicurista | Nails Spa Belleza</title>

    <link rel="stylesheet" href="../css/estilo.css">
    <link rel="stylesheet" href="../css/manicurista.css">
</head>

<body>

    <!-- BARRA DE NAVEGACION -->

    <header class="navbar">

        <div class="logo">
            💅 Nails Spa Belleza
        </div>

        <nav>
            <a href="../index.php">Inicio</a>
            <a href="../index.php#servicios">Servicios</a>
        </nav>

        <a href="../index.php#reserva" class="btn-reserva">
            Reservar cita
        </a>

    </header>


    <!-- CONTENIDO PRINCIPAL -->

    <main class="contenedor-manicuristas">

        <!-- TITULO -->

        <div class="titulo-manicuristas">

            <h1>Elige tu manicurista</h1>

            <p>
                Selecciona a la profesional que realizará tu servicio.
            </p>

        </div>


        <!-- TARJETAS DE MANICURISTAS -->

        <div class="grid-manicuristas">

            <?php while ($manicurista = $resultado->fetch_assoc()): ?>

                <form method="POST" class="form-manicurista">

                    <!-- ID DE LA MANICURISTA -->

                    <input
                        type="hidden"
                        name="id_manicurista"
                        value="<?= htmlspecialchars($manicurista['id_manicurista']) ?>"
                    >

                    <!-- TARJETA COMPLETA -->

                    <button
                        type="submit"
                        class="tarjeta-manicurista"
                    >

                        <!-- FOTOGRAFIA -->

                        <img
                            src="../img/ft_manicuristas/<?= htmlspecialchars($manicurista['foto']) ?>"
                            alt="<?= htmlspecialchars($manicurista['nombre']) ?>"
                            class="foto-manicurista"
                        >

                        <!-- NOMBRE DESDE MYSQL -->

                        <h3>
                            <?= htmlspecialchars($manicurista['nombre']) ?>
                        </h3>

                        <!-- DISPONIBILIDAD -->

                        <span class="disponible">
                            Disponible
                        </span>

                        <!-- BOTON -->

                        <span class="boton-seleccionar">
                            Agendar con esta manicurista
                        </span>

                    </button>

                </form>

            <?php endwhile; ?>

        </div>

    </main>

</body>

</html>