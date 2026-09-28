document.addEventListener("DOMContentLoaded", function () {

    console.log("Nails Spa Belleza iniciado.");

    const fecha = document.getElementById("fecha");

    const manicurista = document.querySelector(
        '[name="manicurista"]'
    );

    const hora = document.getElementById("hora");


    // ==========================================
    // FECHA MÍNIMA
    // ==========================================

    if (fecha) {

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
    // COMPROBAR HORARIOS
    // ==========================================

    function comprobarHorarios() {

        if (!fecha || !manicurista || !hora) {
            return;
        }

        const fechaSeleccionada = fecha.value;
        const manicuristaSeleccionada =
            manicurista.value;


        // ======================================
        // RESTAURAR HORAS
        // ======================================

        hora.querySelectorAll(
            "option:not(:first-child)"
        ).forEach(function (opcion) {

            if (opcion.dataset.textoOriginal) {

                opcion.textContent =
                    opcion.dataset.textoOriginal;

            }

            opcion.disabled = false;

        });


        // ======================================
        // DESCANSO 12:00 PM - 1:00 PM
        // ======================================

        const opcionDescanso =
            hora.querySelector(
                'option[value="12:00:00"]'
            );

        if (opcionDescanso) {

            opcionDescanso.disabled = true;

            opcionDescanso.textContent =
                "12:00 PM — DESCANSO (12:00 PM - 1:00 PM)";
        }


        if (fechaSeleccionada === "") {
            return;
        }


        // ======================================
        // BLOQUEAR HORAS PASADAS SI ES HOY
        // ======================================

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


        if (fechaSeleccionada === fechaActual) {

            const horaActual =
                hoy.getHours();

            const minutoActual =
                hoy.getMinutes();

            hora.querySelectorAll(
                "option:not(:first-child)"
            ).forEach(function (opcion) {

                if (
                    opcion.value === "12:00:00"
                ) {
                    return;
                }

                if (!opcion.dataset.textoOriginal) {

                    opcion.dataset.textoOriginal =
                        opcion.textContent.trim();

                }

                const partes =
                    opcion.value.split(":");

                const horaOpcion =
                    parseInt(partes[0]);

                const minutoOpcion =
                    parseInt(partes[1]);

                if (
                    horaOpcion < horaActual ||
                    (
                        horaOpcion === horaActual &&
                        minutoOpcion <= minutoActual
                    )
                ) {

                    opcion.disabled = true;

                    opcion.textContent =
                        opcion.dataset.textoOriginal +
                        " — YA PASÓ";

                }

            });

        }


        // ======================================
        // CONSULTAR HORARIOS OCUPADOS
        // ======================================

        if (manicuristaSeleccionada === "") {
            return;
        }


        fetch(
            "../acciones/horarios.ocupados.php" +
            "?fecha=" +
            encodeURIComponent(fechaSeleccionada) +
            "&manicurista=" +
            encodeURIComponent(
                manicuristaSeleccionada
            )
        )
        .then(function (respuesta) {

            if (!respuesta.ok) {
                throw new Error(
                    "Error al consultar horarios."
                );
            }

            return respuesta.json();

        })
        .then(function (horariosOcupados) {

            const ocupados =
                horariosOcupados.map(function (hora) {

                    return hora.substring(0, 8);

                });


            hora.querySelectorAll(
                "option:not(:first-child)"
            ).forEach(function (opcion) {

                if (
                    opcion.value === "12:00:00"
                ) {
                    return;
                }


                if (!opcion.dataset.textoOriginal) {

                    opcion.dataset.textoOriginal =
                        opcion.textContent.trim();

                }


                if (
                    ocupados.includes(
                        opcion.value
                    )
                ) {

                    opcion.disabled = true;

                    opcion.textContent =
                        opcion.dataset.textoOriginal +
                        " — OCUPADO";

                }

            });


            // ==================================
            // QUITAR SELECCIÓN SI YA NO ES VÁLIDA
            // ==================================

            if (
                hora.value !== "" &&
                hora.options[
                    hora.selectedIndex
                ].disabled
            ) {

                hora.value = "";
            }

        })
        .catch(function (error) {

            console.error(
                "Error al consultar horarios:",
                error
            );

        });

    }


    // ==========================================
    // CAMBIO DE FECHA
    // ==========================================

    if (fecha) {

        fecha.addEventListener(
            "change",
            comprobarHorarios
        );

    }


    // ==========================================
    // CAMBIO DE MANICURISTA
    // ==========================================

    if (manicurista) {

        manicurista.addEventListener(
            "change",
            comprobarHorarios
        );

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