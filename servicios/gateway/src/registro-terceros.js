/**
 * REGISTRO POR TERCEROS: el backend PHP corre en Apache y no tiene un proceso propio que envie latidos.
 * Por eso el gateway lo vigila: cada 10 s consulta su /salud y, si responde, renueva su registro en
 * Redis (TTL 30 s). Si Apache se cae, el registro vence y el gateway responde 503 en vez de esperar.
 * Es el mismo patron que usan herramientas como Registrator o los sidecars en Kubernetes.
 */
export function registrarApiPhp(redis, apiPhp, { intervaloMs = 10_000, ttlSeg = 30 } = {}) {
  const clave = 'servicio:api-php:apache';
  const datos = JSON.stringify({
    nombre: 'api-php',
    instancia: 'apache',
    url: apiPhp.url,
    rutaBase: apiPhp.rutaBase,
    rutaUploads: apiPhp.rutaUploads,
    salud: `${apiPhp.url}${apiPhp.rutaBase}/salud`,
    registradoPor: 'gateway',
  });

  const revisar = async () => {
    try {
      const respuesta = await fetch(`${apiPhp.url}${apiPhp.rutaBase}/salud`, { signal: AbortSignal.timeout(3000) });
      if (respuesta.ok) {
        await redis.set(clave, datos, 'EX', ttlSeg);
      } else {
        console.warn(`[registro] api-php respondio ${respuesta.status}; no renuevo su registro`);
      }
    } catch (error) {
      console.warn(`[registro] api-php no responde: ${error.message}`);
    }
  };

  revisar();
  const temporizador = setInterval(revisar, intervaloMs);
  return () => clearInterval(temporizador);
}
