import { Notificacion, documentoDesdeMensaje } from './modelo.js';

const esperar = (ms) => new Promise((resolver) => setTimeout(resolver, ms));

/**
 * CONSUMIDOR DE LA COLA (mensajeria asincrona).
 * El Observer PHP publica con LPUSH y aqui saco con BRPOP: el mas antiguo primero (FIFO).
 * BRPOP es bloqueante: espera hasta 5 s a que llegue un mensaje sin gastar CPU preguntando.
 * Por eso uso una conexion de Redis exclusiva para el consumidor.
 *
 * Tolerancia a fallos:
 *  - Si este servicio esta apagado, los mensajes esperan en Redis y se procesan al encenderlo.
 *  - Si MongoDB falla al guardar, devuelvo el mensaje al FINAL de la cola (RPUSH) para que sea
 *    el proximo en procesarse y reintento despues; ningun aviso se pierde.
 *  - Guardar es idempotente (upsert por idEvento): procesar dos veces el mismo mensaje no lo duplica.
 */
export function iniciarConsumidor(redisBloqueante, cola) {
  const estado = { activo: true, procesados: 0, errores: 0, ultimo: null };

  (async () => {
    while (estado.activo) {
      let respuesta;
      try {
        respuesta = await redisBloqueante.brpop(cola, 5);
      } catch (error) {
        if (!estado.activo) break;
        estado.errores++;
        console.warn(`[consumidor] Redis no responde (${error.message}); reintento en 2 s`);
        await esperar(2000);
        continue;
      }
      if (!respuesta) continue; // pasaron 5 s sin mensajes

      const [, crudo] = respuesta;
      try {
        const documento = documentoDesdeMensaje(JSON.parse(crudo));
        await Notificacion.updateOne({ idEvento: documento.idEvento }, { $setOnInsert: documento }, { upsert: true });
        estado.procesados++;
        estado.ultimo = new Date().toISOString();
        console.log(`[consumidor] guardada: ${documento.mensaje}`);
      } catch (error) {
        estado.errores++;
        if (error instanceof SyntaxError || error.message === 'Mensaje de la cola incompleto') {
          // Un mensaje mal formado nunca se podra guardar: lo aparto para revisarlo y sigo
          await redisBloqueante.lpush(`${cola}:descartados`, crudo).catch(() => {});
          console.warn(`[consumidor] mensaje descartado: ${error.message}`);
        } else {
          await redisBloqueante.rpush(cola, crudo).catch(() => {});
          console.warn(`[consumidor] no se pudo guardar en MongoDB (${error.message}); se reintentara`);
          await esperar(2000);
        }
      }
    }
  })();

  return estado;
}
