import assert from 'node:assert/strict';
import { once } from 'node:events';
import express from 'express';
import { after, before, describe, test } from 'node:test';
import { crearApp } from '../src/app.js';


async function escuchar(app) {
  const servidor = app.listen(0, '127.0.0.1');
  await once(servidor, 'listening');
  return { servidor, url: `http://127.0.0.1:${servidor.address().port}` };
}

// Servidor de prueba que responde quien es y que recibio
function servicioEco(nombre) {
  const app = express();
  app.get('/proyectoGestorEscolar/api/auth/me', (req, res) => {
    if (!req.headers.cookie?.includes('PHPSESSID=valida')) return res.status(401).json({ data: null });
    res.json({ data: { id: 41, rol: { nombre: 'ESTUDIANTE' }, materias: [3, 4] } });
  });
  app.use((req, res) => res.json({ nombre, ruta: req.originalUrl, usuario: req.headers['x-usuario-id'] ?? null }));
  return app;
}

describe('API Gateway de punta a punta', () => {
  const servidores = [];
  let gateway;

  before(async () => {
    const api = await escuchar(servicioEco('api-php'));
    const notificaciones = await escuchar(servicioEco('notificaciones'));
    const calendario1 = await escuchar(servicioEco('calendario-1'));
    const calendario2 = await escuchar(servicioEco('calendario-2'));
    servidores.push(api, notificaciones, calendario1, calendario2);

    // Registro de servicios en memoria (en produccion lo lee de Redis)
    const registro = [
      { nombre: 'api-php', instancia: 'apache', url: api.url, rutaBase: '/proyectoGestorEscolar/api' },
      { nombre: 'notificaciones', instancia: 'n1', url: notificaciones.url, rutaBase: '' },
      { nombre: 'calendario', instancia: 'c1', url: calendario1.url, rutaBase: '' },
      { nombre: 'calendario', instancia: 'c2', url: calendario2.url, rutaBase: '' },
    ];
    const turnos = new Map();
    const descubrimiento = {
      listar: async () => registro,
      olvidarCache: () => {},
      resolver: async (nombre) => {
        const instancias = registro.filter((s) => s.nombre === nombre);
        if (instancias.length === 0) return null;
        const turno = turnos.get(nombre) ?? 0;
        turnos.set(nombre, turno + 1);
        return instancias[turno % instancias.length];
      },
    };
    const config = { origenes: ['http://localhost:4200'], timeoutMs: 2000 };
    gateway = await escuchar(crearApp({ descubrimiento, config }));
  });

  after(() => [...servidores, gateway].forEach(({ servidor }) => servidor.close()));

  test('/api se reenvia al backend PHP con su ruta base', async () => {
    const respuesta = await fetch(`${gateway.url}/api/materias?activas=1`);
    assert.equal(respuesta.headers.get('x-servicio'), 'api-php/apache');
    assert.deepEqual(await respuesta.json(), { nombre: 'api-php', ruta: '/proyectoGestorEscolar/api/materias?activas=1', usuario: null });
  });

  test('reparte las peticiones entre dos instancias de calendario (round-robin)', async () => {
    const atendidas = [];
    for (let i = 0; i < 4; i++) atendidas.push((await (await fetch(`${gateway.url}/calendario/festivos/2026`)).json()).nombre);
    assert.deepEqual(atendidas, ['calendario-1', 'calendario-2', 'calendario-1', 'calendario-2']);
  });

  test('/notificaciones sin sesion responde 401 y no llega al microservicio', async () => {
    const respuesta = await fetch(`${gateway.url}/notificaciones`);
    assert.equal(respuesta.status, 401);
  });

  test('/notificaciones con sesion valida pasa el usuario en cabeceras', async () => {
    const respuesta = await fetch(`${gateway.url}/notificaciones`, {
      headers: { cookie: 'PHPSESSID=valida', 'x-usuario-id': '1' }, // intento hacerme pasar por el usuario 1
    });
    const cuerpo = await respuesta.json();
    assert.equal(cuerpo.nombre, 'notificaciones');
    assert.equal(cuerpo.usuario, '41'); // el gateway ignora la cabecera falsa y pone el usuario real
  });

  test('una ruta desconocida responde 404 en JSON', async () => {
    const respuesta = await fetch(`${gateway.url}/no-existe`);
    assert.equal(respuesta.status, 404);
    assert.equal((await respuesta.json()).data, null);
  });

  test('CORS solo permite el origen del frontend', async () => {
    const respuesta = await fetch(`${gateway.url}/api/materias`, { headers: { origin: 'http://localhost:4200' } });
    assert.equal(respuesta.headers.get('access-control-allow-origin'), 'http://localhost:4200');
    assert.equal(respuesta.headers.get('access-control-allow-credentials'), 'true');
  });
});

describe('API Gateway ante fallas de los servicios', () => {
  let gateway;
  before(async () => {
    // Calendario aparece registrado pero en un puerto donde no hay nadie; notificaciones no esta registrado
    const registro = [{ nombre: 'calendario', instancia: 'caido', url: 'http://127.0.0.1:9', rutaBase: '' }];
    const descubrimiento = {
      listar: async () => registro,
      olvidarCache: () => {},
      resolver: async (nombre) => registro.find((s) => s.nombre === nombre) ?? null,
    };
    gateway = await escuchar(crearApp({ descubrimiento, config: { origenes: [], timeoutMs: 2000 } }));
  });
  after(() => gateway.servidor.close());

  test('un servicio sin instancias registradas responde 503 de inmediato', async () => {
    const respuesta = await fetch(`${gateway.url}/api/materias`);
    assert.equal(respuesta.status, 503);
    assert.match((await respuesta.json()).error, /api-php no está disponible/);
  });

  test('un servicio registrado que no responde da 502 en vez de colgar al cliente', async () => {
    const respuesta = await fetch(`${gateway.url}/calendario/festivos/2026`);
    assert.equal(respuesta.status, 502);
  });
});
