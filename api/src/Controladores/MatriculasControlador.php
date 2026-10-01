<?php
declare(strict_types=1);

namespace Api\Controladores;

use Api\BaseDatos;
use Api\Http\Peticion;
use Api\Http\Respuesta;
use Api\Http\Validador;
use Api\Modelos\Materia;
use Api\Modelos\Matricula;
use Api\Modelos\Usuario;
use Api\Seguridad\Sesion;

// Matriculas: exclusivo del administrador, igual que en las vistas PHP
final class MatriculasControlador
{
    // GET /matriculas?id_usuario=41
    public function listar(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $idUsuario = (int) $peticion->query('id_usuario');
        $matriculas = Matricula::with(['usuario', 'materia'])
            ->when($idUsuario > 0, fn($c) => $c->where('id_usuario', $idUsuario))
            ->orderByDesc('fecha_matricula')
            ->get();
        return Respuesta::ok($matriculas->map(fn(Matricula $m) => $m->paraApi())->values());
    }

    // POST /matriculas { id_usuario, id_materias: [3, 4] }
    public function crear(Peticion $peticion): Respuesta
    {
        Sesion::exigirRol(['ADMINISTRADOR']);
        $validador = new Validador($peticion);
        $idUsuario = $validador->entero('id_usuario', 'El estudiante');
        $idMaterias = array_values(array_unique(array_filter(
            array_map('intval', (array) $peticion->campo('id_materias', [])),
            fn(int $id) => $id > 0
        )));
        if ($idMaterias === []) {
            $validador->agregarError('id_materias', 'Seleccione al menos una materia');
        }
        if ($idUsuario !== null && !Usuario::activos()->conRol('ESTUDIANTE')->whereKey($idUsuario)->exists()) {
            $validador->agregarError('id_usuario', 'El usuario seleccionado no es un estudiante activo');
        }
        $validador->verificar();

        // Solo matriculo en materias activas; la transaccion asegura que se guarden todas o ninguna
        $materiasValidas = Materia::activas()->whereKey($idMaterias)->pluck('id_materia');
        $fecha = date('Y-m-d H:i:s');
        $nuevas = BaseDatos::transaccion(fn() => Matricula::query()->insertOrIgnore(
            $materiasValidas->map(fn(int $idMateria) => [
                'id_usuario' => $idUsuario,
                'id_materia' => $idMateria,
                'fecha_matricula' => $fecha,
            ])->all()
        ));

        return Respuesta::creado([
            'matriculas_nuevas' => $nuevas,
            'ya_existian' => $materiasValidas->count() - $nuevas,
            'materias_invalidas' => count($idMaterias) - $materiasValidas->count(),
        ]);
    }
}
