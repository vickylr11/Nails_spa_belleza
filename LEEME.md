# Nails Spa Belleza — versión corregida v9 (referencia del docente)

Hecha sobre la versión del grupo del 28/09/2026 (commit "actualizaciones", con las 4 manicuristas,
el logo y las fotos). Se conservó su diseño, sus fotos, mysqli y el nombre `guardar.reserva.php`.
Acompaña a `Grupo5_NailsSpa_Lista_temas_puntuales_v5.pdf`.

## Instalar
- **Base que ya tienen** (con la columna `manicurista` de texto): phpMyAdmin > nails_spa > SQL >
  pegar `sql/actualizacion.sql` y después `sql/actualizacion_2.sql`, cada una UNA vez. No borran citas.
  y luego `_3.sql` y `_4.sql`, cada una UNA vez, en ese orden. Si ya hicieron algunas, solo las que faltan.
  (Base del `nails.spa.sql` de la v4, v5 o v6: faltan `_3` y `_4`. De la v7: solo `_4`.)
- **Base nueva**: importar `sql/nails.spa.sql`.
- Funciona en cualquier carpeta de htdocs (probado en `Blanquizal/PracticaLaboral/Nails_spa_belleza`).
- Zona del personal: **`/super/`** (el login es `/super/login.php`). No hay enlace público.
- Usuarios (cambiar TODAS las claves antes de entregar, en «Mi contraseña»):

| Correo | Clave | Rol |
|---|---|---|
| admin@nailsspa.com | admin123 | administrador |
| marangeles@nailsspa.com | marangeles123 | manicurista |
| victoria@nailsspa.com | victoria123 | manicurista |
| jennifer@nailsspa.com | jennifer123 | manicurista |
| valeria@nailsspa.com | valeria123 | manicurista |

## Estructura

```
Nails_spa_belleza/
├── index.php                 público
├── paginas/reservar.php      público
├── acciones/                 público: guardar.reserva.php, horarios.ocupados.php
├── assets/css/ js/ img/      estilos, script y fotos (logo.png = la antigua foto1.png)
│   └── img/servicios/        fotos subidas desde el panel (.htaccess: ningún .php se ejecuta)
├── super/                    zona del personal (todo pide login; arriba sale una barra con el menú del rol)
│   ├── login.php  salir.php  index.php (manda al login)
│   ├── agenda.php            admin: todas + filtro; manicurista: solo la suya
│   ├── cambiar_estado.php    admin: 5 estados; manicurista: Confirmada/Completada/No asistió, solo sus citas
│   ├── horarios.php  guardar_horarios.php  clientes.php   solo administrador
│   ├── servicios.php  guardar_servicio.php        solo admin: crear, editar, foto, activar/desactivar
│   ├── manicuristas.php  guardar_manicurista.php  solo admin: crear, editar, activar/desactivar
│   ├── usuarios.php  guardar_usuario.php          solo admin: crear, editar, rol, clave, dar/quitar acceso
│   └── clave.php  guardar_clave.php         cada quien cambia su contraseña
├── includes/   (bloqueada)   header, footer, agenda.php (la regla), seguridad.php (roles), panel.php (ayudas de formularios y fotos)
├── conexion/   (bloqueada)
└── sql/        (bloqueada)   nails.spa.sql, actualizacion.sql
```

`includes/`, `conexion/` y `sql/` tienen `.htaccess` con `Require all denied`: desde el navegador dan 403
(probado en Apache 2.4 + mod_php, como XAMPP). Esto importa sobre todo por `sql/`, que tiene los usuarios.

**Roles.** `usuarios.rol` = administrador | manicurista; `usuarios.id_manicurista` liga la cuenta con su
manicurista (un CHECK impide una manicurista sin ella); `activo = 0` quita el acceso sin borrar.
Todo se revisa en el servidor: la agenda de la manicurista se filtra con el id de su **sesión**
(no del formulario), y `cambiar_estado.php` rechaza citas ajenas aunque se cambie el id con F12.

