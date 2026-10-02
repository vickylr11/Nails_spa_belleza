<?php

// ==========================================================
// CONFIRMAR LA CITA POR WHATSAPP
// No se envía nada automáticamente (eso exige la API de WhatsApp
// Business de Meta: verificación, pago por mensaje y un servidor en
// internet). Se arma un enlace https://wa.me/... que abre WhatsApp con
// el número de la clienta y el mensaje ya escrito: la persona del salón
// solo toca Enviar y después marca la cita como Confirmada.
// ==========================================================


// Deja el teléfono como lo pide wa.me: solo números y con el 57 de Colombia.
// Devuelve "" si no parece un celular colombiano.
function numero_whatsapp($telefono)
{
    // Quitar espacios, guiones, paréntesis, el +...
    $numero = preg_replace('/\D/', '', $telefono);

    // 300 123 4567 -> 573001234567
    if (strlen($numero) === 10 && $numero[0] === '3') {
        return '57' . $numero;
    }

    // Ya viene con el 57: 57 300 123 4567
    if (strlen($numero) === 12 && str_starts_with($numero, '573')) {
        return $numero;
    }

    return '';
}


// Arma el enlace con el mensaje.
// Pendiente  -> mensaje para confirmar la cita.
// Confirmada -> recordatorio.
function enlace_whatsapp($reserva)
{
    $numero = numero_whatsapp($reserva['telefono']);

    if ($numero === '') {
        return '';
    }

    $dias = ['', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado', 'domingo'];

    $momento = strtotime($reserva['fecha'] . ' ' . $reserva['hora']);

    $cuando = $dias[date('N', $momento)] . ' ' . date('d/m/Y', $momento) .
        ' a las ' . date('h:i A', $momento);

    // Solo el primer nombre, para que suene cercano.
    $nombre = explode(' ', trim($reserva['cliente']))[0];

    // Si es su cita gratis de la tarjeta de fidelidad, se lo contamos.
    $regalo = !empty($reserva['gratis'])
        ? " 🎁 ¡Esta cita es GRATIS por ser tu cita número 10!"
        : "";

    if ($reserva['estado'] === 'Confirmada') {

        $mensaje = "Hola $nombre, te recordamos tu cita en Nails Spa Belleza: " .
            $reserva['servicio'] . " con " . $reserva['manicurista'] .
            " el $cuando.$regalo ¡Te esperamos!";

    } else {

        $mensaje = "Hola $nombre, te escribimos de Nails Spa Belleza para confirmar tu cita: " .
            $reserva['servicio'] . " con " . $reserva['manicurista'] .
            " el $cuando.$regalo ¿Nos confirmas tu asistencia respondiendo SÍ?";
    }

    // rawurlencode convierte espacios, tildes y signos para que viajen en el enlace.
    return "https://wa.me/" . $numero . "?text=" . rawurlencode($mensaje);
}
