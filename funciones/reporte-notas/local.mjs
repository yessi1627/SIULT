// Emulador local: hace el papel del API Gateway de AWS para probar la funcion sin desplegarla.
// Por cada peticion HTTP arma el evento que enviaria AWS,
import { createServer } from 'node:http';
import { handler } from './index.mjs';

const PUERTO = Number(process.env.PUERTO ?? 3003);

createServer(async (req, res) => {
  const url = new URL(req.url, `http://${req.headers.host}`);
  if (url.pathname !== '/reporte-notas') {
    res.writeHead(404, { 'content-type': 'application/json' });
    return res.end(JSON.stringify({ data: null, error: 'Ruta no encontrada' }));
  }

  const partes = [];
  for await (const parte of req) partes.push(parte);

  const inicio = performance.now();
  const respuesta = await handler({
    version: '2.0',
    rawPath: url.pathname,
    queryStringParameters: Object.fromEntries(url.searchParams),
    requestContext: { http: { method: req.method } },
    body: Buffer.concat(partes).toString('utf8'),
    isBase64Encoded: false,
  });
  const duracion = (performance.now() - inicio).toFixed(1);

  console.log(`[faas] ${req.method} ${req.url} -> ${respuesta.statusCode} (${duracion} ms facturables)`);
  res.writeHead(respuesta.statusCode, respuesta.headers);
  res.end(respuesta.body);
}).listen(PUERTO, () => console.log(`[faas] reporte-notas en http://localhost:${PUERTO}/reporte-notas`));
