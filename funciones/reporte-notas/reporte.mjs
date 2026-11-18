// Logica del reporte de notas: solo funciones puras (sin red, sin base de datos, sin estado).
// Por eso encaja en una funcion serverless: cada invocacion recibe los datos y devuelve el resultado.

export const NOTA_MINIMA = 3.0;

const RANGOS = [
  { minimo: 4.6, nivel: 'Superior' },
  { minimo: 4.0, nivel: 'Alto' },
  { minimo: 3.0, nivel: 'Básico' },
  { minimo: 0, nivel: 'Bajo' },
];

export const esNotaValida = (n) => typeof n === 'number' && Number.isFinite(n) && n >= 0 && n <= 5;

export const desempeno = (nota) => (esNotaValida(nota) ? RANGOS.find((r) => nota >= r.minimo).nivel : null);

const redondear = (n) => Math.round(n * 100) / 100;

export function resumen(notas) {
  const validas = notas.map(Number).filter(esNotaValida);
  if (validas.length === 0) return { cantidad: 0, promedio: null, aprueba: false, desempeno: null };
  const promedio = redondear(validas.reduce((a, n) => a + n, 0) / validas.length);
  return { cantidad: validas.length, promedio, aprueba: promedio >= NOTA_MINIMA, desempeno: desempeno(promedio) };
}

// Agrupo las calificaciones por estudiante y calculo su resumen (sin modificar la entrada)
export function reportePorEstudiante(calificaciones) {
  const grupos = calificaciones.reduce((acc, { id_usuario, nombres = '', nota }) => {
    const actual = acc.get(id_usuario) ?? { id_usuario, nombres, notas: [] };
    return new Map(acc).set(id_usuario, { ...actual, notas: [...actual.notas, nota] });
  }, new Map());

  return [...grupos.values()]
    .map(({ id_usuario, nombres, notas }) => ({ id_usuario, nombres, ...resumen(notas) }))
    .sort((a, b) => (b.promedio ?? -1) - (a.promedio ?? -1));
}

export function reporteCurso(calificaciones) {
  const estudiantes = reportePorEstudiante(calificaciones);
  const conNota = estudiantes.filter((e) => e.promedio !== null);
  return {
    estudiantes,
    curso: {
      ...resumen(conNota.map((e) => e.promedio)),
      aprobados: conNota.filter((e) => e.aprueba).length,
      reprobados: conNota.filter((e) => !e.aprueba).length,
    },
  };
}

// CSV con separador ";" para que Excel en español lo abra en columnas
export function aCsv(estudiantes) {
  const escapar = (v) => `"${String(v ?? '').replaceAll('"', '""')}"`;
  const filas = estudiantes.map((e) =>
    [e.id_usuario, e.nombres, e.cantidad, e.promedio ?? '', e.desempeno ?? '', e.aprueba ? 'Sí' : 'No'].map(escapar).join(';'),
  );
  return ['"id";"estudiante";"notas";"promedio";"desempeño";"aprueba"', ...filas].join('\r\n');
}
