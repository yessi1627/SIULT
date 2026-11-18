import assert from 'node:assert/strict';
import { test } from 'node:test';
import { handler } from '../index.mjs';
import { desempeno, reporteCurso } from '../reporte.mjs';

const calificaciones = [
  { id_usuario: 7, nombres: 'Ana', nota: 4.5 },
  { id_usuario: 9, nombres: 'Luis', nota: 2.0 },
  { id_usuario: 7, nombres: 'Ana', nota: 5.0 },
  { id_usuario: 9, nombres: 'Luis', nota: 3.0 },
];
const evento = (cuerpo, consulta) => ({ requestContext: { http: { method: 'POST' } }, body: JSON.stringify(cuerpo), queryStringParameters: consulta });

test('clasifica el desempeño con la escala del Decreto 1290', () => {
  assert.deepEqual([5, 4.6, 4.59, 4, 3, 2.99].map(desempeno), ['Superior', 'Superior', 'Alto', 'Alto', 'Básico', 'Bajo']);
  assert.equal(desempeno(7), null);
});

test('arma el reporte por estudiante ordenado por promedio', () => {
  const { estudiantes, curso } = reporteCurso(calificaciones);
  assert.deepEqual(estudiantes.map((e) => [e.nombres, e.promedio, e.desempeno]), [
    ['Ana', 4.75, 'Superior'],
    ['Luis', 2.5, 'Bajo'],
  ]);
  assert.equal(curso.aprobados, 1);
  assert.equal(curso.reprobados, 1);
});

test('no modifica las calificaciones que recibe (funcion pura)', () => {
  const copia = structuredClone(calificaciones);
  reporteCurso(calificaciones);
  assert.deepEqual(calificaciones, copia);
});

test('el handler responde 200 con el reporte en JSON', async () => {
  const respuesta = await handler(evento({ calificaciones }));
  assert.equal(respuesta.statusCode, 200);
  assert.equal(JSON.parse(respuesta.body).data.estudiantes.length, 2);
});

test('el handler entrega CSV con ?formato=csv', async () => {
  const respuesta = await handler(evento({ calificaciones }, { formato: 'csv' }));
  assert.match(respuesta.headers['content-type'], /text\/csv/);
  assert.match(respuesta.body, /"7";"Ana";"2";"4.75";"Superior";"Sí"/);
});

test('el handler rechaza datos invalidos', async () => {
  assert.equal((await handler({ body: '{malo' })).statusCode, 400);
  assert.equal((await handler(evento({ calificaciones: 'x' }))).statusCode, 422);
  assert.equal((await handler({ requestContext: { http: { method: 'GET' } } })).statusCode, 405);
});
