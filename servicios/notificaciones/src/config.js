// Configuracion del microservicio por variables de entorno (con valores por defecto para desarrollo local)
export const config = Object.freeze({
  nombre: 'notificaciones',
  host: process.env.HOST_PUBLICO ?? '127.0.0.1',
  puerto: Number(process.env.PUERTO ?? 3001),
  mongoUrl: process.env.MONGO_URL ?? 'mongodb://127.0.0.1:27017/siult',
  redisUrl: process.env.REDIS_URL ?? 'redis://127.0.0.1:6379/0',
  cola: process.env.COLA ?? 'cola:notificaciones',
});
