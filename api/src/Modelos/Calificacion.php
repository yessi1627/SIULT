<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Calificacion extends Model
{
    protected $table = 'calificaciones';
    protected $primaryKey = 'id_calificacion';
    public $timestamps = false;
    protected $fillable = ['id_tarea', 'id_usuario', 'nota', 'observacion', 'fecha_calificacion', 'version'];
    // version: contador para el bloqueo optimista (ver CalificacionesControlador::guardar)
    protected $casts = ['nota' => 'float', 'version' => 'integer'];

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class, 'id_tarea', 'id_tarea');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function paraApi(): array
    {
        $datos = [
            'id' => $this->id_calificacion,
            'id_tarea' => $this->id_tarea,
            'id_usuario' => $this->id_usuario,
            'nota' => $this->nota,
            'observacion' => $this->observacion,
            'fecha' => $this->fecha_calificacion,
            'version' => $this->version,
        ];
        if ($this->relationLoaded('usuario') && $this->usuario) {
            $datos['estudiante'] = $this->usuario->nombres;
        }
        if ($this->relationLoaded('tarea') && $this->tarea) {
            $datos['tarea'] = $this->tarea->titulo;
            if ($this->tarea->relationLoaded('materia') && $this->tarea->materia) {
                $datos['materia'] = $this->tarea->materia->nombre_materia;
            }
        }
        return $datos;
    }
}
