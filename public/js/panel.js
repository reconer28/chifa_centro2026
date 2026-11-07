document.querySelectorAll("[data-confirmar]").forEach((formulario) =>
    formulario.addEventListener("submit", (evento) => {
        if (!window.confirm(formulario.dataset.confirmar))
            evento.preventDefault();
    }),
);
document
    .querySelectorAll("[data-imprimir]")
    .forEach((boton) => boton.addEventListener("click", () => window.print()));
const casilla = document.querySelector("[data-cuenta]");
const campos = document.querySelector("[data-campos-cuenta]");
function actualizarCampos() {
    if (!casilla || !campos) return;
    campos.hidden = !casilla.checked;
    campos.disabled = !casilla.checked;
}
casilla?.addEventListener("change", actualizarCampos);
actualizarCampos();
const tablero = document.querySelector("[data-tablero]");
let enviando = false;
document.addEventListener("submit", () => {
    enviando = true;
});
async function actualizarTablero() {
    if (!tablero) return;
    if (
        !enviando &&
        document.visibilityState === "visible" &&
        !tablero.contains(document.activeElement)
    ) {
        try {
            const respuesta = await fetch(tablero.dataset.url, {
                headers: { "X-Tablero": "1", Accept: "text/html" },
                credentials: "same-origin",
                cache: "no-store",
            });
            if (respuesta.redirected) {
                window.location.assign(respuesta.url);
                return;
            }
            if (!respuesta.ok) throw new Error("No se pudo actualizar");
            tablero.innerHTML = await respuesta.text();
            document.getElementById("estado-tablero").textContent =
                "● Actualizado " + new Date().toLocaleTimeString("es-PE");
        } catch (error) {
            document.getElementById("estado-tablero").textContent =
                "Sin conexión. Reintentando…";
        }
    }
    window.setTimeout(actualizarTablero, 5000);
}
if (tablero) window.setTimeout(actualizarTablero, 5000);

const botonMenu = document.querySelector("[data-alternar-menu]");
if (botonMenu) {
    const menuLateral = document.getElementById("menu-lateral");
    const etiquetaMenu = botonMenu.querySelector("[data-etiqueta-menu]");
    const clavePreferenciaMenu = "chifa.cocina.menu_contraido";
    function aplicarEstadoMenu(contraido) {
        document.body.classList.toggle("menu-contraido", contraido);
        menuLateral.hidden = contraido;
        botonMenu.setAttribute("aria-expanded", String(!contraido));
        etiquetaMenu.textContent = contraido ? "Mostrar menú" : "Ocultar menú";
    }
    let menuContraido = true;
    try {
        const preferenciaMenu = localStorage.getItem(clavePreferenciaMenu);
        if (preferenciaMenu !== null) menuContraido = preferenciaMenu === "1";
    } catch {}
    aplicarEstadoMenu(menuContraido);
    botonMenu.addEventListener("click", () => {
        const contraido = !document.body.classList.contains("menu-contraido");
        aplicarEstadoMenu(contraido);
        try {
            localStorage.setItem(clavePreferenciaMenu, contraido ? "1" : "0");
        } catch {}
    });
}