**¿Por qué /super/ y no /admin/?** Los robots que buscan paneles prueban primero /admin, /administrador,
/wp-admin. Cambiar el nombre reduce ese ruido, pero **no es la seguridad**: lo que protege es el login
y `seguridad.php` en cada página.

## La idea central
`includes/agenda.php` tiene la regla de la agenda escrita una sola vez (`revisar_cita()`):
servicio y manicurista existen · no es pasada · el salón abre ese día · el servicio termina antes
del cierre · no cruza el descanso 12–1 · la manicurista no tiene otra cita que se cruce (con duración).
La usan `acciones/guardar.reserva.php` (al confirmar) y `acciones/horarios.ocupados.php` (JSON que el
formulario usa para marcar cada hora: OCUPADA, DESCANSO, CERRADO, YA PASÓ, NO ALCANZA ANTES DEL CIERRE).
El formulario vuelve a preguntar cada 45 s. Al guardar, una transacción con `FOR UPDATE` sobre la
manicurista evita que dos clientas tomen la misma hora al mismo tiempo (probado: 8 a la vez → entra 1).

## Páginas del administrador (v4)

Regla general: **nada se borra**. Servicios, manicuristas y usuarios se activan/desactivan, porque
tienen citas en su historia. Todo se valida en el servidor antes de guardar; si hay error, el
formulario vuelve con lo que se había escrito (`volver_con_error()` en `includes/panel.php`).

**Servicios** — nombre (3–100, único), descripción (≤500), precio ($1.000–$10.000.000), duración
(15–480 min, de 5 en 5), foto. La foto se valida con `getimagesize()` (no por el nombre): solo JPG,
PNG o WEBP, máximo 5 MB; se guarda con nombre al azar en `assets/img/servicios/` y la ruta va en
`servicios.imagen`. Al cambiar la foto se borra la anterior subida (las originales `foto3..6.png`
nunca). Un servicio desactivado desaparece de la portada y del formulario, y el servidor rechaza
reservarlo. Si al subir sale «la carpeta no deja guardar archivos»: en la Terminal, dentro del
proyecto, `chmod 777 assets/img/servicios`.

**Historia intacta** — cada cita guarda `precio` y `duracion` del día en que se reservó
(`reservas.precio`, `reservas.duracion`). Si el administrador cambia el servicio, las citas ya
agendadas no cambian de precio ni se «estiran» encima de otras: el choque usa la duración de la cita.

**Manicuristas** — nombre (único). No se puede desactivar a una que tenga citas pendientes o
confirmadas desde hoy (quedarían sin quién las atienda), ni a la última activa. Desactivada, sale del
formulario de reserva y el servidor la rechaza. La lista muestra citas próximas y si tiene cuenta
(con enlace «Crear cuenta» que abre Usuarios ya lleno).

**Usuarios** — nombre, correo (único, se guarda en minúsculas), rol, cuál manicurista (solo si el rol
es manicurista; el campo se oculta con JS y el servidor lo revisa igual), contraseña (obligatoria al
crear, mínimo 8; al editar, vacía = no se cambia; sal nueva al azar cada vez). Una manicurista tiene
como máximo una cuenta (`UNIQUE` en `usuarios.id_manicurista`). El administrador no puede quitarse el
acceso ni el rol a sí mismo, y siempre debe quedar al menos un administrador con acceso.
`seguridad.php` relee al usuario en cada página: si se le quita el acceso o se le cambia el rol, vale
en su siguiente clic.

## Confirmar por WhatsApp (v5)

En la agenda del panel, cada cita Pendiente o Confirmada tiene un botón verde que abre WhatsApp
(`https://wa.me/57…?text=…`) con el número de la clienta y el mensaje ya escrito:
**Confirmar** (Pendiente) pide que responda SÍ; **Recordar** (Confirmada) es un recordatorio.
La persona del salón toca Enviar y, cuando la clienta responde, cambia el estado a Confirmada.
El teléfono se limpia (`300 123 4567`, `+57 300-123-4567` → `573001234567`); si no es un celular
colombiano, sale «Teléfono no válido». Código en `includes/whatsapp.php`.
**No es envío automático:** eso exige la API de WhatsApp Business (verificación de Meta, pago por
mensaje, servidor en internet). La manicurista ve el botón solo en sus citas.

