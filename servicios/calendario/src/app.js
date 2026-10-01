import express from 'express';
import { ServicioNoDisponible, buscarFestivo, fechaValida } from './festivos.js';

export function crearApp({ consulta, redis }) {
  const app = express();

  // GET /festivos/2026
  app.get('/festivos/:anio', async (req, res) => {
    const anio = Number(req.params.anio);
    if (!Number.isInteger(anio) || anio < 2000 || anio > 2100) {
      return res.status(422).json({ data: null, error: 'El año debe estar entre 2000 y 2100' });
    }
    try {
      res.json({ data: await consulta.festivos(anio), error: null });
    } catch (error) {
      responderError(res, error);
    }
  });

  // GET /es-festivo/2026-12-25
  app.get('/es-festivo/:fecha', async (req, res) => {
    const { fecha } = req.params;
    if (!fechaValida(fecha)) {
      return res.status(422).json({ data: null, error: 'La fecha debe tener el formato AAAA-MM-DD' });
    }
    try {
      const { festivos, fuente } = await consulta.festivos(Number(fecha.slice(0, 4)));
      res.json({ data: { ...buscarFestivo(festivos, fecha), fuente }, error: null });
    } catch (error) {
      responderError(res, error);
    }
  });

  app.get('/salud', async (_req, res) => {
    let redisEstado = 'ok';
    try {
      await redis.ping();
    } catch {
      redisEstado = 'sin conexion';
    }
    const abierto = Date.now() < consulta.circuito.abiertoHasta;
    res.json({
      data: {
        servicio: 'calendario',
        // Sin Redis o con Nager.Date caido sigo respondiendo (degradado), por eso no devuelvo 503
        estado: redisEstado === 'ok' && !abierto ? 'ok' : 'degradado',
        redis: redisEstado,
        nager_date: abierto ? 'circuito abierto' : 'disponible',
        fallos_seguidos: consulta.circuito.fallos,
      },
      error: null,
    });
  });

  return app;
}

function responderError(res, error) {
  if (error instanceof ServicioNoDisponible) {
    return res.status(503).json({ data: null, error: error.message });
  }
  console.error('[calendario]', error);
  res.status(500).json({ data: null, error: 'Error interno del servicio de calendario' });
}
