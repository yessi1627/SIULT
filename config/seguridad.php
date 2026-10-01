<?php
// Funciones de seguridad para los controladores.
// autenticacion_rol.php valida las vistas de /admin/ segun la URL; aqui valido los controladores
// de forma explicita y respondo en HTML (redireccion) o en JSON (401/403) segun quien me llame.

// Me aseguro de que la sesion este iniciada una sola vez
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Calculo la URL base del proyecto (ej. /proyectoGestorEscolar/) para redirigir sin depender
// de la carpeta desde donde se ejecuta el controlador
function urlBaseAplicacion(): string
{
    $raizServidor = str_replace('\\', '/', (string) realpath($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $raizProyecto = str_replace('\\', '/', (string) realpath(__DIR__ . '/..'));
    $rutaRelativa = substr($raizProyecto, strlen($raizServidor));
    return rtrim((string) $rutaRelativa, '/') . '/';
}

// Respondo un error en JSON con el mismo formato que usara la API: { "data": null, "error": "..." }
function responderErrorJson(int $codigoHttp, string $mensaje): void
{
    http_response_code($codigoHttp);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['data' => null, 'error' => $mensaje]);
    exit();
}

// Redirijo a una ruta del proyecto dejando el mensaje para SweetAlert
function redirigirConMensaje(string $rutaDestino, string $mensaje, string $icono = 'error'): void
{
    $_SESSION['mensaje'] = $mensaje;
    $_SESSION['icono'] = $icono;
    header('Location: ' . urlBaseAplicacion() . $rutaDestino);
    exit();
}

// Verifico que haya un usuario autenticado
function exigirSesion(bool $respuestaJson = false): void
{
    if (isset($_SESSION['sesion email'], $_SESSION['id_usuario'])) {
        return;
    }
    if ($respuestaJson) {
        responderErrorJson(401, 'Debe iniciar sesion');
    }
    redirigirConMensaje('index.php', 'Debe iniciar sesion para continuar');
}

// Verifico que el usuario autenticado tenga alguno de los roles permitidos
function exigirRol(array $rolesPermitidos, bool $respuestaJson = false): void
{
    exigirSesion($respuestaJson);
    if (in_array($_SESSION['role'] ?? '', $rolesPermitidos, true)) {
        return;
    }
    if ($respuestaJson) {
        responderErrorJson(403, 'No tiene permisos para realizar esta accion');
    }
    redirigirConMensaje('admin/home.php', 'No tiene permisos para realizar esta accion');
}

// Obtengo el token CSRF de la sesion; lo genero una sola vez por sesion
function tokenCsrf(): string
{
    if (empty($_SESSION['token_csrf'])) {
        $_SESSION['token_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['token_csrf'];
}

// Devuelvo el campo oculto que agrego dentro de cada formulario POST
function campoCsrf(): string
{
    return '<input type="hidden" name="token_csrf" value="' . htmlspecialchars(tokenCsrf(), ENT_QUOTES, 'UTF-8') . '">';
}

// Verifico que el formulario venga de una pagina del sistema comparando el token enviado con el de la sesion
function verificarCsrf(string $rutaSiFalla = 'admin/home.php'): void
{
    $tokenEnviado = $_POST['token_csrf'] ?? '';
    $tokenSesion = $_SESSION['token_csrf'] ?? '';
    if ($tokenSesion !== '' && is_string($tokenEnviado) && hash_equals($tokenSesion, $tokenEnviado)) {
        return;
    }
    redirigirConMensaje($rutaSiFalla, 'El formulario expiro, recargue la pagina e intente de nuevo');
}

// Algunos controladores solo se incluyen desde una vista (listados y datos).
// Si alguien los abre directamente por URL respondo 404 en vez de ejecutarlos.
function impedirAccesoDirecto(string $archivo): void
{
    $scriptSolicitado = realpath($_SERVER['SCRIPT_FILENAME'] ?? '');
    if ($scriptSolicitado !== false && $scriptSolicitado === realpath($archivo)) {
        http_response_code(404);
        exit();
    }
}