## Horas como botones (v9)

En la página y en «Cita en el salón» la hora ya no se escoge en una lista ni con horas y minutos:
salen **botones, uno por cada hora en punto** en que el salón abre ESE día (el sábado hasta las 3:00 PM
porque cierra a las 4; el domingo, «Ese día el salón no atiende»). Las libres se tocan; las que no, salen
en gris con el motivo: Ocupada, Descanso o No alcanza. Las que ya pasaron ni se muestran.
- `includes/agenda.php`: `horas_del_dia($conexion, $fecha, $servicio, $manicurista, $modo)` arma los
  botones y a cada uno le aplica `revisar_cita()` (la misma regla de siempre).
- `acciones/horas_disponibles.php` (reemplaza `horarios.ocupados.php`) los devuelve en JSON. Los modos
  del salón (`salon_confirmada`, `salon_completada`) solo valen con sesión de administrador.
- Salón: «La van a atender» agrega un botón **«Ahora»** (hora actual, de 5 en 5 min) para la clienta que
  acaba de llegar; «Ya la atendieron» muestra las horas que ya pasaron.
- `assets/js/script.js`: al tocar un botón, su hora queda en el campo oculto `hora`; no deja enviar sin
  escoger; cada 45 s se actualiza y, si la hora escogida se ocupó, avisa.

## Tarjeta de fidelidad: la cita 10 es gratis (v8)

- La clienta se reconoce por su **celular**, guardado siempre igual (10 dígitos: `normalizar_telefono()`),
  y ahora es `UNIQUE`. `actualizacion_4.sql` limpia los celulares ya guardados y, si una clienta quedó
  dos veces, junta sus citas en un solo registro.
- Regla en `includes/fidelidad.php` (una sola vez): solo cuentan las citas **Completadas**; después de
  9 pagadas, la siguiente es gratis (`reservas.gratis = 1`). El contador **no se guarda: se calcula**
  (`SUM(...)` sobre sus citas). Una gratis reservada no se puede usar dos veces; si se cancela, vuelve.
- Página pública: si la clienta tiene una gratis ganada, la cita sale gratis sola; el mensaje le dice
  «🎁 ¡Esta cita es GRATIS!» o cuántas le faltan. La página no muestra datos de nadie por su celular.
- Cita en el salón: primero el celular; si existe, aparece su nombre (no editable) y sus sellos; si es
  nueva, se escribe el nombre. Si tiene gratis, una casilla (marcada) permite guardarla para otro día.
  `super/buscar_cliente.php` (JSON, solo admin).
- Clientes: búsqueda por nombre o celular, citas completadas, sellos ●●●○○ y última visita.
- Agenda: precio tachado y «🎁 Gratis»; el WhatsApp lo menciona. Pagos: a la manicurista **se le paga
  igual** sobre el precio (el regalo lo asume el salón) y sale la marca «Gratis para la clienta».
- **Carrera corregida al probar:** la tarjeta se leía con una lectura normal dentro de la transacción
  (ve una «foto» vieja) y 3 de 4 reservas simultáneas salieron gratis. Ahora se lee `FOR UPDATE`
  (`tarjeta_cliente(..., true)`): probado 3 rondas de 4 a la vez → 1 gratis cada vez.
- **Nueva regla:** una cita no se puede marcar Completada ni «No asistió» antes de su hora.

## Cita en el salón y pagos a manicuristas (v7)

**Cita en el salón** (`super/cita_nueva.php`, solo administrador; botón «+ Nueva cita» de la agenda):
para la clienta que llega sin cita, la que llama, o un servicio que se hizo y no quedó registrado.
Hora libre de 5 en 5 minutos. «La van a atender» → Confirmada (permite hasta 30 min atrás);
«Ya la atendieron» → Completada (hora pasada, máximo 60 días). Usa la misma regla de la agenda
(`revisar_cita(..., $permitir_pasado = true)`), con transacción. Queda `reservas.origen = 'salon'`
y en la agenda sale la marca «En el salón».

