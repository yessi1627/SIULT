# Optimización de consultas con índices

Los índices funcionan como el índice de un libro: en lugar de leer todas las filas de la tabla
(*full table scan*), MySQL salta directamente a las que necesita. Además, si el índice ya está
ordenado por la columna del `ORDER BY`, se evita el paso de ordenar en memoria (*filesort*).

Script: [`database/migracion_indices.sql`](../database/migracion_indices.sql). Se puede ejecutar varias veces.

## Cómo se midió

- Base de datos de prueba con **60.000 tareas** repartidas en 60 materias y **60.000 notificaciones**.
- `EXPLAIN` de cada consulta antes y después de crear los índices, con `ANALYZE TABLE` para actualizar las estadísticas.
- Tiempo promedio de 20 ejecuciones de cada consulta (MariaDB 10.4 de XAMPP).

Columnas de `EXPLAIN` usadas:

| Columna | Significado |
|---|---|
| `type` | `ALL` = recorre toda la tabla (lo peor) · `index` = recorre el índice · `range` = un rango del índice · `ref` = búsqueda por valor |
| `key` | índice que eligió MySQL |
| `rows` | filas que estima que debe revisar |
| `Extra` | `Using filesort` = ordena en memoria · `Using index` = responde solo con el índice, sin leer la tabla |

## Resultados

### 1. Últimas 15 notificaciones (campana)

```sql
SELECT * FROM notificaciones ORDER BY fecha_creacion DESC LIMIT 15
```

| | type | key | rows | Extra | Tiempo |
|---|---|---|---|---|---|
| Antes | `ALL` | — | 60.174 | Using filesort | 15,37 ms |
| Después | `index` | `idx_notificaciones_fecha` | **15** | — | **0,31 ms** |

**≈ 50 veces más rápida.** Antes leía y ordenaba las 60.000 filas para quedarse con 15; ahora recorre el
índice (que ya está ordenado por fecha) desde el final y se detiene en la fila 15.

### 2. Marcar tareas vencidas (`actualizarTareasVencidas`, se ejecuta en cada listado)

```sql
UPDATE tareas SET estado = 'Vencida' WHERE estado = 'Pendiente' AND fecha_entrega < CURDATE() ...
```

| | type | key | rows | Extra | Tiempo |
|---|---|---|---|---|---|
| Antes | `ALL` | — | 59.945 | Using where | 18,69 ms |
| Después | `range` | `idx_tareas_estado_fecha` | 14.798 | Using where; **Using index** | **4,2 ms** |

**≈ 4,5 veces más rápida.** El índice compuesto `(estado, fecha_entrega)` permite ir directo a las
tareas `Pendiente` con fecha anterior a hoy, y es *cubriente*: no necesita leer la tabla.

### 3. Tareas de una materia ordenadas por fecha

```sql
SELECT id_tarea, titulo, fecha_entrega FROM tareas WHERE id_materia = ? ORDER BY fecha_entrega LIMIT 20
```

| | type | key | rows | Extra | Tiempo |
|---|---|---|---|---|---|
| Antes | `ref` | `id_materia` | 1.000 | Using where; Using filesort | 2,64 ms |
| Después | `ref` | `idx_tareas_materia_fecha` | 1.000 | Using where | **0,38 ms** |

**≈ 7 veces más rápida.** Ya existía un índice por `id_materia`, pero había que ordenar el resultado.
Con el índice compuesto `(id_materia, fecha_entrega)` las filas salen ya ordenadas: desaparece el *filesort*.

### 4. Tareas de un estudiante (varias materias) ordenadas por fecha

```sql
SELECT ... FROM tareas WHERE id_materia IN (SELECT id_materia FROM matriculas WHERE id_usuario = ?) ORDER BY fecha_entrega
```

| | key (tareas) | rows | Tiempo |
|---|---|---|---|
| Antes | `id_materia` | 525 | 28,0 ms |
| Después | `idx_tareas_materia_fecha` | 491 | 27,5 ms |

**Sin mejora apreciable**, y es esperable: el resultado mezcla las tareas de varias materias, así que
aunque cada materia venga ordenada, MySQL tiene que unirlas y ordenar al final. El índice no resuelve
todos los casos; por eso se mide en lugar de suponer. En el sistema esta consulta devuelve pocas filas
por estudiante, así que no es un problema real.

## Índices que ya existían (no se duplicaron)

| Tabla | Índice | Cubre la búsqueda por |
|---|---|---|
| `matriculas` | `UNIQUE (id_usuario, id_materia)` | `id_usuario` (prefijo izquierdo del índice) |
| `calificaciones` | `KEY id_usuario`, `UNIQUE (id_tarea, id_usuario)` | estudiante y tarea |
| `entregas` | `UNIQUE (id_tarea, id_usuario)` | tarea |
| `usuarios` | `UNIQUE email` | login |

Un índice de más también tiene costo: ocupa espacio y vuelve más lentos los `INSERT` y `UPDATE`,
porque hay que actualizarlo. Por eso solo se agregaron los tres que muestran una mejora medible.

## Índices en MongoDB (microservicio de notificaciones)

En `servicios/notificaciones/src/modelo.js`:
- `{ idEvento: 1 }` único: evita guardar dos veces el mismo mensaje de la cola (procesamiento idempotente).
- `{ idMateria: 1, fecha: -1 }`: la consulta del estudiante (avisos de sus materias, los más recientes primero).
- `{ fecha: -1 }`: la consulta del profesor y el administrador (todos los avisos recientes).
