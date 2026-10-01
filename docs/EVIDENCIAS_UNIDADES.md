# Evidencias de las unidades temáticas – Programación II

Este documento relaciona cada tema del microcurrículo con el lugar del proyecto donde está implementado
y la forma de demostrarlo en vivo.

**Sobre el lenguaje:** el curso usa Java y RxJava como referencia. En este proyecto el equivalente es
**TypeScript + RxJS** en el frontend (Angular) y **PHP / Node.js** en el backend. Los conceptos son los mismos:
RxJS implementa el mismo modelo de *Observables* y operadores que RxJava (ambos siguen la especificación
ReactiveX), y Eloquent cumple el papel que Hibernate/JPA cumple en Java.

Repositorios:
- Backend: `proyectoGestorEscolar/` (github.com/yessi1627/SIULT)
- Frontend: `frontend-angular/` (github.com/yessi1627/frontend-angular)

---

## Unidad 1 – Programación funcional y reactiva

| Tema | Dónde está implementado | Cómo demostrarlo |
|---|---|---|
| Funciones puras | `lib/funciones_notas.php`: `promedioNotas`, `notaMaxima`, `notaMinima`, `notasAprobadas`, `resumenNotas` | Mostrar que no acceden a la base de datos ni modifican variables externas; llamar `GET /api/calificaciones/promedios` y ver el resultado |
| Funciones de orden superior (`map`, `filter`, `reduce`) | `lib/funciones_notas.php`: `notasValidas` (`array_map` + `array_filter`), `sumaNotas` (`array_reduce`), `promediosPorEstudiante` | Explicar el código: no hay ciclos `for` que acumulen en variables externas |
| Inmutabilidad | `lib/funciones_notas.php`: `promediosPorEstudiante` crea arreglos nuevos con `array_replace` y el operador `...` en lugar de modificar el acumulado | Revisar el comentario en el código |

*(Se completa en la Fase 2 con RxJS: buscador reactivo, polling, store, backpressure y el tipo `Resultado<T, E>`.)*

---

## Unidad 2 – Concurrencia, paralelismo y distribución

| Tema | Dónde está implementado | Cómo demostrarlo |
|---|---|---|
| Transacciones con rollback | `api/src/BaseDatos.php`: `transaccion()`; usado en `MatriculasControlador::crear` y `TareasControlador::eliminar`. En PHP: `config/controllers/tareas/delete.php` y `matriculas/create.php` | Matricular un estudiante en varias materias: se guardan todas o ninguna |
| Comunicación entre procesos / servicios web | API REST JSON en `api/` consumida por Angular por HTTP | Abrir `http://localhost/proyectoGestorEscolar/api/salud` |
| Tolerancia a fallos (health check) | `api/src/Controladores/SaludControlador.php`: `GET /api/salud` responde 503 si la base de datos no responde | Detener MySQL en XAMPP y llamar `/api/salud` |

*(Se completa en la Fase 3: bloqueo optimista, `GET_LOCK`, `forkJoin`, Web Worker y cola Redis.)*

---

## Unidad 3 – Microservicios y persistencia

| Tema | Dónde está implementado | Cómo demostrarlo |
|---|---|---|
| API REST | `api/index.php` (front controller) y `api/src/Http/Enrutador.php`; endpoints documentados en `api/README.md` | `curl` o Postman contra `http://localhost/proyectoGestorEscolar/api/...` |
| Respuestas uniformes y códigos HTTP | `api/src/Http/Respuesta.php` (`{data, error}`) y `api/src/Http/ErrorHttp.php` (401, 403, 404, 409, 422) | Llamar `/api/materias` sin sesión → 401; crear un rol repetido → 409 |
| ORM | Modelos Eloquent en `api/src/Modelos/` (`Usuario`, `Rol`, `Materia`, `Tarea`, `Matricula`, `Calificacion`, `Entrega`, `Notificacion`, `Archivo`) | Mostrar que los controladores no escriben SQL a mano: `Usuario::with('rol')->activos()->get()` |
| Relaciones | `belongsTo` (`Tarea::materia`), `hasMany` (`Materia::tareas`), `belongsToMany` a través de `matriculas` (`Usuario::materias`, `Materia::estudiantes`) | Revisar los modelos |
| Eager loading (evitar N+1) | `TareasControlador::consultaBase` usa `with(['materia', 'archivos'])`; `UsuariosControlador::listar` usa `with('rol')`; `withCount('tareas')` en materias | Medición con 21 tareas: **sin eager loading 43 consultas, con `with()` 3 consultas** |
| Scopes de consulta | `Tarea::scopeBuscar`, `Tarea::scopeDelEstudiante`, `Usuario::scopeConRol`, `Materia::scopeActivas` | `GET /api/tareas?q=taller` |
| Seguridad de la API | `api/src/Seguridad/Sesion.php` (roles), `api/src/Seguridad/Cors.php` (CORS y cabecera anti-CSRF) | `POST /api/auth/login` sin `X-Requested-With` → 403 |

*(Se completa en la Fase 4: microservicios Node, API Gateway, Service Discovery, MongoDB, caché con Redis e índices.)*

---

## Seguridad (Fase 0, transversal)

| Tema | Dónde | Cómo demostrarlo |
|---|---|---|
| Control de sesión y rol en controladores | `config/seguridad.php`: `exigirSesion`, `exigirRol`, `impedirAccesoDirecto` | Abrir `config/controllers/tareas/list.php` sin sesión → 401 |
| Token CSRF | `config/seguridad.php`: `campoCsrf`, `verificarCsrf` (`hash_equals`) | Enviar un formulario sin el campo `token_csrf` → rechazado |
| Sentencias preparadas | `config/controllers/roles/datos_rol.php`, `usuarios/datos_usuario.php` | Abrir `admin/roles/show.php?id=1' OR '1'='1` → no hay inyección |
| Validación de archivos por tipo MIME real | `config/archivos.php`: `errorArchivoSubido` | Subir un `.exe` renombrado a `.pdf` → rechazado |
