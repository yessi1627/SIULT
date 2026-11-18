import assert from 'node:assert/strict';
import { Before, Given, Then, When } from '@cucumber/cucumber';
import { buscarFestivo, crearConsultaFestivos } from '../../calendario/src/festivos.js';

// Pasos de festivos.feature: conectan cada frase en español con el codigo real del microservicio.
// Lo externo (Redis y Nager.Date) se reemplaza por dobles de prueba en memoria.

Before(function () {
  this.datos = new Map();
  this.redis = {
    get: async (k) => this.datos.get(k) ?? null,
    set: async (k, v) => void this.datos.set(k, v),
  };
  this.llamadasExternas = 0;
  this.externoCaido = false;
  this.festivosOficiales = [];
  this.fetchFn = async () => {
    this.llamadasExternas++;
    if (this.externoCaido) throw new Error('sin red');
    return {
      ok: true,
      json: async () => this.festivosOficiales.map((f) => ({ date: f.fecha, localName: f.nombre, name: f.nombre })),
    };
  };
  this.consulta = crearConsultaFestivos({ redis: this.redis, fetchFn: this.fetchFn });
});

Given('que el calendario oficial de Colombia para 2026 incluye:', function (tabla) {
  this.festivosOficiales = tabla.hashes();
});

async function consultarFecha(mundo, fecha) {
  const resultado = await mundo.consulta.festivos(Number(fecha.slice(0, 4)));
  mundo.ultimaFuente = resultado.fuente;
  mundo.respuesta = buscarFestivo(resultado.festivos, fecha);
}

When('el profesor elige la fecha de entrega {string}', async function (fecha) {
  await consultarFecha(this, fecha);
});

Given('que el profesor ya consultó la fecha {string}', async function (fecha) {
  await consultarFecha(this, fecha);
});

Given('la caché de 24 horas venció', function () {
  this.datos.delete('festivos:CO:2026');
});

Given(/^(?:que )?el servicio externo de festivos dejó de responder$/, function () {
  this.externoCaido = true;
});

When('se consultan los festivos de {int} {int} veces', async function (anio, veces) {
  this.llamadasExternas = 0;
  for (let i = 0; i < veces; i++) await this.consulta.festivos(anio).catch(() => {});
});

Then('el sistema indica que festivo es {string}', function (esperado) {
  assert.equal(this.respuesta.festivo, esperado === 'sí');
});

Then('el nombre del festivo es {string}', function (nombre) {
  assert.equal(this.respuesta.nombre ?? '-', nombre);
});

Then(/^el servicio externo de festivos se consultó (\d+) (?:vez|veces)$/, function (veces) {
  assert.equal(this.llamadasExternas, Number(veces));
});

Then('la última respuesta salió de la {string}', function (fuente) {
  assert.equal(this.ultimaFuente, fuente);
});

Then('el cortocircuito queda abierto', function () {
  assert.ok(this.consulta.circuito.abiertoHasta > Date.now());
});
