# Base de datos `sistemaescolar`

Motor: MySQL / MariaDB de XAMPP (puerto 3306). Juego de caracteres recomendado: `utf8mb4`.

## Orden de ejecución

Los scripts se ejecutan en este orden. Las migraciones se pueden volver a ejecutar sin dañar los datos
(usan `CREATE TABLE IF NOT EXISTS` y validaciones previas).

| # | Archivo | Qué hace |
|---|---------|----------|
| 1 | `sistemaescolar.sql` | Crea las tablas base (`usuarios`, `roles`, `materias`, `tareas`, `archivos`, `notificaciones`, `matriculas`) y los datos de prueba. |
| 2 | `migracion_matriculas.sql` | Asegura la llave primaria de `usuarios`, el `AUTO_INCREMENT` de `materias` y la tabla `matriculas`. |
| 3 | `migracion_calificaciones.sql` | Crea la tabla `calificaciones` (nota entre 0 y 5, una por tarea y estudiante). |
| 4 | `migracion_entregas.sql` | Unifica los estados de las tareas (`Pendiente`, `Completada`, `Vencida`) y crea la tabla `entregas` (una entrega por tarea y estudiante). |
| 5 | `migracion_concurrencia.sql` | Agrega `version` a `calificaciones` (bloqueo optimista) y crea `cola_respaldo` (respaldo de la cola de Redis). |

## Instalación desde cero (phpMyAdmin)

1. Abrir `http://localhost/phpmyadmin`.
2. Crear la base de datos `sistemaescolar` con cotejamiento `utf8mb4_spanish_ci`.
3. Seleccionarla y, en la pestaña **Importar**, cargar los archivos en el orden de la tabla.

## Instalación desde la consola

```bash
C:\xampp\mysql\bin\mysql -u root -e "CREATE DATABASE IF NOT EXISTS sistemaescolar CHARACTER SET utf8mb4 COLLATE utf8mb4_spanish_ci"
C:\xampp\mysql\bin\mysql -u root sistemaescolar < database/sistemaescolar.sql
C:\xampp\mysql\bin\mysql -u root sistemaescolar < database/migracion_matriculas.sql
C:\xampp\mysql\bin\mysql -u root sistemaescolar < database/migracion_calificaciones.sql
C:\xampp\mysql\bin\mysql -u root sistemaescolar < database/migracion_entregas.sql
```

Si la base de datos ya existe con datos, solo se ejecutan las migraciones que falten (pasos 2 a 5).

## Usuarios de prueba

| Email | Clave | Rol |
|-------|-------|-----|
| admin@admin.com | 123 | ADMINISTRADOR |
| profesor@gmail.com | 123 | PROFESOR |
| estudiante@gmail.com | 123 | ESTUDIANTE |

## Estados de una tarea

- `Pendiente`: la tarea está abierta para entregas.
- `Vencida`: pasó la fecha y hora de entrega (se actualiza automáticamente con un solo `UPDATE`).
- `Completada`: el profesor la cerró manualmente.

El estado de cada estudiante (`Entregada`, `Pendiente`, `No entrego`) no se guarda en `tareas`;
se calcula a partir de la tabla `entregas`.
