<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tarea extends Model
{
    protected $table = 'tareas';
    protected $primaryKey = 'id_tarea';
    public const CREATED_AT = 'hora_creacion';
    public const UPDATED_AT = 'hora_actualizacion';
    protected $fillable = ['id_materia', 'titulo', 'descripcion', 'fecha_entrega', 'hora_entrega', 'estado'];

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'id_materia', 'id_materia');
    }

    // Material que adjunta el profesor o el administrador
    public function archivos(): HasMany
    {
        return $this->hasMany(Archivo::class, 'id_tarea', 'id_tarea');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'id_tarea', 'id_tarea');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class, 'id_tarea', 'id_tarea');
    }

    // Tareas de las materias en las que esta matriculado un estudiante
    public function scopeDelEstudiante(Builder $consulta, int $idUsuario): Builder
    {
        return $consulta->whereHas('materia', fn(Builder $m) => $m->delEstudiante($idUsuario));
    }

    // Busco por titulo, descripcion o nombre de la materia (lo usa el buscador reactivo de Angular)
    public function scopeBuscar(Builder $consulta, ?string $texto): Builder
    {
        if ($texto === null || $texto === '') {
            return $consulta;
        }
        $patron = '%' . addcslashes($texto, '%_\\') . '%';
        return $consulta->where(fn(Builder $c) => $c
            ->where('titulo', 'like', $patron)
            ->orWhere('descripcion', 'like', $patron)
            ->orWhereHas('materia', fn(Builder $m) => $m->where('nombre_materia', 'like', $patron)));
    }

    // Convierto la tarea al formato de la API. Solo uso relaciones ya cargadas con with()
    // para no disparar una consulta extra por cada tarea (problema N+1).
    public function paraApi(): array
    {
        $archivo = $this->relationLoaded('archivos') ? $this->archivos->sortByDesc('id')->first() : null;
        return [
            'id' => $this->id_tarea,
            'titulo' => $this->titulo,
            'descripcion' => $this->descripcion,
            'fecha_entrega' => $this->fecha_entrega,
            'hora_entrega' => $this->hora_entrega,
            'estado' => $this->estado,
            'materia' => $this->relationLoaded('materia') && $this->materia ? $this->materia->paraApi() : null,
            'archivo' => $archivo?->ruta_archivo,
        ];
    }

    // Agrego los datos propios del estudiante: su entrega, su nota y el estado de su entrega
    public function paraEstudiante(): array
    {
        $entrega = $this->relationLoaded('entregas') ? $this->entregas->first() : null;
        $calificacion = $this->relationLoaded('calificaciones') ? $this->calificaciones->first() : null;
        return $this->paraApi() + [
            'mi_entrega' => $entrega?->paraApi(),
            'mi_calificacion' => $calificacion?->paraApi(),
            'estado_entrega' => estadoEntregaEstudiante($this->getAttributes(), $entrega?->ruta_archivo),
        ];
    }
}
