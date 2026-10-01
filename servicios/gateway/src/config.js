// Configuracion del API Gateway por variables de entorno (valores por defecto para desarrollo local)
export const config = Object.freeze({
  puerto: Number(process.env.PUERTO ?? 8080),
  redisUrl: process.env.REDIS_URL ?? 'redis://127.0.0.1:6379/0',
  // Origenes del frontend que pueden llamar al gateway con la cookie de sesion
  origenes: (process.env.ORIGENES ?? 'http://localhost:4200,http://127.0.0.1:4200').split(','),
  // Backend PHP (Apache de XAMPP). No puede auto-registrarse, por eso lo registra el gateway (ver registro-terceros.js)
  apiPhp: {
    url: process.env.API_PHP_URL ?? 'http://localhost',
    rutaBase: process.env.API_PHP_RUTA ?? '/proyectoGestorEscolar/api',
    rutaUploads: process.env.API_PHP_UPLOADS ?? '/proyectoGestorEscolar/config/uploads',
  },
  // Tiempo maximo que espero la respuesta de un servicio antes de responder 504
  timeoutMs: Number(process.env.TIMEOUT_MS ?? 15_000),
});
