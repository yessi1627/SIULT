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
| Funciones puras (frontend) | `frontend-angular/src/app/core/funcional/notas.ts`: `promedio`, `notaMaxima`, `notaMinima`, `aprobadas`, `resumen`, `distribucion` con `map`/`filter`/`reduce`; pruebas en `notas.spec.ts` (verifican que no modifican la entrada) | `ng test` → pruebas de `notas.spec.ts`; ver los indicadores de la pantalla Calificaciones |
| Inmutabilidad (frontend) | Todos los modelos son `readonly` (`core/modelos.ts`); `Store` congela cada estado con `Object.freeze` y crea uno nuevo con spread; `MatriculasComponent` crea un `Set` nuevo en cada selección | `store.spec.ts`: el estado anterior queda intacto |
| Mónada / functor | `frontend-angular/src/app/core/funcional/resultado.ts`: tipo `Resultado<T, E>` (Ok / Fallo) con `map`, `flatMap`, `coincidir`; el comentario explica las leyes. Operador RxJS `aResultado()` convierte errores HTTP en `Fallo` | `resultado.spec.ts` prueba las leyes de functor y de mónada; en la app, crear un rol repetido muestra el error sin romper la pantalla |
| Streams reactivos | Servicios HTTP en `core/servicios/*.service.ts`: todos devuelven `Observable` | Revisar cualquier servicio |
| Buscador reactivo | `paginas/tareas/tareas-lista.component.ts`: `debounceTime(400)` + `distinctUntilChanged()` + `switchMap()` | Escribir "taller" en Tareas: en la pestaña Network sale UNA sola petición al dejar de escribir; si se escribe rápido, `switchMap` cancela la anterior (aparece como *canceled*) |
| Polling reactivo con reintentos | `core/estado/notificaciones.store.ts`: `interval(30000)` + `startWith(0)` + `switchMap` + `retry({ count: 3, delay: backoff })` | Network: una petición a `/notificaciones` cada 30 s. Apagar Apache: la campana muestra "Reintentando…" y se recupera sola al encenderlo |
| Estado compartido (store) | `core/estado/store.ts` (`BehaviorSubject` + `distinctUntilChanged`) y `core/estado/calificaciones.store.ts` | Abrir Calificaciones y el detalle de una tarea; al guardar una nota en el detalle, el promedio y la tabla de Calificaciones se actualizan sin recargar |
| Backpressure | `debounceTime` en el buscador (descarta teclas intermedias) y `exhaustMap` en `paginas/tareas/calificar.component.ts` (ignora clics mientras hay una petición en curso) | Doble o triple clic rápido en "Guardar" nota → en Network sale una sola petición `POST /calificaciones` |
| Framework reactivo | Angular 22 sin zone.js (*zoneless*): la vista reacciona a *signals* y a Observables convertidos con `toSignal` | Revisar `app.config.ts` y cualquier componente |

---

## Unidad 2 – Concurrencia, paralelismo y distribución

