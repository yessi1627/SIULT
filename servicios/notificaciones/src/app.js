import express from 'express';
import mongoose from 'mongoose';
import { Notificacion, aRespuesta, filtroPara } from './modelo.js';

/**
 * API HTTP del microservicio. No maneja sesiones: confia en el API Gateway, que valida la sesion
 * con el backend PHP y le pasa el usuario en las cabeceras X-Usuario-Id, X-Usuario-Rol y
 * X-Usuario-Materias (el gateway borra esas cabeceras si vienen del navegador).
 */
export function crearApp({ redis, cola, estadoConsumidor }) {
  const app = express();
  app.use(express.json());

  // Datos del usuario que envia el gateway
  const usuarioDe = (req) => ({
    id: Number(req.get('x-usuario-id')),
    rol: req.get('x-usuario-rol') ?? '',
    materias: (req.get('x-usuario-materias') ?? '')
      .split(',')
      .filter(Boolean)
      .map(Number),
  });

  const exigirUsuario = (req, res, next) => {
    const usuario = usuarioDe(req);
    if (!usuario.id) {
      return res.status(401).json({ data: null, error: 'Debe iniciar sesion' });
    }
    req.usuario = usuario;
    next();
  };

  // GET /notificaciones?limite=15
  app.get('/notificaciones', exigirUsuario, async (req, res) => {
    const limite = Math.min(Math.max(Number(req.query.limite) || 15, 1), 100);
    const docs = await Notificacion.find(filtroPara(req.usuario)).sort({ fecha: -1 }).limit(limite).lean();
    res.json({ data: docs.map((d) => aRespuesta(d, req.usuario.id)), error: null });
  });

  // PATCH /notificaciones/leidas: marco como leidas todas las que el usuario puede ver
  app.patch('/notificaciones/leidas', exigirUsuario, async (req, res) => {
    const resultado = await Notificacion.updateMany(filtroPara(req.usuario), { $addToSet: { leidaPor: req.usuario.id } });
    res.json({ data: { actualizadas: resultado.modifiedCount }, error: null });
  });

  // PATCH /notificaciones/:id/leida
  app.patch('/notificaciones/:id/leida', exigirUsuario, async (req, res) => {
    if (!mongoose.isValidObjectId(req.params.id)) {
      return res.status(404).json({ data: null, error: 'La notificacion no existe' });
    }
    const doc = await Notificacion.findOneAndUpdate(
      { _id: req.params.id, ...filtroPara(req.usuario) },
      { $addToSet: { leidaPor: req.usuario.id } },
      { new: true },
    ).lean();
    if (!doc) {
      return res.status(404).json({ data: null, error: 'La notificacion no existe' });
    }
    res.json({ data: aRespuesta(doc, req.usuario.id), error: null });
  });

  // GET /salud: estado de MongoDB, Redis y del consumidor de la cola
  app.get('/salud', async (_req, res) => {
    const mongo = mongoose.connection.readyState === 1 ? 'ok' : 'sin conexion';
    let colaPendiente = null;
    try {
      colaPendiente = await redis.llen(cola);
    } catch {
      // Redis caido: lo informo abajo
    }
    const estado = mongo === 'ok' && colaPendiente !== null ? 'ok' : 'degradado';
    res.status(estado === 'ok' ? 200 : 503).json({
      data: {
        servicio: 'notificaciones',
        estado,
        mongo,
        redis: colaPendiente !== null ? 'ok' : 'sin conexion',
        cola_pendiente: colaPendiente,
        procesados: estadoConsumidor.procesados,
        errores: estadoConsumidor.errores,
        ultimo_procesado: estadoConsumidor.ultimo,
      },
      error: null,
    });
  });

  // Cualquier error no controlado responde JSON
  app.use((error, _req, res, _next) => {
    console.error('[notificaciones]', error);
    res.status(500).json({ data: null, error: 'Error interno del servicio de notificaciones' });
  });

  return app;
}
