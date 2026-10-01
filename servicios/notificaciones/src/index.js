import { Redis } from 'ioredis';
import mongoose from 'mongoose';
import { crearApp } from './app.js';
import { config } from './config.js';
import { iniciarConsumidor } from './consumidor.js';
import { registrarServicio } from './registro.js';

// Dos conexiones a Redis: una normal (registro, salud) y otra exclusiva para BRPOP, que bloquea
const redis = new Redis(config.redisUrl, { maxRetriesPerRequest: 1 });
const redisCola = new Redis(config.redisUrl, { maxRetriesPerRequest: null });
redis.on('error', (e) => console.warn(`[redis] ${e.message}`));
redisCola.on('error', (e) => console.warn(`[redis-cola] ${e.message}`));

// Si MongoDB no esta listo, mongoose reintenta solo; el consumidor devuelve los mensajes a la cola
mongoose.connection.on('connected', () => console.log('[mongo] conectado'));
mongoose.connection.on('disconnected', () => console.warn('[mongo] desconectado'));
mongoose.connect(config.mongoUrl, { serverSelectionTimeoutMS: 5000 }).catch((e) => console.warn(`[mongo] ${e.message}`));

const estadoConsumidor = iniciarConsumidor(redisCola, config.cola);
const app = crearApp({ redis, cola: config.cola, estadoConsumidor });

const servidor = app.listen(config.puerto, () => {
  console.log(`[notificaciones] escuchando en http://${config.host}:${config.puerto}`);
});
const darDeBaja = registrarServicio(redis, {
  nombre: config.nombre,
  host: config.host,
  puerto: config.puerto,
  // El gateway recibe /notificaciones/... y quita el prefijo; aqui las rutas empiezan por /notificaciones
  rutaBase: '/notificaciones',
});

// Apagado ordenado: dejo de consumir, me doy de baja del registro y cierro conexiones
async function apagar() {
  console.log('[notificaciones] apagando…');
  estadoConsumidor.activo = false;
  await darDeBaja();
  servidor.close();
  redisCola.disconnect();
  redis.disconnect();
  await mongoose.disconnect();
  process.exit(0);
}
process.on('SIGINT', apagar);
process.on('SIGTERM', apagar);
