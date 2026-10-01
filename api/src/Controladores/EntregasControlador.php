<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Entrega;
use Api\Modelos\Tarea;
use Api\Seguridad\Sesion;
use Illuminate\Database\Eloquent\Builder;

final class EntregasControlador
{
    // GET /entregas?id_tarea=5 (el estudiante solo ve las suyas)
    public function listar(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirSesion();
        $idTarea = (int) $peticion->query('id_tarea');
        $entregas = Entrega::with('usuario')
            ->when(Sesion::esEstudiante($usuario), fn(Builder $c) => $c->where('id_usuario', $usuario['id']))
            ->when($idTarea > 0, fn(Builder $c) => $c->where('id_tarea', $idTarea))
            ->orderByDesc('fecha_entrega')
            ->get();
        return Respuesta::ok($entregas->map(fn(Entrega $e) => $e->paraApi())->values());
    }

    // POST /entregas (multipart/form-data: id_tarea + archivo)
    // Solo el estudiante entrega; si ya habia entregado, reemplazo su entrega y borro el archivo anterior
    public function crear(Peticion $peticion): Respuesta
    {
        $usuario = Sesion::exigirRol(['ESTUDIANTE']);
        $validador = new Validador($peticion);
        $idTarea = $validador->entero('id_tarea', 'La tarea');
        $archivo = $peticion->archivo('archivo');
        // Mismas reglas de tamaño, extension y tipo MIME que el formulario PHP (config/archivos.php)
        $errorArchivo = errorArchivoSubido($archivo);
        if ($errorArchivo !== null) {
            $validador->agregarError('archivo', $errorArchivo);
        }
        $validador->verificar();

        $tarea = Tarea::delEstudiante($usuario['id'])->find($idTarea) ?? throw ErrorHttp::noEncontrado('La tarea');
        if (!tareaAbiertaParaEntregas($tarea->getAttributes())) {
            throw ErrorHttp::conflicto('La fecha y hora de entrega ya pasaron');
        }

        $nombreGuardado = guardarArchivoSubido($archivo) ?? throw new ErrorHttp(500, 'No se pudo guardar el archivo');
        $anterior = Entrega::where('id_tarea', $idTarea)->where('id_usuario', $usuario['id'])->value('ruta_archivo');

        $entrega = Entrega::updateOrCreate(
            ['id_tarea' => $idTarea, 'id_usuario' => $usuario['id']],
            [
                'ruta_archivo' => $nombreGuardado,
                'nombre_original' => mb_substr($archivo['name'], 0, 255),
                'fecha_entrega' => date('Y-m-d H:i:s'),
            ]
        );
        borrarArchivoSubido($anterior);

        return new Respuesta($entrega->paraApi(), $entrega->wasRecentlyCreated ? 201 : 200);
    }
}
