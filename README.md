## 🔗 Link del proyecto

[![portfolio](https://img.shields.io/badge/my_portfolio-000?style=for-the-badge&logo=ko-fi&logoColor=white)](http://44.213.131.209/)

# Sistema de Gestión Escolar

Es un pequeño sistema web basico para la administración académica desarrollado con tecnologías modernas y buenas prácticas de programación.

## Arquitectura

```
                      ┌───────────────────────────── http://localhost:8080 ─────────────────────────────┐
 Angular (4200) ────► │ API Gateway (Node)  ── CORS · sesión · timeouts · Service Discovery (Redis) │
                      └──────┬────────────────────────┬─────────────────────────┬──────────────────┘
                             │ /api, /uploads         │ /notificaciones         │ /calendario
                             ▼                        ▼                         ▼
                   API PHP + Eloquent         Microservicio Node        Microservicio Node
                   (Apache de XAMPP)          notificaciones            calendario
                        │      │                  │      ▲                   │        │
                      MySQL   Redis ── cola ──────┘      │                 Redis    Nager.Date
                              (caché, registro)      MongoDB              (caché)   (API pública)
```

| Pieza | Carpeta | Puerto |
|---|---|---|
| Vistas PHP originales (siguen funcionando) | `admin/`, `config/` | 80 (Apache) |
| API REST con Eloquent | `api/` ([documentación](api/README.md)) | 80 (Apache) |
| API Gateway | `servicios/gateway` | 8080 |
| Microservicio de notificaciones | `servicios/notificaciones` | 3001 |
| Microservicio de calendario | `servicios/calendario` | 3002 |
| Redis y MongoDB | `docker-compose.infra.yml` | 6379 / 27017 |
| Frontend Angular | repositorio `frontend-angular` | 4200 |

Evidencias de cada tema de las unidades 1, 2 y 3: [`docs/EVIDENCIAS_UNIDADES.md`](docs/EVIDENCIAS_UNIDADES.md).

## Cómo ejecutarlo (en este orden)

Requisitos: XAMPP (PHP 8.2 + MySQL/MariaDB), Composer, Node.js 20.19 o superior y Docker Desktop.

1. **Base de datos:** en XAMPP encender Apache y MySQL, y crear la base ejecutando los scripts en el orden de [`database/README.md`](database/README.md).
2. **Dependencias PHP:** `composer install` en esta carpeta.
3. **Redis y MongoDB:** abrir Docker Desktop y ejecutar
   ```bash
   docker compose -f docker-compose.infra.yml up -d
   ```
4. **Microservicios y gateway:**
   ```bash
   cd servicios
   npm install        # solo la primera vez
   npm run iniciar    # arranca gateway, notificaciones y calendario
   ```
   Comprobar en `http://localhost:8080/salud` que los tres servicios aparecen con estado `ok`
   (el registro del backend PHP puede tardar hasta 10 segundos).
5. **Frontend:** en el repositorio `frontend-angular` ejecutar `npm install` y `npm start`, y abrir `http://localhost:4200`.

Para apagar: `Ctrl + C` en las terminales de los servicios y `docker compose -f docker-compose.infra.yml down`.



## Funcionalidades del sistema

Administrador:

- Gestión completa de usuarios (estudiantes y profesores)
- Creación y administración de materias
- Supervisión general del sistema
- Control total de roles y permisos

Datos para logeo:

admin@admin.com:123

Profesores:

- Visualización de materias asignadas
- Creación y gestión de tareas
- Asignación de actividades a grupos específicos
- Seguimiento del progreso estudiantil

Datos para logeo:

profesor@gmail.com:123

Estudiantes:

- Acceso a materias matriculadas
- Visualización de tareas asignadas
- Entrega de trabajos y actividades
- Consulta de calificaciones

Datos para logeo:

estudiante@gmail.com:123
## Frontend

- HTML5 & CSS3 - para la estructura y diseño responsivo.
- Bootstrap - para los componentes e iconos que utilizo
- JavaScript & AJAX - parte de la logica y envio de las notificaciones
- SweetAlert2 - mostrar las notificaciones y alertas de una forma elegante
## Backend

- PHP 
- MySQL 
## Despliegue

AWS  - Servidor de producción

Railway - Hosting de base de datos MySQL

Apache - Servidor web
## Documentación

[Documentation](https://drive.google.com/uc?export=download&id=1Bn0AEvYq4tEj8gWgi9dU-mO-UB_63tf2)


## Tests

El proyecto incluye una suite completa de pruebas automatizadas con Selenium:

Entre ellas estan:

- Pruebas de Integración - Verificación de flujos completos entre componentes
- Pruebas de Caja Negra - Validación de funcionalidades desde la perspectiva del usuario
- Pruebas de Aceptación - Verificación de requisitos
- Pruebas de Regresión - Garantía de que nuevos cambios no afecten funcionalidades existentes

## Author

- [@JhRA9](https://github.com/JhRA9)

Este proyecto lo desarrollé como parte de mi aprendizaje en programación web y es uno de mis primeros proyectos serios. Reconozco que puede realizarse de una manera más óptima y más bonita, como: hay funcionalidades que podrían estar mejor implementadas, el código podría ser más limpio en algunas partes, y definitivamente necesita más pruebas y optimizaciones de seguridad.

Aunque sé que hay muchas cosas por mejorar y optimizar, estoy contento con lo que he logrado hasta ahora. Cada funcionalidad que implementé fue un nuevo reto para mí. Me ayudó a crecer como desarrollador ya que cada línea de código representa un aprendizaje para mí, cada bug que solucioné me enseñó algo nuevo, y cada funcionalidad que logré completar me motivó a seguir mejorando.

