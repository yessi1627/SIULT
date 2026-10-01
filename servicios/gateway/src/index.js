import { Redis } from 'ioredis';
import { crearApp } from './app.js';
import { config } from './config.js';
import { crearDescubrimiento } from './descubrimiento.js';
import { registrarApiPhp } from './registro-terceros.js';

const redis = new Redis(config.redisUrl, { maxRetriesPerRequest: 1 });
redis.on('error', (e) => console.warn(`[redis] ${e.message}`));

const descubrimiento = crearDescubrimiento(redis);
const detenerRegistro = registrarApiPhp(redis, config.apiPhp);
const app = crearApp({ descubrimiento, config });

const servidor = app.listen(config.puerto, () => {
  console.log(`[gateway] escuchando en http://localhost:${config.puerto}`);
});

function apagar() {
  console.log('[gateway] apagando…');
  detenerRegistro();
  servidor.close();
  redis.disconnect();
  process.exit(0);
}
process.on('SIGINT', apagar);
process.on('SIGTERM', apagar);
