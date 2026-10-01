import cors from 'cors';
import express from 'express';
import { crearValidadorSesion } from './autenticacion.js';
import { proxyHacia } from './proxy.js';

/**
 * API GATEWAY: una sola direccion de entrada (http://localhost:8080) que reparte cada peticion
 * al servicio que corresponde, como una recepcionista:
 *   /api/*             -> backend PHP (Apache)                 [sesion validada por el propio PHP]
 *   /uploads/*         -> archivos subidos (Apache)
 *   /notificaciones/*  -> microservicio de notificaciones      [sesion validada por el gateway]
 *   /calendario/*      -> microservicio de calendario
 *   /salud             -> estado de todos los servicios registrados
 * Centraliza CORS, el registro de peticiones (logs), los tiempos maximos y la validacion de sesion.
 */
export function crearApp({ descubrimiento, config }) {
  const app = express();
  app.disable('x-powered-by');

  app.use(
    cors({
      origin: config.origenes,
      credentials: true,
      methods: ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
      allowedHeaders: ['Content-Type', 'X-Requested-With'],
      exposedHeaders: ['X-Cache', 'X-Servicio'],
    }),
  );

  // Registro de cada peticion con su duracion
  app.use((req, res, next) => {
    const inicio = performance.now();
    res.on('finish', () => {
      console.log(`[gateway] ${req.method} ${req.originalUrl} -> ${res.statusCode} (${Math.round(performance.now() - inicio)} ms)`);
    });
    next();
  });

  const opciones = { timeoutMs: config.timeoutMs };
  const exigirSesion = crearValidadorSesion(descubrimiento);

  app.use('/api', ...proxyHacia('api-php', descubrimiento, opciones));
  app.use('/uploads', ...proxyHacia('api-php', descubrimiento, { ...opciones, rutaDe: (d) => d.rutaUploads }));
  app.use('/notificaciones', exigirSesion, ...proxyHacia('notificaciones', descubrimiento, opciones));
  app.use('/calendario', ...proxyHacia('calendario', descubrimiento, opciones));

  // Servicios registrados (para mostrar el Service Discovery en la sustentacion)
  app.get('/registro', async (_req, res) => {
    descubrimiento.olvidarCache();
    res.json({ data: await descubrimiento.listar(), error: null });
  });

  // Salud de todo el sistema: consulto en paralelo el /salud de cada servicio registrado
  app.get('/salud', async (_req, res) => {
    descubrimiento.olvidarCache();
    const servicios = await descubrimiento.listar().catch(() => []);
    const revisiones = await Promise.allSettled(
      servicios.map(async (s) => {
        const inicio = performance.now();
        const r = await fetch(s.salud, { signal: AbortSignal.timeout(3000) });
        const cuerpo = await r.json().catch(() => ({}));
        return { estado: cuerpo.data?.estado ?? (r.ok ? 'ok' : 'error'), latencia_ms: Math.round(performance.now() - inicio) };
      }),
    );
    const detalle = servicios.map((s, i) => ({
      nombre: s.nombre,
      instancia: s.instancia,
      url: s.url,
      ...(revisiones[i].status === 'fulfilled' ? revisiones[i].value : { estado: 'sin respuesta', latencia_ms: null }),
    }));
    const esperados = ['api-php', 'notificaciones', 'calendario'];
    const faltantes = esperados.filter((n) => !detalle.some((d) => d.nombre === n));
    const todoBien = faltantes.length === 0 && detalle.every((d) => d.estado === 'ok');
    res.json({
      data: { servicio: 'gateway', estado: todoBien ? 'ok' : 'degradado', servicios: detalle, sin_registrar: faltantes },
      error: null,
    });
  });

  app.use((_req, res) => res.status(404).json({ data: null, error: 'Ruta no encontrada en el gateway' }));
  return app;
}
