import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import { nivelDemora, segundosTranscurridos, textoDuracion } from './alertas_pedidos.js';

const barra = document.querySelector('[data-tiempo-real]');
const contenido = document.querySelector('[data-contenido-pedidos]');
if (barra && contenido) {
    window.Pusher = Pusher;
    const estadoConexion = document.getElementById('estado-conexion');
    const botonSonido = barra.querySelector('[data-activar-sonido]');
    const aviso = document.querySelector('[data-aviso-pedido]');
    const textoAviso = aviso.querySelector('[data-texto-aviso]');
    const resumenDemoras = barra.querySelector('[data-resumen-demoras]');
    const amarillo = Number(barra.dataset.amarillo);
    const rojo = Number(barra.dataset.rojo);
    const eventosRecibidos = new Set();
    const avisosVisuales = new Set();
    let avisosSonoros = new Set();
    try { avisosSonoros = new Set(JSON.parse(sessionStorage.getItem('chifa.avisos_sonoros') || '[]')); } catch {}
    let contextoAudio = null;
    let sonidoActivo = false;
    let ultimaEmision = 0;
    let pendiente = false;
    let consultando = false;
    let navegando = false;
    let canalConectado = false;
    let programacion;
    let desfase = Number(contenido.dataset.servidorAhora) - Date.now();

    function mostrarAviso(mensaje, urgente = false) {
        textoAviso.textContent = mensaje;
        aviso.hidden = false;
        aviso.classList.toggle('aviso-urgente', urgente);
    }
    aviso.querySelector('[data-cerrar-aviso]').addEventListener('click', () => { aviso.hidden = true; });

    function emitirSonido(tipo = 'nuevo') {
        if (!sonidoActivo || !contextoAudio || contextoAudio.state !== 'running') return false;
        if (Date.now() - ultimaEmision < 2000) return false;
        ultimaEmision = Date.now();
        const frecuencias = tipo === 'urgente' ? [440, 440, 440] : tipo === 'modificado' ? [660, 880] : [880, 1100];
        frecuencias.forEach((frecuencia, indice) => {
            const oscilador = contextoAudio.createOscillator();
            const volumen = contextoAudio.createGain();
            const inicio = contextoAudio.currentTime + indice * 0.2;
            oscilador.type = 'sine';
            oscilador.frequency.value = frecuencia;
            volumen.gain.setValueAtTime(0, inicio);
            volumen.gain.linearRampToValueAtTime(0.12, inicio + 0.015);
            volumen.gain.exponentialRampToValueAtTime(0.001, inicio + 0.15);
            oscilador.connect(volumen);
            volumen.connect(contextoAudio.destination);
            oscilador.start(inicio);
            oscilador.stop(inicio + 0.17);
            oscilador.onended = () => { oscilador.disconnect(); volumen.disconnect(); };
        });
        return true;
    }

    botonSonido.addEventListener('click', async () => {
        if (sonidoActivo) {
            sonidoActivo = false;
        } else {
            try {
                const AudioDelNavegador = window.AudioContext || window.webkitAudioContext;
                contextoAudio ??= new AudioDelNavegador();
                await contextoAudio.resume();
                sonidoActivo = contextoAudio.state === 'running';
                if (!sonidoActivo) throw new Error('Audio no disponible');
            } catch {
                mostrarAviso('No se pudo activar el sonido. Revisa el audio del navegador.');
                return;
            }
        }
        botonSonido.setAttribute('aria-pressed', String(sonidoActivo));
        botonSonido.textContent = sonidoActivo ? 'Silenciar avisos' : 'Activar sonido';
        if (sonidoActivo) emitirSonido('nuevo');
    });

    function revisarRelojes() {
        let advertencias = 0;
        let urgentes = 0;
        const nuevosAvisos = [];
        const ahora = Date.now() + desfase;
        contenido.querySelectorAll('[data-demora-pedido]').forEach(reloj => {
            const segundos = segundosTranscurridos(Date.parse(reloj.dataset.inicio), ahora);
            const nivel = nivelDemora(segundos, amarillo, rojo);
            reloj.querySelector('[data-reloj-pedido]').textContent = textoDuracion(segundos);
            reloj.querySelector('[data-etiqueta-demora]').textContent = nivel === 'urgente' ? '15 min o más' : nivel === 'advertencia' ? '12 min o más' : 'En curso';
            reloj.classList.toggle('demora-amarilla', nivel === 'advertencia');
            reloj.classList.toggle('demora-roja', nivel === 'urgente');
            const tarjeta = reloj.closest('[data-pedido],tr,.fila-resumen,.comprobante');
            tarjeta?.classList.toggle('demora-amarilla', nivel === 'advertencia');
            tarjeta?.classList.toggle('demora-roja', nivel === 'urgente');
            if (nivel === 'normal') return;
            if (nivel === 'urgente') urgentes++; else advertencias++;
            const clave = reloj.dataset.demoraPedido + ':' + nivel;
            if (!avisosVisuales.has(clave)) {
                avisosVisuales.add(clave);
                mostrarAviso('Pedido #' + reloj.dataset.demoraPedido + ': ' + (nivel === 'urgente' ? 'lleva 15 minutos o más sin entregar.' : 'lleva 12 minutos o más sin entregar.'), nivel === 'urgente');
            }
            if (!avisosSonoros.has(clave)) nuevosAvisos.push({ clave, nivel });
        });
        resumenDemoras.textContent = advertencias || urgentes ? advertencias + ' en advertencia · ' + urgentes + ' urgentes' : '';
        if (nuevosAvisos.length && emitirSonido(nuevosAvisos.some(aviso => aviso.nivel === 'urgente') ? 'urgente' : 'nuevo')) {
            nuevosAvisos.forEach(aviso => avisosSonoros.add(aviso.clave));
            const ultimosAvisos = [...avisosSonoros].slice(-500);
            avisosSonoros = new Set(ultimosAvisos);
            try { sessionStorage.setItem('chifa.avisos_sonoros', JSON.stringify(ultimosAvisos)); } catch {}
        }
    }

    function mostrarConexion(mensaje, conectado = false) {
        estadoConexion.textContent = mensaje;
        estadoConexion.classList.toggle('conectado', conectado);
    }
    function pedirActualizacion() {
        pendiente = true;
        window.clearTimeout(programacion);
        programacion = window.setTimeout(sincronizar, 120);
    }
    async function sincronizar() {
        if (!pendiente || consultando || navegando || document.visibilityState !== 'visible') return;
        const foco = document.activeElement;
        if (contenido.contains(foco) && foco.matches('input,select,textarea')) return;
        pendiente = false;
        consultando = true;
        let sincronizacionCompleta = false;
        try {
            const respuesta = await fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store', headers: { Accept: 'text/html', 'X-Panel-Pedidos': '1' } });
            if (respuesta.redirected) { window.location.assign(respuesta.url); return; }
            if (!respuesta.ok) throw new Error('No se pudo recuperar el panel');
            const documento = new DOMParser().parseFromString(await respuesta.text(), 'text/html');
            const nuevoContenido = documento.querySelector('[data-contenido-pedidos]');
            if (!nuevoContenido) throw new Error('Panel no disponible');
            contenido.innerHTML = nuevoContenido.innerHTML;
            contenido.dataset.servidorAhora = nuevoContenido.dataset.servidorAhora;
            desfase = Number(nuevoContenido.dataset.servidorAhora) - Date.now();
            revisarRelojes();
            sincronizacionCompleta = true;
            if (canalConectado) mostrarConexion('● Conectado en tiempo real', true);
        } catch {
            pendiente = true;
            mostrarConexion('No se pudo sincronizar. Usa Actualizar o espera la reconexión.');
        } finally {
            consultando = false;
        }
        if (pendiente && sincronizacionCompleta) pedirActualizacion();
    }
    document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible') pedirActualizacion(); });
    document.addEventListener('focusout', () => { if (pendiente) window.setTimeout(sincronizar, 0); });
    document.addEventListener('submit', evento => { queueMicrotask(() => { if (!evento.defaultPrevented) navegando = true; }); });

    try {
        const echo = new Echo({
            broadcaster: 'reverb',
            key: barra.dataset.reverbKey,
            wsHost: barra.dataset.reverbHost,
            wsPort: Number(barra.dataset.reverbPort),
            wssPort: Number(barra.dataset.reverbPort),
            forceTLS: barra.dataset.reverbScheme === 'https',
            enabledTransports: ['ws', 'wss'],
            authEndpoint: '/broadcasting/auth',
            auth: { headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content } },
        });
        echo.connector.pusher.connection.bind('state_change', cambio => {
            if (cambio.current === 'connected') mostrarConexion('Autorizando avisos...');
            else if (['disconnected', 'unavailable', 'failed'].includes(cambio.current)) { canalConectado = false; mostrarConexion('Sin conexión de avisos · Reconectando…'); }
            else mostrarConexion('Conectando avisos…');
        });
        echo.private('restaurante.pedidos')
            .subscribed(() => { canalConectado = true; mostrarConexion('● Conectado en tiempo real', true); pedirActualizacion(); })
            .error(() => { canalConectado = false; mostrarConexion('No se pudo autorizar el canal de pedidos. Vuelve a ingresar.'); })
            .listen('.pedidos.actualizados', evento => {
                if (eventosRecibidos.has(evento.evento)) return;
                eventosRecibidos.add(evento.evento);
                barra.querySelector('[data-ultimo-aviso]').textContent = 'Último aviso: ' + new Date().toLocaleTimeString('es-PE');
                if (eventosRecibidos.size > 200) eventosRecibidos.delete(eventosRecibidos.values().next().value);
                const mensajes = {
                    creado: 'Nuevo pedido #' + evento.pedido_id + '.',
                    modificado: 'Pedido #' + evento.pedido_id + ' actualizado. Revisa los cambios en su detalle.',
                    anulado: 'Pedido #' + evento.pedido_id + ' anulado. No continuar su preparación.',
                };
                const existente = [...contenido.querySelectorAll('[data-pedido]')].find(tarjeta => tarjeta.dataset.pedido === String(evento.pedido_id));
                const repetido = existente && Number(existente.dataset.version) >= evento.version && ['creado', 'modificado'].includes(evento.tipo);
                if (mensajes[evento.tipo] && !repetido) {
                    mostrarAviso(mensajes[evento.tipo]);
                    emitirSonido(evento.tipo === 'modificado' ? 'modificado' : 'nuevo');
                }
                pedirActualizacion();
            });
        window.addEventListener('pagehide', () => echo.disconnect(), { once: true });
    } catch {
        mostrarConexion('Avisos no disponibles. Puedes actualizar la pantalla manualmente.');
    }
    revisarRelojes();
    window.setInterval(revisarRelojes, 1000);
}
