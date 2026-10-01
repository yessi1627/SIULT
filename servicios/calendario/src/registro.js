/**
 * SERVICE DISCOVERY (auto-registro): al arrancar, el servicio guarda en Redis la clave
 *   servicio:<nombre>:<instancia>  ->  { nombre, url, rutaBase, ... }
 * con un tiempo de vida (TTL) de 30 s y la renueva cada 10 s (latido / heartbeat).
 * Si el proceso se cae, deja de renovarla y Redis la borra sola: el gateway ya no le envia trafico.
 * (Cada microservicio tiene su propia copia: asi se despliegan de forma independiente.)
 */
export function registrarServicio(redis, { nombre, host, puerto, rutaBase = '', intervaloMs = 10_000, ttlSeg = 30 }) {
  const instancia = `${nombre}-${process.pid}`;
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
