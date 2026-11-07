document.addEventListener('submit', (evento) => {
    const formulario = evento.target.closest('[data-confirmar]');
    if (formulario && !window.confirm(formulario.dataset.confirmar)) evento.preventDefault();
});
document.addEventListener('click', (evento) => {
    if (evento.target.closest('[data-imprimir]')) window.print();
});
const casilla = document.querySelector("[data-cuenta]");
const campos = document.querySelector("[data-campos-cuenta]");
function actualizarCampos() {
    if (!casilla || !campos) return;
    campos.hidden = !casilla.checked;
    campos.disabled = !casilla.checked;
}
casilla?.addEventListener("change", actualizarCampos);
actualizarCampos();
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
