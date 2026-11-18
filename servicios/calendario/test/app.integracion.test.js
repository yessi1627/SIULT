import assert from 'node:assert/strict';
import { once } from 'node:events';
import { after, before, describe, test } from 'node:test';
import { crearApp } from '../src/app.js';
import { crearConsultaFestivos } from '../src/festivos.js';

/**
 * PRUEBAS DE INTEGRACION: a diferencia de festivos.test.js (unitarias), aqui levanto el servidor HTTP
 * real de Express en un puerto libre y lo llamo con fetch, igual que lo haria el gateway.
 * Se integran: rutas de Express + validacion + consulta de festivos + cache + manejo de errores.
 * Solo reemplazo lo externo (Redis y Nager.Date) para que la prueba no dependa de la red.
 */
function redisFalso() {
  const datos = new Map();
  return {
    get: async (k) => datos.get(k) ?? null,
    set: async (k, v) => void datos.set(k, v),
    ping: async () => 'PONG',
  };
}

async function levantar(fetchFn) {
  const redis = redisFalso();
  const consulta = crearConsultaFestivos({ redis, fetchFn });
  const servidor = crearApp({ consulta, redis }).listen(0, '127.0.0.1');
  await once(servidor, 'listening');
  return { servidor, url: `http://127.0.0.1:${servidor.address().port}` };
}

const nager = async () => ({
  ok: true,
  json: async () => [
    { date: '2026-12-25', localName: 'Navidad', name: 'Christmas Day' },
    { date: '2026-01-01', localName: 'Año Nuevo', name: "New Year's Day" },
  ],
});

describe('microservicio de calendario por HTTP', () => {
  let app;
  before(async () => (app = await levantar(nager)));
  after(() => app.servidor.close());

  test('GET /es-festivo responde que el 25 de diciembre es Navidad', async () => {
    const respuesta = await fetch(`${app.url}/es-festivo/2026-12-25`);
    assert.equal(respuesta.status, 200);
    const { data } = await respuesta.json();
    assert.deepEqual(data, { fecha: '2026-12-25', festivo: true, nombre: 'Navidad', fuente: 'nager' });
  });

  test('la segunda consulta del mismo año sale de la cache', async () => {
    const { data } = await (await fetch(`${app.url}/es-festivo/2026-03-10`)).json();
    assert.equal(data.festivo, false);
    assert.equal(data.fuente, 'cache');
  });

  test('GET /festivos/2026 devuelve la lista del año', async () => {
    const { data } = await (await fetch(`${app.url}/festivos/2026`)).json();
    assert.equal(data.festivos.length, 2);
  });

  test('una fecha mal escrita responde 422', async () => {
    const respuesta = await fetch(`${app.url}/es-festivo/25-12-2026`);
    assert.equal(respuesta.status, 422);
    assert.match((await respuesta.json()).error, /AAAA-MM-DD/);
  });

  test('un año fuera de rango responde 422', async () => {
    assert.equal((await fetch(`${app.url}/festivos/1800`)).status, 422);
  });

  test('/salud informa el estado del servicio', async () => {
    const { data } = await (await fetch(`${app.url}/salud`)).json();
    assert.equal(data.servicio, 'calendario');
    assert.equal(data.estado, 'ok');
  });
});

describe('calendario con Nager.Date caido y sin respaldo', () => {
  let app;
  before(async () => (app = await levantar(async () => Promise.reject(new Error('sin red')))));
  after(() => app.servidor.close());

  test('responde 503 en lugar de un error 500', async () => {
    const respuesta = await fetch(`${app.url}/es-festivo/2027-12-25`);
    assert.equal(respuesta.status, 503);
  });
});
