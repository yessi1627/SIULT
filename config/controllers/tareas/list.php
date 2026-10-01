<?php
// Habilito CORS para el frontend Angular (localhost:4200) que consume este endpoint
$origenesPermitidos = ['http://localhost:4200'];
$origen = $_SERVER['HTTP_ORIGIN'] ?? '';
if (in_array($origen, $origenesPermitidos, true)) {
    header('Access-Control-Allow-Origin: ' . $origen);
    header('Access-Control-Allow-Credentials: true');
}
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit();
}

include('../../config.php');
require_once __DIR__ . '/../../seguridad.php';
require_once __DIR__ . '/../../estados_tarea.php';

// Sin sesion respondo 401 en JSON para que Angular pueda redirigir al login
exigirSesion(true);

// Antes de listar actualizo en una sola consulta las tareas que ya vencieron
actualizarTareasVencidas($pdo);

function obtenerTareasOrdenadas($orden)
{
    global $pdo;
    $ordenesPermitidos = [
        'title' => 't.titulo',
        'description' => 't.descripcion',
        'estado' => 't.estado',
        'materia' => 'm.nombre_materia',
        'fecha_entrega' => 't.fecha_entrega',
    ];
    $ordenSql = $ordenesPermitidos[$orden] ?? $ordenesPermitidos['title'];
    // El archivo de la tarea sale de una subconsulta (el ultimo) para no repetir filas;
    // la entrega y la calificacion son las del usuario en sesion (una por tarea gracias al UNIQUE)
    $sql = "SELECT t.*, m.nombre_materia AS materia,
            (SELECT a.ruta_archivo FROM archivos a WHERE a.id_tarea = t.id_tarea ORDER BY a.id DESC LIMIT 1) AS ruta_archivo,
            e.ruta_archivo AS ruta_entrega, e.fecha_entrega AS fecha_entrega_estudiante,
            c.nota, c.observacion AS observacion_calificacion
        FROM tareas t
        LEFT JOIN materias m ON t.id_materia = m.id_materia
        LEFT JOIN entregas e ON e.id_tarea = t.id_tarea AND e.id_usuario = :id_usuario_entrega
        LEFT JOIN calificaciones c ON c.id_tarea = t.id_tarea AND c.id_usuario = :id_usuario_calificacion";
    $parametros = [
        ':id_usuario_entrega' => $_SESSION['id_usuario'],
        ':id_usuario_calificacion' => $_SESSION['id_usuario'],
    ];
    if (($_SESSION['role'] ?? '') === 'ESTUDIANTE') {
        $sql .= " INNER JOIN matriculas mat ON mat.id_materia = t.id_materia
            WHERE mat.id_usuario = :id_usuario";
        $parametros[':id_usuario'] = $_SESSION['id_usuario'];
    }
    $sql .= " ORDER BY " . $ordenSql;
    $sentencia = $pdo->prepare($sql);
    $sentencia->execute($parametros);
    return $sentencia->fetchAll(PDO::FETCH_ASSOC);
}

// PATRON ESTRATEGY

interface Strategy
{
    public function execute();
}

class TareasByTitle implements Strategy
{
    public function execute()
    {
        return obtenerTareasOrdenadas('title');
    }
}

class TareasByDescription implements Strategy
{
    public function execute()
    {
        return obtenerTareasOrdenadas('description');
    }
}

class TareasByEstado implements Strategy
{
    public function execute()
    {
        return obtenerTareasOrdenadas('estado');
    }
}

class TareasByMateria implements Strategy
{
    public function execute()
    {
        return obtenerTareasOrdenadas('materia');
    }
}

class TareasFecha implements Strategy
{
    public function execute()
    {
        return obtenerTareasOrdenadas('fecha_entrega');
    }
}

class ContextTareas
{
    private $strategy;

    public function __construct(Strategy $strategy)
    {
        $this->strategy = $strategy;
    }

    public function executeStrategy()
    {
        return $this->strategy->execute();
    }
}




$order = $_GET['order'] ?? 'title';
$strategyMap = [
    'title' => TareasByTitle::class,
    'description' => TareasByDescription::class,
    'estado' => TareasByEstado::class,
    'materia' => TareasByMateria::class,
    'fecha_entrega' => TareasFecha::class,
];

$strategyClass = $strategyMap[$order] ?? TareasByTitle::class;
$strategy = new $strategyClass();

$context = new ContextTareas($strategy);
$tareas = $context->executeStrategy();
$tareasMap = array_map(function ($tarea) {
    return [
        'id' => $tarea['id_tarea'],
        'titulo' => $tarea['titulo'],
        'descripcion' => $tarea['descripcion'],
        'estado' => $tarea['estado'],
        'materia' => $tarea['materia'],
        'ruta_archivo' => $tarea['ruta_archivo'],
        'ruta_entrega' => $tarea['ruta_entrega'],
        'fecha_entrega_estudiante' => $tarea['fecha_entrega_estudiante'],
        'estado_entrega' => estadoEntregaEstudiante($tarea, $tarea['ruta_entrega']),
        'fecha_entrega' => $tarea['fecha_entrega'],
        'hora_entrega' => $tarea['hora_entrega'],
        'nota' => $tarea['nota'],
        'observacion_calificacion' => $tarea['observacion_calificacion'],
    ];
}, $tareas);

header('Content-Type: application/json');
echo json_encode(['data' => $tareasMap, 'error' => null]);
