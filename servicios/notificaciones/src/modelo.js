import mongoose from 'mongoose';

/**
 * Las notificaciones se guardan en MongoDB (NoSQL, orientado a documentos) porque son datos simples,
 * independientes y de escritura frecuente: no necesitan relaciones ni transacciones entre tablas.
 * Cada documento guarda dentro de si mismo la lista de usuarios que ya lo leyeron (leidaPor),
 * algo que en SQL requeriria una tabla intermedia.
 */
const esquema = new mongoose.Schema(
  {
    // Identificador del evento que llego por la cola; es unico para no guardar dos veces el mismo
    // mensaje si se procesa de nuevo (entrega "al menos una vez" -> procesamiento idempotente)
    idEvento: { type: String, required: true, unique: true },
    tipo: { type: String, default: 'tarea_creada' },
    mensaje: { type: String, required: true },
    idTarea: { type: Number, required: true },
    idMateria: { type: Number, default: null },
    fecha: { type: Date, required: true },
    leidaPor: { type: [Number], default: [] },
  },
  { collection: 'notificaciones', versionKey: false, timestamps: { createdAt: 'creadaEn', updatedAt: false } },
);

// Indice para la consulta mas comun: notificaciones de ciertas materias, las mas recientes primero
esquema.index({ idMateria: 1, fecha: -1 });
esquema.index({ fecha: -1 });

export const Notificacion = mongoose.model('Notificacion', esquema);

// Convierto un mensaje de la cola (formato del Observer PHP) en un documento
export function documentoDesdeMensaje(mensaje) {
  if (!mensaje?.id_evento || !mensaje?.mensaje || !mensaje?.id_tarea) {
    throw new Error('Mensaje de la cola incompleto');
  }
  return {
    idEvento: mensaje.id_evento,
    tipo: mensaje.tipo ?? 'tarea_creada',
    mensaje: mensaje.mensaje,
    idTarea: Number(mensaje.id_tarea),
    idMateria: mensaje.id_materia === null || mensaje.id_materia === undefined ? null : Number(mensaje.id_materia),
    fecha: mensaje.fecha ? new Date(mensaje.fecha.replace(' ', 'T')) : new Date(),
  };
}

// Formato de salida igual al de la API PHP ("2026-10-02 10:30:00") para que Angular no cambie
const dosDigitos = (n) => String(n).padStart(2, '0');
export function fechaTexto(fecha) {
  const f = new Date(fecha);
  return `${f.getFullYear()}-${dosDigitos(f.getMonth() + 1)}-${dosDigitos(f.getDate())} ${dosDigitos(f.getHours())}:${dosDigitos(f.getMinutes())}:${dosDigitos(f.getSeconds())}`;
}

export function aRespuesta(doc, idUsuario) {
  return {
    id: String(doc._id),
    tipo: doc.tipo,
    mensaje: doc.mensaje,
    id_tarea: doc.idTarea,
    id_materia: doc.idMateria,
    fecha: fechaTexto(doc.fecha),
    leida: (doc.leidaPor ?? []).includes(idUsuario),
  };
}

// Filtro de visibilidad: el estudiante solo ve avisos de sus materias
export function filtroPara(usuario) {
  return usuario.rol === 'ESTUDIANTE' ? { idMateria: { $in: usuario.materias } } : {};
}
