<?php

// ==========================================================
// TARJETA DE FIDELIDAD — escrita UNA sola vez.
// "La cita número 10 es gratis": después de 9 citas pagadas y
// COMPLETADAS, la siguiente sale gratis.
//
// La usan:
//   acciones/guardar.reserva.php   (la clienta reserva en la página)
//   super/guardar_cita.php         (el administrador registra en el salón)
//   super/buscar_cliente.php       (el formulario del salón muestra los sellos)
//   super/clientes.php             (lista de clientas con sus sellos)
//
// El contador NO es un número guardado en la base: se CALCULA contando
// las citas de la clienta. Un número guardado se puede descuadrar
// (una cita cancelada, un error); el conteo siempre dice la verdad.
// ==========================================================

// Cuántas citas pagadas hacen falta para ganar una gratis.
// 9 pagadas -> la número 10 es gratis.
const CITAS_PARA_GRATIS = 9;


// Deja el celular como se guarda en la base: 10 dígitos, sin espacios,
// guiones, paréntesis ni el 57 de Colombia. Devuelve "" si no son 10 dígitos.
//   "300 123 4567" -> "3001234567"      "+57 300-123-4567" -> "3001234567"
function normalizar_telefono($telefono)
{
    $numero = preg_replace('/\D/', '', $telefono);

    if (strlen($numero) === 12 && str_starts_with($numero, '57')) {
        $numero = substr($numero, 2);
    }

    return strlen($numero) === 10 ? $numero : '';
}


// Estado de la tarjeta de una clienta.
// Devuelve:
//   pagadas      citas completadas que pagó (cuentan para la tarjeta)
//   sellos       cuántas lleva hacia la próxima gratis (0 a 8)
//   disponibles  cuántas citas gratis tiene para usar ahora (0 o más)
// $al_guardar = true cuando se va a guardar una cita (dentro de la transacción).
//   Entonces la lectura es "con bloqueo" (FOR UPDATE), que siempre ve lo último
//   guardado. Una lectura normal dentro de una transacción ve una "foto" tomada
//   al principio, y si dos citas de la misma clienta llegan al mismo tiempo,
//   las dos verían la gratis disponible y las dos saldrían gratis
//   (probado: pasó con 3 de 4 reservas simultáneas antes de este arreglo).
function tarjeta_cliente($conexion, $id_cliente, $al_guardar = false)
{
    $stmt = $conexion->prepare("
        SELECT
            SUM(estado = 'Completada' AND gratis = 0) AS pagadas,
            SUM(estado = 'Completada' AND gratis = 1) AS gratis_usadas,
            SUM(estado IN ('Pendiente', 'Confirmada') AND gratis = 1) AS gratis_reservadas
        FROM reservas
        WHERE id_cliente = ?
    " . ($al_guardar ? " FOR UPDATE" : ""));
    $stmt->bind_param("i", $id_cliente);
    $stmt->execute();

    $f = $stmt->get_result()->fetch_assoc();

    return calcular_tarjeta($f['pagadas'], $f['gratis_usadas'], $f['gratis_reservadas']);
}


// La cuenta en sí (la usan tarjeta_cliente() y la lista de clientas).
function calcular_tarjeta($pagadas, $gratis_usadas, $gratis_reservadas)
{
    $pagadas = intval($pagadas);

    // Cada 9 pagadas se gana una gratis.
    $ganadas = intdiv($pagadas, CITAS_PARA_GRATIS);

    // Una gratis que ya se usó, o que ya está reservada, no se puede volver a usar.
    // (Si la reservada se cancela, deja de contar aquí y la clienta la recupera.)
    $disponibles = $ganadas - intval($gratis_usadas) - intval($gratis_reservadas);

    return [
        'pagadas' => $pagadas,
        'sellos' => $pagadas % CITAS_PARA_GRATIS,
        'disponibles' => max(0, $disponibles),
    ];
}


// Texto corto para mostrar: "7 de 9" o "¡Tiene 1 cita gratis!"
function texto_tarjeta($tarjeta)
{
    if ($tarjeta['disponibles'] > 0) {
        return "¡Tiene una cita gratis para usar!";
    }

    return $tarjeta['sellos'] . " de " . CITAS_PARA_GRATIS . " citas · la " .
        (CITAS_PARA_GRATIS + 1) . ".ª es gratis";
}


// Busca la clienta por celular; si no existe, la crea.
// Si existe, NO le cambia el nombre (así nadie le cambia el nombre a otra
// persona escribiendo su celular).
// Debe llamarse DENTRO de una transacción: la fila queda bloqueada
// (FOR UPDATE) para que dos citas al mismo tiempo no usen la misma gratis.
function buscar_o_crear_cliente($conexion, $telefono, $nombre, $correo = '')
{
    $stmt = $conexion->prepare("SELECT id_cliente FROM clientes WHERE telefono = ? FOR UPDATE");
    $stmt->bind_param("s", $telefono);
    $stmt->execute();

    $cliente = $stmt->get_result()->fetch_assoc();

    if ($cliente) {
        return intval($cliente['id_cliente']);
    }

    try {

        $stmt = $conexion->prepare("INSERT INTO clientes (nombre, telefono, correo) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $nombre, $telefono, $correo);
        $stmt->execute();

        return $conexion->insert_id;

    } catch (mysqli_sql_exception $e) {

        // 1062 = celular repetido: otra reserva la creó en ese mismo instante.
        // Se usa la que ya quedó creada.
        if ($e->getCode() !== 1062) {
            throw $e;
        }

        $stmt = $conexion->prepare("SELECT id_cliente FROM clientes WHERE telefono = ? FOR UPDATE");
        $stmt->bind_param("s", $telefono);
        $stmt->execute();

        return intval($stmt->get_result()->fetch_assoc()['id_cliente']);
    }
}