**Pagos** (`super/pagos.php`): solo cuentan las citas **Completadas**. Cada manicurista gana un
porcentaje del precio de la cita (`manicuristas.porcentaje`, 50% por defecto, se cambia en
Manicuristas). Periodo por defecto: la quincena actual (atajos: esta quincena, anterior, este mes).
- Administrador: resumen de lo pendiente por manicurista → «Ver y pagar» → detalle →
  «Registrar pago». `registrar_pago.php` recalcula todo en el servidor (nunca usa un total del
  formulario), rechaza si el total cambió mientras se revisaba, bloquea con `FOR UPDATE`
  (probado: 6 clics a la vez → 1 pago), crea el registro en `pagos` y marca cada cita con
  `id_pago` y `valor_manicurista`. Comprobante imprimible con firmas. Historial de pagos.
- Avisa si hay servicios sin pagar de antes del periodo.
- Manicurista: «Mis pagos», solo lo suyo (pendiente, historial y sus comprobantes).
- Una cita ya pagada no cambia de estado. Cambiar el porcentaje no altera lo ya pagado.

**Hora de Colombia:** `conexion.php` fija `America/Bogota` en PHP y `-05:00` en MySQL; si no,
en un computador configurado en UTC, «ya pasó» y «citas de hoy» quedaban corridas 5 horas.

## Estilos (v6)

`assets/css/estilo.css` se reescribió completo (antes ~1.500 líneas con reglas repetidas y
sobrescritas: `.hero` tres veces, un `form {}` que volvía tarjeta cualquier formulario). Ahora
son 14 secciones numeradas, cada clase definida una sola vez, colores y medidas en variables
(`:root`), la misma paleta vino/rosa y el mismo logo. Letras: Playfair Display (títulos) y
Poppins (texto) desde Google Fonts; sin internet usa Georgia/Arial. En `index.php` se corrigió
el HTML de la portada (tenía un `<section class="hero">` metido dentro de otro) y se quitaron los
`style="..."` en línea. El CSS y el JS llevan `?v=` con la fecha del archivo para que el navegador
no muestre la versión vieja guardada en caché. Probado en 390, 768 y 1366 px sin scroll horizontal.

## Qué cambió respecto a su versión
| Archivo | Cambio |
|---|---|
| `includes/agenda.php` | NUEVO: la regla completa |
| `acciones/guardar.reserva.php` | usa la regla + transacción; ya no cambia el nombre de una clienta existente; vuelve a `reservar.php?ok=` |
| `acciones/horas_disponibles.php` | (v9) las horas del día como botones, con motivo si no se puede |
| `assets/js/script.js` | movido desde `img/js/`; ahora sí carga. Consulta al cambiar fecha, servicio o manicurista |
| `paginas/reservar.php` | manicuristas y horas salen de la base |
| `includes/header.php`, `footer.php`, `index.php` | rutas con `$base` en vez de `/NAILS_SPA_BELLEZA/`; el `<style>` del menú pasó al CSS; menú del panel solo con sesión |
| `includes/seguridad.php`, `super/*` | login con roles, cambio de contraseña (sal nueva al azar por usuario) |
| `super/agenda.php` | por rol: admin todas + filtro por manicurista y precios; manicurista «Mi agenda» |
| `super/cambiar_estado.php` | reemplaza `cancelar_reserva.php`: estados por rol, una cancelada no se reactiva |
| `super/guardar_horarios.php`, `super/horarios.php` | solo admin; valida los 7 días antes de guardar |
| `assets/css/estilo.css` | estados, mensajes de error, horas deshabilitadas, arreglo para celular |
| `sql/` | tabla `manicuristas`, `reservas.id_manicurista`, sin UNIQUE(fecha,hora), 5 estados, RESTRICT, `usuarios` con rol, `SET NAMES utf8mb4` |

Queda para el grupo: galería de diseños, datos y horario reales, cambiar la clave, exportar el .sql final.
