document.addEventListener("DOMContentLoaded", function () {

    // ==========================================
    // CITA EN EL SALÓN: buscar la clienta por su celular
    // Cuando el celular tiene 10 dígitos, se pregunta al servidor
    // (super/buscar_cliente.php) si ya existe y cómo va su tarjeta.
    // ==========================================

    const telefonoSalon = document.getElementById("telefono-salon");

    if (telefonoSalon) {

        const info = document.getElementById("info-clienta");
        const nombreSalon = document.getElementById("nombre-salon");
        const cajaGratis = document.getElementById("caja-gratis");
        const formulario = telefonoSalon.form;

        let espera = null;

        function buscarClienta() {

            // Solo los números: "300 123 4567" -> "3001234567"
            let numero = telefonoSalon.value.replace(/\D/g, "");

            if (numero.length === 12 && numero.startsWith("57")) {
                numero = numero.substring(2);
            }

            info.hidden = true;
            cajaGratis.hidden = true;
            nombreSalon.readOnly = false;

            // Si el nombre lo había puesto la búsqueda (de otra clienta), se borra:
            // si no, una clienta nueva quedaría con el nombre de la anterior.
            if (nombreSalon.dataset.autollenado === "1") {
                nombreSalon.value = "";
                nombreSalon.dataset.autollenado = "";
            }

            // Se quita la marca de que se mostró la casilla de gratis
            const marca = formulario.querySelector('[name="gratis_mostrado"]');
            if (marca) {
                marca.remove();
            }

            if (numero.length !== 10) {
                return;
            }

            fetch("buscar_cliente.php?telefono=" + encodeURIComponent(numero))
                .then(function (respuesta) {
                    return respuesta.json();
                })
                .then(function (datos) {

                    info.hidden = false;

                    if (!datos.existe) {
                        info.className = "info-clienta nueva";
                        info.textContent = "Clienta nueva: escribe su nombre y queda registrada.";
                        nombreSalon.required = true;
                        nombreSalon.focus();
                        return;
                    }

                    // Ya existe: se usa su nombre y no se puede cambiar aquí.
                    nombreSalon.value = datos.nombre;
                    nombreSalon.dataset.autollenado = "1";
                    nombreSalon.readOnly = true;
                    nombreSalon.required = false;

                    info.className = "info-clienta" + (datos.disponibles > 0 ? " gratis" : "");
                    info.innerHTML = "";

                    const titulo = document.createElement("strong");
                    titulo.textContent = datos.nombre;

                    const detalle = document.createElement("span");
                    detalle.textContent = datos.pagadas + " citas completadas · " + datos.texto;

                    // Sellos: un punto por cita, lleno o vacío
                    const sellos = document.createElement("div");
                    sellos.className = "sellos";

                    for (let i = 1; i <= datos.meta + 1; i++) {
                        const punto = document.createElement("span");
                        if (i <= datos.meta) {
                            punto.className = (i <= datos.sellos || datos.disponibles > 0) ? "lleno" : "";
                        } else {
                            punto.className = "regalo" + (datos.disponibles > 0 ? " lleno" : "");
                            punto.textContent = "🎁";
                        }
                        sellos.appendChild(punto);
                    }

                    info.append(titulo, detalle, sellos);

                    if (datos.disponibles > 0) {
                        cajaGratis.hidden = false;
                        const oculto = document.createElement("input");
                        oculto.type = "hidden";
                        oculto.name = "gratis_mostrado";
                        oculto.value = "1";
                        formulario.appendChild(oculto);
                    }
                })
                .catch(function (error) {
                    console.error("Error al buscar la clienta:", error);
                });
        }

        // Espera a que dejen de escribir un momento antes de preguntar
        telefonoSalon.addEventListener("input", function () {
            clearTimeout(espera);
            espera = setTimeout(buscarClienta, 300);
        });

        buscarClienta();
    }


    // ==========================================
    // PANEL > USUARIOS: el campo "¿Cuál manicurista es?"
    // solo se muestra si el rol es manicurista.
    // (El servidor lo revisa igual: esto es solo comodidad.)
    // ==========================================

    const rol = document.getElementById("rol");
    const campoManicurista = document.getElementById("campo-manicurista");

    if (rol && campoManicurista) {

        function mostrarCampoManicurista() {
            campoManicurista.style.display =
                rol.value === "manicurista" ? "block" : "none";
        }

        rol.addEventListener("change", mostrarCampoManicurista);

        mostrarCampoManicurista();

    } else if (campoManicurista) {

        // Editándose a sí mismo (administrador): no aplica.
        campoManicurista.style.display = "none";
    }


    console.log("Nails Spa Belleza iniciado.");

    const fecha = document.getElementById("fecha");

    const servicio = document.getElementById("servicio");

    const manicurista = document.getElementById("manicurista");

    const hora = document.getElementById("hora");


    // ==========================================
    // FECHA MÍNIMA (solo en la página pública: la clienta no reserva en el pasado.
    // En «Cita en el salón» sí se puede escoger un día pasado para un servicio ya hecho.)
    // ==========================================

    if (fecha && document.getElementById("formReserva")) {

        const hoy = new Date();

        const año = hoy.getFullYear();

        const mes = String(
            hoy.getMonth() + 1
        ).padStart(2, "0");

        const dia = String(
            hoy.getDate()
        ).padStart(2, "0");

        const fechaActual =
            `${año}-${mes}-${dia}`;

        fecha.min = fechaActual;
    }



    // ==========================================
    // HORAS COMO BOTONES
    // Le pide al servidor las horas de ese día (acciones/horas_disponibles.php)
    // y pinta un botón por cada una. Las libres se pueden tocar; las que no,
    // salen en gris con el motivo (Ocupada, Descanso, No alcanza).
    // Al tocar una, su valor queda en el campo oculto «hora», que es lo que
    // se envía. El navegador no decide nada: la regla está en includes/agenda.php
    // y el servidor la vuelve a revisar al guardar.
    // ==========================================

    const cajaHoras = document.getElementById("horas");
    const estado = document.getElementById("estado");   // solo en «Cita en el salón»

    function modoActual() {

        if (cajaHoras.dataset.modo !== "salon") {
            return "web";
        }

        return estado && estado.value === "Completada"
            ? "salon_completada"
            : "salon_confirmada";
    }

    function aviso(texto) {
        cajaHoras.innerHTML = "";
        const p = document.createElement("p");
        p.className = "nota";
        p.textContent = texto;
        cajaHoras.appendChild(p);
    }

    function cargarHoras(avisarSiSePerdio) {

        if (!cajaHoras || !fecha || !servicio || !manicurista || !hora) {
            return;
        }

        if (fecha.value === "" || servicio.value === "" || manicurista.value === "") {
            hora.value = "";
            aviso("Escoge el servicio, la manicurista y la fecha para ver las horas libres.");
            return;
        }

        const url = cajaHoras.dataset.api +
            "?fecha=" + encodeURIComponent(fecha.value) +
            "&servicio=" + encodeURIComponent(servicio.value) +
            "&manicurista=" + encodeURIComponent(manicurista.value) +
            "&modo=" + modoActual();

        fetch(url)
            .then(function (respuesta) {
                if (!respuesta.ok) {
                    throw new Error("Error al consultar las horas.");
                }
                return respuesta.json();
            })
            .then(function (datos) {

                const escogida = hora.value;

                if (!datos.abierto) {
                    hora.value = "";
                    aviso("Ese día el salón no atiende. Escoge otra fecha.");
                    return;
                }

                const libres = datos.horas.filter(function (h) { return h.disponible; });

                const sinLibres = modoActual() === "salon_completada"
                    ? "No hay horas pasadas libres ese día para esta manicurista."
                    : "No quedan horas libres ese día con esta manicurista. Prueba otra fecha u otra manicurista.";

                if (datos.horas.length === 0) {
                    hora.value = "";
                    aviso(sinLibres);
                    return;
                }

                cajaHoras.innerHTML = "";

                // Todas en gris: se explica arriba, y se dejan ver los motivos.
                if (libres.length === 0) {
                    const p = document.createElement("p");
                    p.className = "nota horas-aviso";
                    p.textContent = sinLibres;
                    cajaHoras.appendChild(p);
                }

                let sigueDisponible = false;

                datos.horas.forEach(function (h) {

                    const boton = document.createElement("button");
                    boton.type = "button";                // no envía el formulario
                    boton.className = "hora-btn" + (h.ahora ? " hora-ahora" : "");
                    boton.dataset.hora = h.hora;
                    boton.textContent = h.texto;

                    if (!h.disponible) {

                        boton.disabled = true;

                        const motivo = document.createElement("small");
                        motivo.textContent = h.motivo;
                        boton.appendChild(motivo);

                    } else if (h.hora === escogida) {

                        boton.classList.add("escogida");
                        sigueDisponible = true;
                    }

                    boton.addEventListener("click", function () {
                        cajaHoras.querySelectorAll(".hora-btn").forEach(function (b) {
                            b.classList.remove("escogida");
                        });
                        boton.classList.add("escogida");
                        hora.value = h.hora;
                    });

                    cajaHoras.appendChild(boton);
                });

                // Si la hora escogida ya no está libre (otra clienta la tomó), se quita.
                if (escogida !== "" && !sigueDisponible) {
                    hora.value = "";
                    if (avisarSiSePerdio) {
                        alert("La hora que tenías escogida ya no está disponible. Escoge otra.");
                    }
                }
            })
            .catch(function (error) {
                console.error(error);
                aviso("No se pudieron cargar las horas. Revisa la conexión y vuelve a intentar.");
            });
    }

    if (cajaHoras) {

        // Cuando cambia algo que afecta las horas, se vuelven a pedir.
        [fecha, servicio, manicurista, estado].forEach(function (campo) {
            if (campo) {
                campo.addEventListener("change", function () {
                    cargarHoras(false);
                });
            }
        });

        cargarHoras(false);

        // "Tiempo real": cada 45 segundos se vuelve a preguntar,
        // por si otra clienta tomó una hora mientras esta decide.
        setInterval(function () {
            cargarHoras(true);
        }, 45000);

        // No se puede enviar sin escoger una hora.
        hora.form.addEventListener("submit", function (evento) {
            if (hora.value === "") {
                evento.preventDefault();
                alert("Escoge una hora tocando uno de los botones.");
                cajaHoras.scrollIntoView({ behavior: "smooth", block: "center" });
            }
        });
    }


    // ==========================================
    // VALIDAR FORMULARIO
    // ==========================================

    const formulario =
        document.getElementById("formReserva");

    if (formulario) {

        formulario.addEventListener(
            "submit",
            function (evento) {

                const nombre =
                    document.querySelector(
                        '[name="nombre"]'
                    ).value.trim();

                const telefono =
                    document.querySelector(
                        '[name="telefono"]'
                    ).value.trim();


                if (nombre.length < 3) {

                    alert(
                        "Escribe un nombre válido."
                    );

                    evento.preventDefault();

                    return;
                }


                if (telefono.length < 7) {

                    alert(
                        "Escribe un número de teléfono válido."
                    );

                    evento.preventDefault();

                    return;
                }

            }
        );

    }

});


function confirmarCancelacion() {

    return confirm(
        "¿Seguro que deseas cancelar esta reserva?"
    );

}