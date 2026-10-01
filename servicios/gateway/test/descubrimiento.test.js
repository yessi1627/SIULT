import assert from 'node:assert/strict';
import { test } from 'node:test';
import { cabecerasDeUsuario } from '../src/autenticacion.js';
import { crearDescubrimiento, elegirPorTurno } from '../src/descubrimiento.js';

test('reparte las peticiones por turnos entre las instancias (round-robin)', () => {
  const turnos = new Map();
  const instancias = [{ instancia: 'b' }, { instancia: 'a' }];
  const elegidas = [1, 2, 3, 4].map(() => elegirPorTurno(instancias, 'calendario', turnos).instancia);
  assert.deepEqual(elegidas, ['a', 'b', 'a', 'b']);
});

test('sin instancias registradas devuelve null', () => {
  assert.equal(elegirPorTurno([], 'x', new Map()), null);
});

test('resuelve un servicio leyendo el registro de Redis', async () => {
  const registro = {
    'servicio:calendario:c1': JSON.stringify({ nombre: 'calendario', instancia: 'c1', url: 'http://127.0.0.1:3002' }),
    'servicio:api-php:apache': JSON.stringify({ nombre: 'api-php', instancia: 'apache', url: 'http://localhost' }),
  };
  const redis = {
    scan: async () => ['0', Object.keys(registro)],
    mget: async (...claves) => claves.map((c) => registro[c]),
  };
  const descubrimiento = crearDescubrimiento(redis);
  assert.equal((await descubrimiento.resolver('calendario')).url, 'http://127.0.0.1:3002');
  assert.equal(await descubrimiento.resolver('notificaciones'), null);
});

test('arma las cabeceras de usuario para los microservicios', () => {
  assert.deepEqual(cabecerasDeUsuario({ id: 41, rol: 'ESTUDIANTE', materias: [3, 4] }), {
    'x-usuario-id': '41',
    'x-usuario-rol': 'ESTUDIANTE',
    'x-usuario-materias': '3,4',
  });
});
