document.addEventListener("DOMContentLoaded", function () {

    console.log("Nails Spa Belleza iniciado.");

    const fecha = document.getElementById("fecha");

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