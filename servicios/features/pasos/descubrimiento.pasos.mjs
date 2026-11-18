import assert from 'node:assert/strict';
import { Given, Then, When } from '@cucumber/cucumber';
import { crearDescubrimiento } from '../../gateway/src/descubrimiento.js';

// Pasos de descubrimiento.feature: uso el Service Discovery real del gateway con un registro de Redis simulado en memoria
function redisEnMemoria(registro) {
  return {
    scan: async () => ['0', [...registro.keys()]],
    mget: async (...claves) => claves.map((c) => registro.get(c) ?? null),
  };
}

Given('que en el registro hay {int} instancias vivas del servicio {string}', function (cantidad, nombre) {
  this.registro = new Map();
  for (let i = 1; i <= cantidad; i++) {
    const instancia = `${nombre}-${i}`;
    this.registro.set(`servicio:${nombre}:${instancia}`, JSON.stringify({ nombre, instancia, url: `http://${instancia}:3000` }));
  }
  this.descubrimiento = crearDescubrimiento(redisEnMemoria(this.registro), { cacheMs: 0 });
});

Given('la instancia {string} deja de enviar latidos y su registro vence', function (instancia) {
  // Sin latidos, Redis borra la clave al cumplirse el TTL de 30 s
  const nombre = instancia.replace(/-\d+$/, '');
  this.registro.delete(`servicio:${nombre}:${instancia}`);
});

When(/^(?:llegan (\d+) peticiones|llega una petición) para "([^"]+)"$/, async function (cantidad, nombre) {
  const total = cantidad == null ? 1 : Number(cantidad);
  this.atendidas = [];
  for (let i = 0; i < total; i++) {
    const destino = await this.descubrimiento.resolver(nombre);
    this.atendidas.push(destino?.instancia ?? null);
  }
});

Then('cada instancia atiende {int} peticiones', function (cantidad) {
  const conteo = this.atendidas.reduce((acc, i) => ({ ...acc, [i]: (acc[i] ?? 0) + 1 }), {});
  assert.ok(Object.values(conteo).every((n) => n === cantidad), JSON.stringify(conteo));
});

Then('el gateway no encuentra a quién enviarla', function () {
  assert.deepEqual(this.atendidas, [null]);
});

Then('todas las peticiones las atiende {string}', function (instancia) {
  assert.ok(this.atendidas.every((i) => i === instancia), JSON.stringify(this.atendidas));
});
