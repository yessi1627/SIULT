
import { hostname, networkInterfaces } from 'node:os';

// Direccion con la que el servicio se anuncia en el registro. Con HOST_PUBLICO=auto (Docker y Kubernetes)
// uso la IP del contenedor o del pod, que es distinta en cada replica y el gateway puede alcanzar.
export function direccionPublica(valor = '127.0.0.1') {
  if (valor !== 'auto') return valor;
  const ipv4 = Object.values(networkInterfaces()).flat().find((i) => i?.family === 'IPv4' && !i.internal);
  return ipv4?.address ?? hostname();
}

export function registrarServicio(redis, { nombre, host, puerto, rutaBase = '', intervaloMs = 10_000, ttlSeg = 30 }) {
  // En un contenedor el PID siempre es 1,  el hostname (id del contenedor o nombre del pod) es unico
  const instancia = `${nombre}-${hostname()}-${process.pid}`;
  const clave = `servicio:${nombre}:${instancia}`;
  const datos = JSON.stringify({
    nombre,
    instancia,
    url: `http://${host}:${puerto}`,
    rutaBase,
    salud: `http://${host}:${puerto}/salud`,
    inicio: new Date().toISOString(),
  });

  const latido = () =>
    redis.set(clave, datos, 'EX', ttlSeg).catch((error) => console.warn(`[registro] no se pudo renovar ${clave}: ${error.message}`));

  latido();
  const temporizador = setInterval(latido, intervaloMs);

  return async () => {
    clearInterval(temporizador);
    await redis.del(clave).catch(() => {});
  };
}
