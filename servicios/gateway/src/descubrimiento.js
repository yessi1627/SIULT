/**
 * SERVICE DISCOVERY del lado del gateway.
 * Cada servicio vivo tiene en Redis una clave `servicio:<nombre>:<instancia>` que renueva cada 10 s
 * (TTL 30 s). Para enviar una peticion, busco las instancias registradas con ese nombre y elijo una
 * por turnos (round-robin), asi la carga se reparte si hay varias instancias del mismo servicio.
 * Guardo el resultado 2 s para no consultar Redis en cada peticion.
 */
export function crearDescubrimiento(redis, { cacheMs = 2000 } = {}) {
  let cache = { hasta: 0, servicios: [] };
  const turnos = new Map();

  async function listar() {
    if (Date.now() < cache.hasta) return cache.servicios;
    const servicios = [];
    let cursor = '0';
    do {
      const [siguiente, claves] = await redis.scan(cursor, 'MATCH', 'servicio:*', 'COUNT', 100);
      cursor = siguiente;
      if (claves.length > 0) {
        const valores = await redis.mget(...claves);
        for (const valor of valores) {
          if (valor) servicios.push(JSON.parse(valor));
        }
      }
    } while (cursor !== '0');
    cache = { hasta: Date.now() + cacheMs, servicios };
    return servicios;
  }

  // Devuelvo una instancia del servicio o null si no hay ninguna viva
  async function resolver(nombre) {
    const instancias = (await listar()).filter((s) => s.nombre === nombre);
    return elegirPorTurno(instancias, nombre, turnos);
  }

  return { listar, resolver, olvidarCache: () => (cache = { hasta: 0, servicios: [] }) };
}

// Funcion de seleccion round-robin (separada para probarla sin Redis)
export function elegirPorTurno(instancias, nombre, turnos) {
  if (instancias.length === 0) return null;
  const ordenadas = [...instancias].sort((a, b) => a.instancia.localeCompare(b.instancia));
  const turno = turnos.get(nombre) ?? 0;
  turnos.set(nombre, turno + 1);
  return ordenadas[turno % ordenadas.length];
}
