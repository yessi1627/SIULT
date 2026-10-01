<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\BaseDatos;
use Api\Http\ErrorHttp;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Entrega;
use Api\Modelos\Tarea;
use Api\Seguridad\Sesion;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

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

    /**
     * POST /entregas (multipart/form-data: id_tarea + archivo). Solo el estudiante entrega.
     *
     * EXCLUSION MUTUA: con GET_LOCK('entrega_<tarea>_<usuario>') solo una subida del mismo estudiante
     * para la misma tarea se procesa a la vez (por ejemplo, si envia el formulario dos veces o desde
     * dos pestañas). La segunda espera hasta 5 s y, si la primera no termina, recibe 409.
     * TRANSACCION: el registro de la entrega se confirma o se deshace completo; si la base de datos
     * falla, borro el archivo nuevo para no dejar basura en el disco. El archivo anterior solo se
     * borra despues de confirmar la transaccion.
     */
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

        [$entrega, $anterior] = conBloqueo(
            BaseDatos::pdo(),
            nombreBloqueoEntrega($idTarea, $usuario['id']),
            5,
            function () use ($archivo, $idTarea, $usuario) {
                $nombreGuardado = guardarArchivoSubido($archivo) ?? throw new ErrorHttp(500, 'No se pudo guardar el archivo');
                try {
                    return BaseDatos::transaccion(function () use ($archivo, $idTarea, $usuario, $nombreGuardado) {
                        $anterior = Entrega::where('id_tarea', $idTarea)->where('id_usuario', $usuario['id'])->value('ruta_archivo');
                        $entrega = Entrega::updateOrCreate(
                            ['id_tarea' => $idTarea, 'id_usuario' => $usuario['id']],
                            [
                                'ruta_archivo' => $nombreGuardado,
                                'nombre_original' => mb_substr($archivo['name'], 0, 255),
                                'fecha_entrega' => date('Y-m-d H:i:s'),
                            ]
                        );
                        return [$entrega, $anterior];
                    });
                } catch (Throwable $error) {
                    // Compensacion: la transaccion hizo rollback, quito el archivo que ya habia movido
                    borrarArchivoSubido($nombreGuardado);
                    throw $error;
                }
            }
        );
        borrarArchivoSubido($anterior);

        return new Respuesta($entrega->paraApi(), $entrega->wasRecentlyCreated ? 201 : 200);
    }
}
