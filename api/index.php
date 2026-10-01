<?php
// Front controller de la API REST. Todas las peticiones a /api/... llegan a este archivo
// (ver api/.htaccess), se enrutan al controlador que corresponde y SIEMPRE responden JSON:
//   exito: { "data": ..., "error": null }
//   error: { "data": null, "error": "mensaje", "detalles": { ... } }
declare(strict_types=1);

use Api\BaseDatos;
use Api\Controladores\AuthControlador;
use Api\Controladores\CalificacionesControlador;
use Api\Controladores\EntregasControlador;
use Api\Controladores\MateriasControlador;
use Api\Controladores\MatriculasControlador;
use Api\Controladores\NotificacionesControlador;
use Api\Controladores\RolesControlador;
use Api\Controladores\SaludControlador;
use Api\Controladores\TareasControlador;
use Api\Controladores\UsuariosControlador;
use Api\Http\Enrutador;
use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Seguridad\Cors;
use Api\Seguridad\Sesion;
use Illuminate\Database\QueryException;

// Nunca muestro errores de PHP en la salida: romperian el JSON. Se registran en el log de Apache.
ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/entorno.php';
require_once __DIR__ . '/../config/estados_tarea.php';
require_once __DIR__ . '/../config/archivos.php';
require_once __DIR__ . '/../lib/funciones_notas.php';
require_once __DIR__ . '/../config/redis.php';
require_once __DIR__ . '/../config/bloqueos.php';

// Calculo la URL donde vive la API (ej. /proyectoGestorEscolar/api) a partir de la carpeta del servidor
function rutaBaseApi(): string
{
    $raizServidor = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $carpetaApi = str_replace('\\', '/', (string) realpath(__DIR__));
    return rtrim(substr($carpetaApi, strlen($raizServidor)), '/');
}

Cors::aplicar();
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    // Respuesta a la verificacion previa (preflight) de CORS
    http_response_code(204);
    exit();
}

$enrutador = new Enrutador();
$enrutador->get('/salud', [SaludControlador::class, 'revisar']);

$enrutador->post('/auth/login', [AuthControlador::class, 'login']);
$enrutador->post('/auth/logout', [AuthControlador::class, 'logout']);
$enrutador->get('/auth/me', [AuthControlador::class, 'yo']);

foreach ([
    'usuarios' => UsuariosControlador::class,
    'roles' => RolesControlador::class,
    'materias' => MateriasControlador::class,
    'tareas' => TareasControlador::class,
] as $recurso => $controlador) {
    $enrutador->get("/$recurso", [$controlador, 'listar']);
    $enrutador->get("/$recurso/{id}", [$controlador, 'ver']);
    $enrutador->post("/$recurso", [$controlador, 'crear']);
    $enrutador->put("/$recurso/{id}", [$controlador, 'actualizar']);
    $enrutador->delete("/$recurso/{id}", [$controlador, 'eliminar']);
}

$enrutador->get('/matriculas', [MatriculasControlador::class, 'listar']);
$enrutador->post('/matriculas', [MatriculasControlador::class, 'crear']);

$enrutador->get('/calificaciones', [CalificacionesControlador::class, 'listar']);
$enrutador->post('/calificaciones', [CalificacionesControlador::class, 'guardar']);
$enrutador->get('/calificaciones/promedios', [CalificacionesControlador::class, 'promedios']);

$enrutador->get('/entregas', [EntregasControlador::class, 'listar']);
$enrutador->post('/entregas', [EntregasControlador::class, 'crear']);

$enrutador->get('/notificaciones', [NotificacionesControlador::class, 'listar']);

try {
    $peticion = Peticion::desdeGlobales(rutaBaseApi());
    Cors::verificarOrigenEscritura($peticion->metodo);
    Sesion::iniciar();
    BaseDatos::iniciar();
    $enrutador->despachar($peticion)->enviar();
} catch (ErrorHttp $error) {
    Respuesta::enviarError($error->codigoHttp, $error->getMessage(), $error->detalles);
} catch (BloqueoOcupado $error) {
    // Otro proceso tiene el bloqueo del mismo recurso (GET_LOCK): responder 409 para que el cliente reintente
    Respuesta::enviarError(409, $error->getMessage());
} catch (QueryException $error) {
    // 23000: violacion de una restriccion (llave unica o foranea)
    if ($error->getCode() === '23000') {
        Respuesta::enviarError(409, 'La operacion entra en conflicto con datos existentes');
    } else {
        error_log('API: error de base de datos: ' . $error->getMessage());
        Respuesta::enviarError(500, 'Error interno de base de datos');
    }
} catch (Throwable $error) {
    error_log('API: error no controlado: ' . $error->getMessage() . ' en ' . $error->getFile() . ':' . $error->getLine());
    Respuesta::enviarError(500, 'Error interno del servidor');
}
