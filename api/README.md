# API REST del Sistema de Gestión Escolar

API en PHP 8.2 sin framework, con **Eloquent** (`illuminate/database`) como ORM. Comparte la base de datos,
la sesión y las reglas de negocio con las vistas PHP de `admin/`.

URL base local: `http://localhost/proyectoGestorEscolar/api`

## Instalación

```bash
composer install
```

Requiere la extensión `zip` activa en `C:\xampp\php\php.ini` (`extension=zip`) y `mod_rewrite` en Apache
(viene activo en XAMPP). Si `mod_rewrite` no está disponible, las rutas también funcionan como
`api/index.php?r=materias`.

## Convenciones

- Todas las respuestas son JSON con la forma `{ "data": ..., "error": null }`.
- Los errores responden `{ "data": null, "error": "mensaje", "detalles": { "campo": "mensaje" } }`.
- Autenticación por **cookie de sesión PHP** (la misma de las vistas). Desde Angular se envía con `withCredentials: true`.
- Las peticiones `POST`, `PUT` y `DELETE` deben incluir la cabecera `X-Requested-With: XMLHttpRequest`
  (protección CSRF: un formulario de otro sitio no puede enviar esa cabecera).
- CORS habilitado para `http://localhost:4200` con credenciales.

| Código | Significado |
|---|---|
| 200 / 201 | Correcto / recurso creado |
| 401 | No hay sesión |
| 403 | El rol no tiene permiso, o falta `X-Requested-With` |
| 404 | El recurso o la ruta no existen |
| 405 | Método no permitido para la ruta |
| 409 | Conflicto (email o rol repetido, materia con tareas, plazo vencido) |
| 422 | Datos inválidos (ver `detalles`) |
| 503 | La base de datos no responde (solo `/salud`) |

## Endpoints

| Método | Ruta | Roles | Descripción |
|---|---|---|---|
| GET | `/salud` | público | Estado de la API, la base de datos y Redis; tamaño de la cola y mensajes en respaldo |
| POST | `/auth/login` | público | `{ email, password }` inicia sesión |
| POST | `/auth/logout` | público | Cierra la sesión |
| GET | `/auth/me` | todos | Usuario autenticado |
| GET | `/usuarios?rol=ESTUDIANTE` | ADMIN | Lista usuarios activos con su rol |
| GET / PUT / DELETE | `/usuarios/{id}` | ADMIN | Ver / actualizar / eliminar |
| POST | `/usuarios` | ADMIN | `{ nombres, email, rol_id, password, password_confirmacion }` |
| GET | `/roles` | ADMIN | Lista roles con cantidad de usuarios |
| POST / PUT / DELETE | `/roles`, `/roles/{id}` | ADMIN | `{ nombre_rol }` |
| GET | `/materias` | todos | El estudiante solo ve sus materias matriculadas |
| GET | `/materias/{id}` | todos | Detalle con cantidad de tareas |
| POST / PUT / DELETE | `/materias`, `/materias/{id}` | ADMIN | `{ nombre_materia }` |
| GET | `/tareas?q=&id_materia=&orden=` | todos | Búsqueda por título, descripción o materia. El estudiante recibe `mi_entrega`, `mi_calificacion` y `estado_entrega` |
| GET | `/tareas/{id}` | todos | Al profesor le incluye los estudiantes con su entrega y su nota |
| POST | `/tareas` | ADMIN, PROFESOR | `{ id_materia, titulo, descripcion, fecha_entrega, hora_entrega }` |
| PUT | `/tareas/{id}` | ADMIN, PROFESOR | Igual que crear, más `estado` (`Pendiente`, `Completada`, `Vencida`) |
| DELETE | `/tareas/{id}` | ADMIN, PROFESOR | Borra la tarea, sus entregas y notas (cascada) y los archivos |
| GET | `/matriculas?id_usuario=` | ADMIN | Matrículas con estudiante y materia |
| POST | `/matriculas` | ADMIN | `{ id_usuario, id_materias: [..] }` en una transacción |
| GET | `/calificaciones?id_tarea=&id_materia=` | todos | El estudiante solo ve sus notas |
| POST | `/calificaciones` | ADMIN, PROFESOR | `{ id_tarea, id_usuario, nota (0 a 5), observacion, version }`. Sin `version` crea la nota; con `version` la actualiza solo si nadie la cambió antes (bloqueo optimista, si no **409**) |
| GET | `/calificaciones/promedios?id_materia=` | todos | Promedio, máxima, mínima y aprobación por estudiante |
| GET | `/entregas?id_tarea=` | todos | El estudiante solo ve las suyas |
| POST | `/entregas` | ESTUDIANTE | `multipart/form-data`: `id_tarea`, `archivo` (pdf, docx, jpg, png, zip; máx. 5 MB). Protegida con `GET_LOCK`: si hay otra subida en curso responde **409** |
| GET | `/notificaciones?limite=20` | todos | Avisos más recientes; el estudiante solo los de sus materias |

## Ejemplo con curl

```bash
# Inicio sesión y guardo la cookie
curl -c sesion.txt -H "X-Requested-With: XMLHttpRequest" -H "Content-Type: application/json" \
     -d '{"email":"profesor@gmail.com","password":"123"}' \
     http://localhost/proyectoGestorEscolar/api/auth/login

# Busco tareas con la cookie de sesión
curl -b sesion.txt "http://localhost/proyectoGestorEscolar/api/tareas?q=taller"
```

## Estructura

```
api/
├── index.php              Front controller: registra las rutas y convierte errores en JSON
├── .htaccess              Reescribe /api/... hacia index.php
└── src/
    ├── BaseDatos.php      Inicializa Eloquent (Capsule) y transacciones
    ├── Http/              Peticion, Respuesta, Enrutador, Validador, ErrorHttp
    ├── Seguridad/         Sesion (roles) y Cors (CORS + cabecera anti-CSRF)
    ├── Modelos/           Modelos Eloquent y sus relaciones
    └── Controladores/     Un controlador por recurso
```

Reglas compartidas con las vistas PHP:
- `config/estados_tarea.php`: estados de tarea y plazo de entrega.
- `config/archivos.php`: tamaño, extensiones y tipo MIME de los archivos.
- `lib/funciones_notas.php`: funciones puras para promedios y estadísticas.
- `observers/`: patrón Observer que registra las notificaciones al crear una tarea.
