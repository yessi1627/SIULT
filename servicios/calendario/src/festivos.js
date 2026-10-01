/**
 * Consulta de festivos de Colombia en la API publica y gratuita Nager.Date con tolerancia a fallos:
 *
 *  1. CACHE en Redis (TTL 24 h): los festivos de un año no cambian, no tiene sentido pedirlos cada vez.
 *  2. TIMEOUT de 3 s: si Nager.Date tarda, no dejo esperando al usuario.
 *  3. RESPALDO: guardo una copia sin vencimiento; si Nager.Date falla y la cache ya vencio, uso la copia.
 *  4. CORTOCIRCUITO (circuit breaker): despues de 3 fallos seguidos dejo de llamar a Nager.Date durante
 *     60 s y respondo de inmediato con la copia o con "no disponible". Asi un servicio externo caido
 *     no vuelve lento a todo el sistema.
 */
export const URL_NAGER = 'https://date.nager.at/api/v3/PublicHolidays';
const TTL_SEGUNDOS = 24 * 60 * 60;
const FALLOS_PARA_ABRIR = 3;
const TIEMPO_ABIERTO_MS = 60_000;

export class ServicioNoDisponible extends Error {}

export function crearConsultaFestivos({ redis, fetchFn = fetch, pais = 'CO', timeoutMs = 3000, ahora = () => Date.now() }) {
  const circuito = { fallos: 0, abiertoHasta: 0 };
  const clave = (anio) => `festivos:${pais}:${anio}`;

  async function leer(claveRedis) {
    try {
      const valor = await redis.get(claveRedis);
      return valor ? JSON.parse(valor) : null;
    } catch {
      return null; // Redis caido: sigo sin cache
    }
  }

  async function pedirANager(anio) {
    const respuesta = await fetchFn(`${URL_NAGER}/${anio}/${pais}`, { signal: AbortSignal.timeout(timeoutMs) });
    if (!respuesta.ok) throw new Error(`Nager.Date respondio ${respuesta.status}`);
    const datos = await respuesta.json();
    return datos.map((f) => ({ fecha: f.date, nombre: f.localName, nombreIngles: f.name }));
  }

  return {
    circuito,

    async festivos(anio) {
      const enCache = await leer(clave(anio));
      if (enCache) return { anio, fuente: 'cache', festivos: enCache };

      const circuitoAbierto = ahora() < circuito.abiertoHasta;
      if (!circuitoAbierto) {
        try {
          const festivos = await pedirANager(anio);
          circuito.fallos = 0;
          await redis.set(clave(anio), JSON.stringify(festivos), 'EX', TTL_SEGUNDOS).catch(() => {});
          await redis.set(`${clave(anio)}:respaldo`, JSON.stringify(festivos)).catch(() => {});
          return { anio, fuente: 'nager', festivos };
        } catch (error) {
          circuito.fallos++;
          if (circuito.fallos >= FALLOS_PARA_ABRIR) {
            circuito.abiertoHasta = ahora() + TIEMPO_ABIERTO_MS;
            console.warn(`[calendario] circuito abierto por ${TIEMPO_ABIERTO_MS / 1000} s: ${error.message}`);
          }
        }
      }

      const respaldo = await leer(`${clave(anio)}:respaldo`);
      if (respaldo) return { anio, fuente: 'respaldo', festivos: respaldo };
      throw new ServicioNoDisponible('El servicio de festivos no está disponible en este momento');
    },
  };
}

// Funcion pura: busco una fecha en la lista de festivos
export function buscarFestivo(festivos, fecha) {
  const encontrado = festivos.find((f) => f.fecha === fecha);
  return { fecha, festivo: Boolean(encontrado), nombre: encontrado?.nombre ?? null };
}

export const fechaValida = (texto) => /^\d{4}-\d{2}-\d{2}$/.test(texto) && !Number.isNaN(Date.parse(texto));
