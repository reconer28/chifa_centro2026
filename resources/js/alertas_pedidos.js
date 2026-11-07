export function segundosTranscurridos(inicio, ahora) {
    if (!Number.isFinite(inicio) || !Number.isFinite(ahora)) return 0;
    return Math.max(0, Math.floor((ahora - inicio) / 1000));
}
export function nivelDemora(segundos, amarillo = 12, rojo = 15) {
    if (segundos >= rojo * 60) return 'urgente';
    if (segundos >= amarillo * 60) return 'advertencia';
    return 'normal';
}
export function textoDuracion(segundos) {
    const valor = Math.max(0, Math.floor(segundos));
    return String(Math.floor(valor / 60)).padStart(2, '0') + ':' + String(valor % 60).padStart(2, '0');
}
