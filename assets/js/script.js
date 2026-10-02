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
        avisarCambioHoras();
    }

    // Avisa que las horas se volvieron a pintar (el resumen de la reserva lo escucha).
    function avisarCambioHoras() {
        cajaHoras.dispatchEvent(new Event("horas-cambiaron"));
    }

    function cargarHoras(avisarSiSePerdio) {

        if (!cajaHoras || !fecha || !servicio || !manicurista || !hora) {
            return;
        }

        if (fecha.value === "" || servicio.value === "" || manicurista.value === "") {
            hora.value = "";
            aviso(document.getElementById("formReserva")
                ? "Escoge el día, la manicurista y el servicio para ver las horas libres."
                : "Escoge servicio, manicurista y fecha para ver las horas.");
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
                        avisarCambioHoras();
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

                avisarCambioHoras();
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
        // En la página de reservar se dice qué paso falta.
        hora.form.addEventListener("submit", function (evento) {

            if (hora.value !== "") {
                return;
            }

            evento.preventDefault();

            let falta = ["Escoge una hora tocando uno de los botones.", cajaHoras];

            if (fecha.value === "") {
                falta = ["Escoge el día de tu cita.", document.getElementById("paso-dia") || fecha];
            } else if (manicurista.value === "") {
                falta = ["Escoge tu manicurista.", document.getElementById("paso-manicurista") || manicurista];
            } else if (servicio.value === "") {
                falta = ["Escoge el servicio.", document.getElementById("paso-servicio") || servicio];
            }

            alert(falta[0]);
            falta[1].scrollIntoView({ behavior: "smooth", block: "center" });
        });
    }


    // ==========================================
    // RESERVA EN PASOS (paginas/reservar.php)
    //   1 día → 2 manicurista → 3 servicio → 4 hora → 5 datos
    // Cada botón solo escribe un valor en un campo oculto (fecha,
    // manicurista, servicio) y avisa con un evento "change": el bloque
    // de HORAS COMO BOTONES (arriba) escucha ese aviso y pide las horas.
    // ==========================================

    const formPasos = document.querySelector(".form-pasos");

    if (formPasos) {

        const pasoManicurista = document.getElementById("paso-manicurista");
        const pasoServicio = document.getElementById("paso-servicio");
        const pasoHora = document.getElementById("paso-hora");
        const fechaOtra = document.getElementById("fecha-otra");
        const resumen = document.getElementById("resumen");

        const botonesDia = formPasos.querySelectorAll(".dia-btn");
        const botonesManicurista = formPasos.querySelectorAll(".manicurista-btn");
        const botonesServicio = formPasos.querySelectorAll(".servicio-btn");

        // Marca un botón como escogido y desmarca los demás del grupo.
        function marcar(grupo, escogido) {
            grupo.forEach(function (b) {
                b.classList.toggle("escogida", b === escogido);
            });
        }

        function desbloquear(paso, si) {
            paso.classList.toggle("bloqueado", !si);
        }

        // Baja suavemente al siguiente paso (útil en el celular).
        function irA(paso) {
            paso.scrollIntoView({ behavior: "smooth", block: "nearest" });
        }

        function buscar(grupo, id) {
            return Array.from(grupo).find(function (b) { return b.dataset.id === String(id); });
        }

        // Los servicios que hace una manicurista: "1,2,4" -> ["1","2","4"]
        function serviciosDe(botonManicurista) {
            return botonManicurista.dataset.servicios
                ? botonManicurista.dataset.servicios.split(",")
                : [];
        }


        // ---------- Paso 1: el día ----------

        function escogerDia(valor) {

            fecha.value = valor;

            const boton = Array.from(botonesDia).find(function (b) { return b.dataset.fecha === valor; });
            marcar(botonesDia, boton);

            // Si el día no está en los botones, queda escrito en «¿Más adelante?»
            fechaOtra.value = boton ? "" : valor;

            desbloquear(pasoManicurista, valor !== "");
            fecha.dispatchEvent(new Event("change"));
            actualizarResumen();
        }

        botonesDia.forEach(function (boton) {
            boton.addEventListener("click", function () {
                escogerDia(boton.dataset.fecha);
                irA(pasoManicurista);
            });
        });

        fechaOtra.addEventListener("change", function () {
            if (fechaOtra.value !== "") {
                escogerDia(fechaOtra.value);
                irA(pasoManicurista);
            }
        });


        // ---------- Paso 2: la manicurista ----------
        // Al escogerla, en el paso 3 solo quedan SUS servicios.

        function escogerManicurista(boton) {

            manicurista.value = boton.dataset.id;
            marcar(botonesManicurista, boton);

            const suyos = serviciosDe(boton);

            botonesServicio.forEach(function (b) {
                b.hidden = !suyos.includes(b.dataset.id);
            });

            // El servicio que estaba escogido no lo hace ella: se quita.
            if (servicio.value !== "" && !suyos.includes(servicio.value)) {
                servicio.value = "";
                marcar(botonesServicio, null);
                avisarManicuristas();
            }

            pasoServicio.querySelector(".paso-espera").textContent = suyos.length
                ? "Estos son los servicios que hace " + boton.dataset.nombre + "."
                : boton.dataset.nombre + " no tiene servicios asignados todavía. Escoge otra manicurista.";

            desbloquear(pasoServicio, true);
            desbloquear(pasoHora, servicio.value !== "");

            manicurista.dispatchEvent(new Event("change"));
            actualizarResumen();
        }

        botonesManicurista.forEach(function (boton) {
            boton.addEventListener("click", function () {
                escogerManicurista(boton);
                irA(servicio.value !== "" ? pasoHora : pasoServicio);
            });
        });


        // ---------- Paso 3: el servicio ----------

        function escogerServicio(boton) {
            servicio.value = boton.dataset.id;
            marcar(botonesServicio, boton);
            desbloquear(pasoHora, true);
            servicio.dispatchEvent(new Event("change"));
            avisarManicuristas();
            actualizarResumen();
        }

        botonesServicio.forEach(function (boton) {
            boton.addEventListener("click", function () {
                escogerServicio(boton);
                irA(pasoHora);
            });
        });

        // Si ya hay un servicio escogido (vino desde la portada con ?servicio=3),
        // a las manicuristas que no lo hacen se les avisa. Igual se pueden escoger:
        // en ese caso se quita el servicio y la clienta escoge otro.
        function avisarManicuristas() {
            botonesManicurista.forEach(function (b) {
                const noLoHace = servicio.value !== "" && !serviciosDe(b).includes(servicio.value);
                b.classList.toggle("no-hace", noLoHace);
                b.querySelector(".manicurista-aviso").textContent = noLoHace ? "No hace este servicio" : "";
            });
        }


        // ---------- Resumen de lo escogido ----------

        function actualizarResumen() {

            const partes = [];

            if (fecha.value !== "") {
                // "2026-10-02" -> "viernes 2 de octubre" (T12:00 evita que la zona horaria cambie el día)
                partes.push(new Date(fecha.value + "T12:00:00").toLocaleDateString("es-CO", {
                    weekday: "long", day: "numeric", month: "long"
                }));
            }

            const btnHora = document.querySelector(".hora-btn.escogida");
            if (hora.value !== "" && btnHora) {
                partes.push("a las " + btnHora.firstChild.textContent.trim());
            }

            const m = buscar(botonesManicurista, manicurista.value);
            if (m) {
                partes.push("con " + m.dataset.nombre);
            }

            const s = buscar(botonesServicio, servicio.value);
            if (s) {
                partes.push(s.dataset.nombre + " (" + s.dataset.precio + ", " + s.dataset.duracion + " min)");
            }

            resumen.hidden = partes.length === 0;
            resumen.textContent = "Tu cita: " + partes.join(" · ");
            resumen.classList.toggle("completo", hora.value !== "");
        }

        document.getElementById("horas").addEventListener("horas-cambiaron", actualizarResumen);


        // ---------- Al abrir la página ----------
        // Si volvió de un error, o llegó con ?servicio=, se marca lo que ya estaba escogido.

        if (fecha.value !== "") {
            escogerDia(fecha.value);
        }

        const mInicial = buscar(botonesManicurista, manicurista.value);
        const sInicial = buscar(botonesServicio, servicio.value);

        if (sInicial) {
            marcar(botonesServicio, sInicial);
            avisarManicuristas();
        } else {
            servicio.value = "";
        }

        if (mInicial && fecha.value !== "") {
            escogerManicurista(mInicial);
        } else {
            manicurista.value = "";
        }

        actualizarResumen();
    }


    // ==========================================
    // CITA EN EL SALÓN: al escoger el servicio, en «Manicurista»
    // solo se pueden escoger las que lo hacen (data-servicios="1,2,4").
    // ==========================================

    if (servicio && manicurista && manicurista.tagName === "SELECT") {

        function filtrarManicuristas() {

            Array.from(manicurista.options).forEach(function (op) {

                if (!op.value) {
                    return;
                }

                const suyos = (op.dataset.servicios || "").split(",");
                op.disabled = servicio.value !== "" && !suyos.includes(servicio.value);

                if (op.disabled && op.selected) {
                    manicurista.value = "";
                    manicurista.dispatchEvent(new Event("change"));
                }
            });
        }

        servicio.addEventListener("change", filtrarManicuristas);
        filtrarManicuristas();
    }


    // ==========================================
    // BLOQUEOS: con «Todo el día» no hace falta escribir las horas
    // ==========================================

    const todoElDia = document.getElementById("todo-el-dia");

    if (todoElDia) {

        const cajaHorasBloqueo = document.getElementById("horas-bloqueo");

        function mostrarHorasBloqueo() {
            cajaHorasBloqueo.hidden = todoElDia.checked;
            cajaHorasBloqueo.querySelectorAll("input").forEach(function (campo) {
                campo.required = !todoElDia.checked;
            });
        }

        todoElDia.addEventListener("change", mostrarHorasBloqueo);
        mostrarHorasBloqueo();
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