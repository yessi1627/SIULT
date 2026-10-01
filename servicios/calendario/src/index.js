import { Redis } from 'ioredis';
import { crearApp } from './app.js';
import { crearConsultaFestivos } from './festivos.js';
import { registrarServicio } from './registro.js';

const config = Object.freeze({
  nombre: 'calendario',
  host: process.env.HOST_PUBLICO ?? '127.0.0.1',
  puerto: Number(process.env.PUERTO ?? 3002),
  redisUrl: process.env.REDIS_URL ?? 'redis://127.0.0.1:6379/0',
});

const redis = new Redis(config.redisUrl, { maxRetriesPerRequest: 1 });
redis.on('error', (e) => console.warn(`[redis] ${e.message}`));

const consulta = crearConsultaFestivos({ redis });
const app = crearApp({ consulta, redis });

const servidor = app.listen(config.puerto, () => {
  console.log(`[calendario] escuchando en http://${config.host}:${config.puerto}`);
});
const darDeBaja = registrarServicio(redis, { nombre: config.nombre, host: config.host, puerto: config.puerto });

async function apagar() {
  console.log('[calendario] apagando…');
  await darDeBaja();
  servidor.close();
  redis.disconnect();
  process.exit(0);
}
process.on('SIGINT', apagar);
process.on('SIGTERM', apagar);
