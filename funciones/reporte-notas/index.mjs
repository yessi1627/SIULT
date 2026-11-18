import { aCsv, reporteCurso } from './reporte.mjs';

/**
 * FUNCION COMO SERVICIO (FaaS) – reporte de notas de un curso.
 *
 * Es un "handler" con el formato de AWS Lambda detras de un API Gateway HTTP (evento version 2.0):
 * recibe un evento con el cuerpo de la peticion y devuelve { statusCode, headers, body }.
 * No tiene servidor propio ni estado: el proveedor la ejecuta solo cuando llega una peticion,
 * crea tantas copias como peticiones simultaneas haya y cobra por milisegundo de ejecucion.
 *
 *   POST /reporte-notas            { "calificaciones": [ { "id_usuario": 7, "nombres": "Ana", "nota": 4.5 } ] }
 *   POST /reporte-notas?formato=csv  -> mismo reporte como archivo CSV
 *
 * Despliegue: template.yaml (AWS SAM). Prueba local sin nube: node local.mjs
 */
export async function handler(event) {
  if ((event.requestContext?.http?.method ?? 'POST') !== 'POST') {
    return json(405, { data: null, error: 'Use el metodo POST' });
  }

  let cuerpo;
  try {
    const texto = event.isBase64Encoded ? Buffer.from(event.body ?? '', 'base64').toString('utf8') : event.body;
    cuerpo = JSON.parse(texto ?? '{}');
  } catch {
    return json(400, { data: null, error: 'El cuerpo no es un JSON valido' });
  }

  const { calificaciones } = cuerpo;
  if (!Array.isArray(calificaciones) || calificaciones.some((c) => c?.id_usuario === undefined)) {
    return json(422, { data: null, error: 'Envie "calificaciones": [{ id_usuario, nombres, nota }]' });
  }
  if (calificaciones.length > 50_000) {
    return json(413, { data: null, error: 'Maximo 50.000 calificaciones por reporte' });
  }

  const reporte = reporteCurso(calificaciones);

  if (event.queryStringParameters?.formato === 'csv') {
    return {
      statusCode: 200,
      headers: {
        'content-type': 'text/csv; charset=utf-8',
        'content-disposition': 'attachment; filename="reporte-notas.csv"',
      },
      // BOM al inicio para que Excel reconozca las tildes
      body: '﻿' + aCsv(reporte.estudiantes),
    };
  }
  return json(200, { data: reporte, error: null });
}

function json(statusCode, cuerpo) {
  return { statusCode, headers: { 'content-type': 'application/json; charset=utf-8' }, body: JSON.stringify(cuerpo) };
}
