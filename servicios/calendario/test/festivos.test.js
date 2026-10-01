import assert from 'node:assert/strict';
import { test } from 'node:test';
import { ServicioNoDisponible, buscarFestivo, crearConsultaFestivos } from '../src/festivos.js';

// Redis falso en memoria para probar sin depender del contenedor
function redisFalso() {
  const datos = new Map();
  return {
    datos,
    get: async (k) => datos.get(k) ?? null,
    set: async (k, v) => void datos.set(k, v),
  };
}
const respuestaNager = [{ date: '2026-12-25', localName: 'Navidad', name: 'Christmas Day' }];
const fetchExitoso = async () => ({ ok: true, json: async () => respuestaNager });
const fetchCaido = async () => {
  throw new Error('sin red');
};

test('la primera consulta va a Nager.Date y la segunda sale de la cache', async () => {
  let llamadas = 0;
  const consulta = crearConsultaFestivos({ redis: redisFalso(), fetchFn: async (...a) => (llamadas++, fetchExitoso(...a)) });
  assert.equal((await consulta.festivos(2026)).fuente, 'nager');
  assert.equal((await consulta.festivos(2026)).fuente, 'cache');
  assert.equal(llamadas, 1);
});

test('si Nager.Date falla y la cache vencio, usa la copia de respaldo', async () => {
  const redis = redisFalso();
  await crearConsultaFestivos({ redis, fetchFn: fetchExitoso }).festivos(2026);
  redis.datos.delete('festivos:CO:2026'); // simulo que vencieron las 24 h
  const r = await crearConsultaFestivos({ redis, fetchFn: fetchCaido }).festivos(2026);
  assert.equal(r.fuente, 'respaldo');
  assert.equal(r.festivos[0].nombre, 'Navidad');
});

test('sin cache ni respaldo responde "no disponible" en vez de romperse', async () => {
  await assert.rejects(crearConsultaFestivos({ redis: redisFalso(), fetchFn: fetchCaido }).festivos(2030), ServicioNoDisponible);
});

test('el cortocircuito se abre tras 3 fallos y deja de llamar a Nager.Date', async () => {
  let llamadas = 0;
  const consulta = crearConsultaFestivos({ redis: redisFalso(), fetchFn: async () => (llamadas++, fetchCaido()) });
  for (let i = 0; i < 5; i++) await consulta.festivos(2031).catch(() => {});
  assert.equal(llamadas, 3);
  assert.ok(consulta.circuito.abiertoHasta > Date.now());
});

test('busca si una fecha es festivo', () => {
  const lista = [{ fecha: '2026-12-25', nombre: 'Navidad' }];
  assert.deepEqual(buscarFestivo(lista, '2026-12-25'), { fecha: '2026-12-25', festivo: true, nombre: 'Navidad' });
  assert.equal(buscarFestivo(lista, '2026-12-24').festivo, false);
});
