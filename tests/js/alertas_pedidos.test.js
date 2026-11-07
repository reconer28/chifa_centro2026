import test from 'node:test';
import assert from 'node:assert/strict';
import { nivelDemora, segundosTranscurridos, textoDuracion } from '../../resources/js/alertas_pedidos.js';
test('la advertencia empieza exactamente a los doce minutos y la urgencia a los quince', () => {
    assert.equal(nivelDemora(719), 'normal');
    assert.equal(nivelDemora(720), 'advertencia');
    assert.equal(nivelDemora(899), 'advertencia');
    assert.equal(nivelDemora(900), 'urgente');
    assert.equal(nivelDemora(3600), 'urgente');
});
test('un reloj local atrasado o una fecha inválida no producen demoras negativas', () => {
    assert.equal(segundosTranscurridos(2000, 1000), 0);
    assert.equal(segundosTranscurridos(NaN, 1000), 0);
    assert.equal(segundosTranscurridos(1000, 721999), 720);
});
test('la duración conserva minutos largos y muestra segundos completos', () => {
    assert.equal(textoDuracion(0), '00:00');
    assert.equal(textoDuracion(725), '12:05');
    assert.equal(textoDuracion(3903), '65:03');
});
