<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Materia extends Model
{
    protected $table = 'materias';
    protected $primaryKey = 'id_materia';
    public const CREATED_AT = 'hora_creacion';
    public const UPDATED_AT = 'hora_actualizacion';
    protected $fillable = ['nombre_materia', 'estado'];

    public function tareas(): HasMany
    {
        return $this->hasMany(Tarea::class, 'id_materia', 'id_materia');
    }

    public function estudiantes(): BelongsToMany
    {
        return $this->belongsToMany(Usuario::class, 'matriculas', 'id_materia', 'id_usuario');
    }

    public function scopeActivas(Builder $consulta): Builder
    {
        return $consulta->where('estado', '1');
    }

    // Materias en las que esta matriculado un estudiante
    public function scopeDelEstudiante(Builder $consulta, int $idUsuario): Builder
    {
        return $consulta->whereHas('estudiantes', fn(Builder $u) => $u->where('usuarios.id_usuario', $idUsuario));
    }

    public function paraApi(): array
    {
        $datos = ['id' => $this->id_materia, 'nombre' => $this->nombre_materia];
        // tareas_count solo existe si la consulta uso withCount('tareas')
        if ($this->tareas_count !== null) {
            $datos['cantidad_tareas'] = (int) $this->tareas_count;
        }
        return $datos;
    }
}
