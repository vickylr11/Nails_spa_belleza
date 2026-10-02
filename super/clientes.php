<?php

require_once "../conexion/conexion.php";
require_once "../includes/seguridad.php";
require_once "../includes/fidelidad.php";

exigir_admin();

$base = "../";

$titulo = "Clientes - Nails Spa Belleza";


// ==========================================
// BUSCAR por nombre o celular (opcional)
// ==========================================

$buscar = trim($_GET['buscar'] ?? '');

// Si escribieron un celular, se busca con los mismos 10 dígitos que se guardan.
$buscar_tel = normalizar_telefono($buscar);

// Los % y _ que escriba la persona se toman como texto, no como comodines.
$patron = '%' . addcslashes($buscar, '%_\\') . '%';


// ==========================================
// CLIENTAS CON SU TARJETA
// Los conteos se calculan con SUM(...) sobre sus citas,
// y la cuenta de la tarjeta la hace calcular_tarjeta() (includes/fidelidad.php).
// ==========================================

$stmt = $conexion->prepare("
    SELECT c.id_cliente, c.nombre, c.telefono, c.correo, c.fecha_registro,
        COALESCE(SUM(r.estado = 'Completada' AND r.gratis = 0), 0) AS pagadas,
        COALESCE(SUM(r.estado = 'Completada' AND r.gratis = 1), 0) AS gratis_usadas,
        COALESCE(SUM(r.estado IN ('Pendiente', 'Confirmada') AND r.gratis = 1), 0) AS gratis_reservadas,
        MAX(CASE WHEN r.estado = 'Completada' THEN r.fecha END) AS ultima_visita
    FROM clientes c
    LEFT JOIN reservas r ON r.id_cliente = c.id_cliente
    WHERE ? = '' OR c.nombre LIKE ? OR c.telefono = ?
    GROUP BY c.id_cliente
    -- Primero las que han venido más recientemente; al final las que nunca han venido.
    ORDER BY MAX(CASE WHEN r.estado = 'Completada' THEN r.fecha END) IS NULL,
             MAX(CASE WHEN r.estado = 'Completada' THEN r.fecha END) DESC,
             c.nombre
    LIMIT 200
");
$stmt->bind_param("sss", $buscar, $patron, $buscar_tel);
$stmt->execute();

$clientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

require_once "../includes/header.php";

?>

<section class="pagina-header">

    <div>

        <span>ADMINISTRADOR</span>

        <h1>Clientes</h1>

        <p>Cada clienta se reconoce por su celular. Después de <?= CITAS_PARA_GRATIS ?> citas completadas, la <?= CITAS_PARA_GRATIS + 1 ?>.ª es gratis.</p>

    </div>

</section>


<section class="panel-contenido">

    <div class="filtros">

        <form method="GET">

            <label>Buscar</label>

            <input type="text" name="buscar" placeholder="Nombre o celular"
                   value="<?= htmlspecialchars($buscar) ?>">

            <button type="submit" class="btn-principal">Buscar</button>

            <?php if ($buscar !== ''): ?>
                <a href="clientes.php" class="btn-secundario">Ver todas</a>
            <?php endif; ?>

        </form>

    </div>


    <div class="tabla-contenedor">

        <table>

            <thead>
                <tr>
                    <th>Clienta</th>
                    <th>Celular</th>
                    <th>Citas completadas</th>
                    <th>Tarjeta de fidelidad</th>
                    <th>Última visita</th>
                    <th>Registro</th>
                </tr>
            </thead>

            <tbody>

            <?php if (!$clientes): ?>
                <tr>
                    <td colspan="6" class="sin-datos">
                        <?= $buscar === '' ? 'Todavía no hay clientas registradas.' : 'No se encontró ninguna clienta.' ?>
                    </td>
                </tr>
            <?php endif; ?>

            <?php foreach ($clientes as $c): ?>

                <?php $tarjeta = calcular_tarjeta($c['pagadas'], $c['gratis_usadas'], $c['gratis_reservadas']); ?>

                <tr>

                    <td>
                        <strong><?= htmlspecialchars($c['nombre']) ?></strong>
                        <?php if ($c['correo']): ?>
                            <br><small><?= htmlspecialchars($c['correo']) ?></small>
                        <?php endif; ?>
                    </td>

                    <td><?= htmlspecialchars($c['telefono']) ?></td>

                    <td>
                        <?= intval($c['pagadas']) + intval($c['gratis_usadas']) ?>
                        <?php if ($c['gratis_usadas'] > 0): ?>
                            <br><small>(<?= $c['gratis_usadas'] ?> gratis)</small>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="sellos" title="<?= htmlspecialchars(texto_tarjeta($tarjeta)) ?>">
                            <?php for ($i = 1; $i <= CITAS_PARA_GRATIS; $i++): ?>
                                <span class="<?= ($i <= $tarjeta['sellos'] || $tarjeta['disponibles'] > 0) ? 'lleno' : '' ?>"></span>
                            <?php endfor; ?>
                            <span class="regalo <?= $tarjeta['disponibles'] > 0 ? 'lleno' : '' ?>">🎁</span>
                        </div>

                        <?php if ($tarjeta['disponibles'] > 0): ?>
                            <small class="marca marca-gratis">Tiene cita gratis</small>
                        <?php elseif ($c['gratis_reservadas'] > 0): ?>
                            <small class="marca marca-gratis">Gratis reservada</small>
                        <?php else: ?>
                            <small><?= $tarjeta['sellos'] ?> de <?= CITAS_PARA_GRATIS ?></small>
                        <?php endif; ?>
                    </td>

                    <td><?= $c['ultima_visita'] ? date('d/m/Y', strtotime($c['ultima_visita'])) : '—' ?></td>

                    <td><?= date('d/m/Y', strtotime($c['fecha_registro'])) ?></td>

                </tr>

            <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</section>

<?php require_once "../includes/footer.php"; ?>
