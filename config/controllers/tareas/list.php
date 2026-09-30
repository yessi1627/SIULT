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

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    $sql = "SELECT t.*, m.nombre_materia AS materia, a.ruta_archivo, c.nota, c.observacion AS observacion_calificacion
        FROM tareas t
        LEFT JOIN materias m ON t.id_materia = m.id_materia
        LEFT JOIN archivos a ON t.id_tarea = a.id_tarea
        LEFT JOIN calificaciones c ON c.id_tarea = t.id_tarea AND c.id_usuario = :id_usuario_calificacion";
    $parametros = [':id_usuario_calificacion' => $_SESSION['id_usuario'] ?? 0];
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
        'fecha_entrega' => $tarea['fecha_entrega'],
        'hora_entrega' => $tarea['hora_entrega'],
        'nota' => $tarea['nota'],
        'observacion_calificacion' => $tarea['observacion_calificacion'],
    ];
}, $tareas);

header('Content-Type: application/json');
echo json_encode(['data' => $tareasMap]);
