import assert from 'node:assert/strict';
import { test } from 'node:test';
import { aRespuesta, documentoDesdeMensaje, fechaTexto, filtroPara } from '../src/modelo.js';

test('convierte un mensaje de la cola en documento', () => {
  const doc = documentoDesdeMensaje({
    id_evento: 'abc',
    tipo: 'tarea_creada',
    mensaje: 'Nueva tarea',
    id_tarea: '7',
    id_materia: '3',
    fecha: '2026-10-02 10:30:00',
  });
  assert.equal(doc.idTarea, 7);
  assert.equal(doc.idMateria, 3);
  assert.equal(fechaTexto(doc.fecha), '2026-10-02 10:30:00');
});

test('rechaza mensajes incompletos', () => {
  assert.throws(() => documentoDesdeMensaje({ mensaje: 'sin evento' }), /incompleto/);
});

test('el estudiante solo ve sus materias; profesor y admin ven todo', () => {
  assert.deepEqual(filtroPara({ rol: 'ESTUDIANTE', materias: [3, 4] }), { idMateria: { $in: [3, 4] } });
  assert.deepEqual(filtroPara({ rol: 'PROFESOR', materias: [] }), {});
});

test('marca como leida solo para el usuario que la leyo', () => {
  const doc = { _id: 'x1', tipo: 't', mensaje: 'm', idTarea: 1, idMateria: 3, fecha: new Date(2026, 0, 5, 8, 0, 0), leidaPor: [41] };
  assert.equal(aRespuesta(doc, 41).leida, true);
  assert.equal(aRespuesta(doc, 39).leida, false);
  assert.equal(aRespuesta(doc, 41).fecha, '2026-01-05 08:00:00');
});
