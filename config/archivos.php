<?php
// Reglas para los archivos que suben los usuarios. Las comparten el formulario PHP
// (config/controllers/tareas/upload.php) y la API (POST /api/entregas).

const TAMANO_MAXIMO_ARCHIVO = 5242880;
const EXTENSIONES_PERMITIDAS = ['pdf', 'docx', 'jpg', 'jpeg', 'png', 'zip'];
const TIPOS_MIME_PERMITIDOS = [
    'pdf' => ['application/pdf'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    'jpg' => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png' => ['image/png'],
    'zip' => ['application/zip', 'application/x-zip-compressed'],
];
const DIRECTORIO_UPLOADS = __DIR__ . '/uploads/';

// Obtengo la extension en minuscula del nombre original del archivo
function extensionArchivo(string $nombreOriginal): string
{
    return strtolower(pathinfo($nombreOriginal, PATHINFO_EXTENSION));
}

// Verifico el archivo recibido en $_FILES. Devuelvo el mensaje de error o null si es valido.
// No confio en la extension: reviso el tipo MIME real del contenido con finfo.
function errorArchivoSubido(?array $archivo): ?string
{
    if (!$archivo || ($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return 'El archivo no pudo ser recibido';
    }
    if ($archivo['size'] > TAMANO_MAXIMO_ARCHIVO) {
        return 'El archivo supera el tamaño máximo permitido de 5 MB';
    }
    $extension = extensionArchivo($archivo['name']);
    if (!in_array($extension, EXTENSIONES_PERMITIDAS, true)) {
        return 'Tipo de archivo no permitido';
    }
    $tipoMime = (new finfo(FILEINFO_MIME_TYPE))->file($archivo['tmp_name']);
    if (!in_array($tipoMime, TIPOS_MIME_PERMITIDOS[$extension], true)) {
        return 'El contenido del archivo no coincide con su extensión';
    }
    return null;
}

// Muevo el archivo a la carpeta de uploads con un nombre aleatorio y devuelvo ese nombre.
// Devuelvo null si no se pudo mover.
function guardarArchivoSubido(array $archivo): ?string
{
    if (!is_dir(DIRECTORIO_UPLOADS)) {
        mkdir(DIRECTORIO_UPLOADS, 0777, true);
    }
    $nombreGuardado = bin2hex(random_bytes(16)) . '.' . extensionArchivo($archivo['name']);
    if (!move_uploaded_file($archivo['tmp_name'], DIRECTORIO_UPLOADS . $nombreGuardado)) {
        return null;
    }
    return $nombreGuardado;
}

// Borro un archivo de la carpeta de uploads; uso basename para no salir de esa carpeta
function borrarArchivoSubido(?string $nombreGuardado): void
{
    if (!$nombreGuardado) {
        return;
    }
    $ruta = DIRECTORIO_UPLOADS . basename($nombreGuardado);
    if (is_file($ruta)) {
        unlink($ruta);
    }
}