| Tema | Dónde está implementado | Cómo demostrarlo |
|---|---|---|
| Transacciones con rollback | `api/src/BaseDatos.php`: `transaccion()`; usado en `MatriculasControlador::crear` y `TareasControlador::eliminar`. En PHP: `config/controllers/tareas/delete.php` y `matriculas/create.php` | Matricular un estudiante en varias materias: se guardan todas o ninguna |
| Comunicación entre procesos / servicios web | API REST JSON en `api/` consumida por Angular por HTTP | Abrir `http://localhost/proyectoGestorEscolar/api/salud` |
| Tolerancia a fallos (health check) | `api/src/Controladores/SaludControlador.php`: `GET /api/salud` responde 503 si la base de datos no responde | Detener MySQL en XAMPP y llamar `/api/salud` |
| Paralelismo en el cliente | `frontend-angular/src/app/paginas/inicio/inicio.component.ts`: `forkJoin` pide materias, tareas y notificaciones al mismo tiempo | Network al abrir Inicio: las tres peticiones arrancan juntas (barras superpuestas en la línea de tiempo) |
| Tolerancia a fallos (cliente) | `retry` con espera exponencial en `notificaciones.store.ts`; `aResultado()` evita que un error corte los flujos | Apagar Apache con la app abierta y volver a encenderlo |
| Modelo de concurrencia optimista (consistencia) | Columna `version` en `calificaciones` (`database/migracion_concurrencia.sql`). API: `CalificacionesControlador::guardar` ejecuta `UPDATE ... SET version = version + 1 WHERE ... AND version = ?`; si afecta 0 filas responde **409**. Angular (`calificar.component.ts`) envía la versión que leyó y ante un 409 avisa y recarga la nota. También en el formulario PHP (`config/controllers/calificaciones/create.php`) | Abrir la misma tarea en dos navegadores (profesor y administrador), cambiar la nota en el primero y luego en el segundo: el segundo recibe "Otro usuario modificó esta nota…" y ve el valor actualizado. Se evita la *actualización perdida* |
| Locks / exclusión mutua | `config/bloqueos.php`: `conBloqueo()` con `GET_LOCK('entrega_<tarea>_<usuario>', 5)` y `RELEASE_LOCK` en `finally`. Usado en `EntregasControlador::crear` y `config/controllers/tareas/upload.php` | En phpMyAdmin ejecutar `SELECT GET_LOCK('entrega_<id tarea>_<id estudiante>', 60)` y luego intentar entregar: espera 5 s y responde 409. Al liberar con `RELEASE_LOCK` la entrega pasa |
| Transacciones en entregas y calificaciones | `EntregasControlador::crear`: transacción + compensación (si falla la BD se borra el archivo nuevo; el anterior solo se borra después del *commit*). Igual en `upload.php` con `beginTransaction` / `rollBack` | Ver el código; la prueba de dos subidas simultáneas deja una sola fila y un solo archivo |
| Particionamiento de datos y paralelismo con Web Workers | `frontend-angular/src/app/core/funcional/estadisticas.ts` (`particionar`, `agregarBloque` = map, `combinar` = reduce), `core/trabajo/estadisticas.worker.ts` y `estadisticas-paralelas.service.ts` (un worker por bloque, `forkJoin` para unirlos) | Calificaciones (profesor) → "Simular 2.000.000 notas": se reparten en 4 bloques y 4 workers; el contador de cuadros sigue avanzando (la interfaz no se congela). Se muestra el tiempo del mismo cálculo en el hilo principal para comparar: el beneficio es no bloquear la interfaz, no necesariamente la velocidad, porque crear workers y transferir datos tiene costo |
| Mensajería asíncrona (cola) | `observers/ColaNotificacionesObserver.php` publica cada evento con `LPUSH cola:notificaciones` (Predis, `config/redis.php`). El consumidor (Fase 4) usa `BRPOP` → orden FIFO | Crear una tarea y ejecutar `docker exec siult-redis redis-cli LRANGE cola:notificaciones 0 -1` |
| Tolerancia a fallos (respaldo de la cola) | `config/redis.php`: `publicarEnCola` usa tiempos de espera cortos (0,5 s); si Redis no responde guarda el mensaje en la tabla `cola_respaldo` (patrón *outbox*) y `reenviarRespaldo` lo reenvía cuando Redis vuelve. `/api/salud` muestra el estado de Redis y los mensajes en respaldo | `docker stop siult-redis`, crear una tarea (funciona igual), ver `/api/salud` → `mensajes_en_respaldo: 1`; `docker start siult-redis`, crear otra tarea → el respaldo queda en 0 y la cola tiene todos los mensajes |
| Timeouts y reintentos | `frontend-angular/src/app/core/http/api.interceptor.ts`: `timeout(15 s)` (60 s en subidas) y `retry` con espera exponencial solo en GET y solo en errores transitorios (0, 502, 503, 504) | Detener Apache: las lecturas se reintentan y luego muestran "No hay conexión…" en lugar de quedarse cargando |

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
| Arquitectura de microservicios | `servicios/notificaciones` (Node + MongoDB) y `servicios/calendario` (Node + Redis): cada uno es un programa independiente, con su propio `package.json`, su propia base de datos y su propio `/salud`; se comunican por HTTP (gateway) y por mensajes (cola Redis) | `npm run iniciar` en `servicios/`; apagar uno (Ctrl + C en su proceso) y ver que el resto del sistema sigue funcionando |
| API Gateway | `servicios/gateway/src/app.js`: única entrada `http://localhost:8080`; `/api` → PHP, `/notificaciones` y `/calendario` → microservicios. Centraliza CORS, logs, tiempos máximos (`proxy.js`) y validación de sesión (`autenticacion.js`). Angular solo conoce esta dirección (`environment.ts`) | Pestaña Network: todas las peticiones van a `localhost:8080`; la cabecera `X-Servicio` dice qué servicio respondió. `http://localhost:8080/salud` muestra el estado de todos |
| Service Discovery | Auto-registro en Redis con TTL 30 s y latido cada 10 s (`servicios/*/src/registro.js`); registro por terceros del PHP (`gateway/src/registro-terceros.js`); el gateway resuelve el destino con round-robin (`gateway/src/descubrimiento.js`) | `http://localhost:8080/registro`; `docker exec siult-redis redis-cli KEYS "servicio:*"`; detener un servicio: su clave desaparece y el gateway responde 503 |
| Por qué gateway en Node y no Nginx | Nginx enruta a destinos fijos escritos en su configuración; para consultar el registro de Redis en cada petición necesitaría módulos extra (OpenResty/Lua). Con Node el descubrimiento dinámico, la validación de sesión y el registro por terceros son código propio, fácil de leer y probar | Explicación de diseño |
| Base de datos NoSQL | MongoDB en `servicios/notificaciones/src/modelo.js` (Mongoose): documentos con la lista de lectores embebida (`leidaPor`), índices `idMateria+fecha` y `idEvento` único | `docker exec siult-mongo mongosh siult --eval "db.notificaciones.find().limit(3)"` |
| Mensajería asíncrona (consumidor) | `servicios/notificaciones/src/consumidor.js`: `BRPOP` sobre `cola:notificaciones`, guardado idempotente (upsert por `idEvento`) y reintento si MongoDB falla | Detener el servicio, crear una tarea (queda en la cola), volver a iniciarlo: el aviso aparece |
| Caché | Redis en PHP (`config/cache.php`, patrón *cache-aside*): listado de materias y roles (TTL 1 h) con invalidación en `crear/actualizar/eliminar` de materias, roles, usuarios, matrículas y tareas. Festivos en el microservicio de calendario (TTL 24 h + copia de respaldo) | Network: cabecera `X-Cache: MISS` la primera vez y `HIT` después; crear una materia y ver `MISS` otra vez |
| Consumo de API externa con tolerancia a fallos | `servicios/calendario/src/festivos.js`: Nager.Date con `timeout` de 3 s, caché, respaldo y **cortocircuito** (*circuit breaker*) tras 3 fallos | Crear tarea con fecha 25/12: aparece "Es festivo en Colombia: Navidad". Sin internet sigue funcionando con la caché |
| Optimización de consultas | `database/migracion_indices.sql` y [`docs/optimizacion_consultas.md`](optimizacion_consultas.md) con `EXPLAIN` antes y después | Últimas notificaciones: de 60.174 filas y 15,4 ms a 15 filas y 0,3 ms (≈ 50 veces más rápida) |

---

## Seguridad (Fase 0, transversal)

| Tema | Dónde | Cómo demostrarlo |
|---|---|---|
| Control de sesión y rol en controladores | `config/seguridad.php`: `exigirSesion`, `exigirRol`, `impedirAccesoDirecto` | Abrir `config/controllers/tareas/list.php` sin sesión → 401 |
| Token CSRF | `config/seguridad.php`: `campoCsrf`, `verificarCsrf` (`hash_equals`) | Enviar un formulario sin el campo `token_csrf` → rechazado |
| Sentencias preparadas | `config/controllers/roles/datos_rol.php`, `usuarios/datos_usuario.php` | Abrir `admin/roles/show.php?id=1' OR '1'='1` → no hay inyección |
| Validación de archivos por tipo MIME real | `config/archivos.php`: `errorArchivoSubido` | Subir un `.exe` renombrado a `.pdf` → rechazado |
