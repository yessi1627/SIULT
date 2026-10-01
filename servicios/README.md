# Microservicios de SIULT

Tres programas Node.js independientes. Cada uno tiene su propio `package.json`, su propio `/salud`
y se registra solo en Redis para que el API Gateway lo encuentre (Service Discovery).

| Servicio | Puerto | Responsabilidad | Datos |
|---|---|---|---|
| `gateway` | 8080 | Única entrada del frontend; enruta, valida sesión, CORS, tiempos máximos | Registro de servicios en Redis |
| `notificaciones` | 3001 | Consume `cola:notificaciones` (Redis, `BRPOP`) y sirve los avisos | MongoDB `siult.notificaciones` |
| `calendario` | 3002 | Festivos de Colombia desde Nager.Date | Caché en Redis (24 h) + respaldo |

## Ejecutar

Requiere Redis y MongoDB (`docker compose -f ../docker-compose.infra.yml up -d`) y Apache con el backend PHP.

```bash
npm install          # instala los tres (npm workspaces)
npm run iniciar      # arranca los tres en una sola terminal
npm test             # pruebas unitarias de los tres servicios (node:test)
```

También se pueden arrancar por separado: `npm run gateway`, `npm run notificaciones`, `npm run calendario`.

## Rutas del gateway

| Ruta | Destino |
|---|---|
| `/api/*` | API PHP (`http://localhost/proyectoGestorEscolar/api/*`) |
| `/uploads/*` | Archivos subidos (`/proyectoGestorEscolar/config/uploads/*`) |
| `/notificaciones`, `/notificaciones/leidas`, `/notificaciones/:id/leida` | Microservicio de notificaciones (requiere sesión) |
| `/calendario/festivos/:anio`, `/calendario/es-festivo/:fecha` | Microservicio de calendario |
| `/salud` | Estado de todos los servicios registrados |
| `/registro` | Servicios registrados en Redis |

## Service Discovery

- Cada microservicio guarda `servicio:<nombre>:<instancia>` en Redis con TTL de 30 s y lo renueva cada 10 s
  (`src/registro.js`). Si se cae, la clave vence y el gateway deja de enviarle tráfico.
- El backend PHP no tiene un proceso propio, así que el gateway lo registra por él después de comprobar
  su `/salud` (registro por terceros, `gateway/src/registro-terceros.js`).
- El gateway elige instancia por turnos (round-robin) si hay varias del mismo servicio.

Para ver el registro: `docker exec siult-redis redis-cli KEYS "servicio:*"` o `http://localhost:8080/registro`.

## Variables de entorno (opcionales)

| Variable | Por defecto | Servicio |
|---|---|---|
| `PUERTO` | 8080 / 3001 / 3002 | todos |
| `REDIS_URL` | `redis://127.0.0.1:6379/0` | todos |
| `MONGO_URL` | `mongodb://127.0.0.1:27017/siult` | notificaciones |
| `API_PHP_URL` | `http://localhost` | gateway |
| `ORIGENES` | `http://localhost:4200,http://127.0.0.1:4200` | gateway |
