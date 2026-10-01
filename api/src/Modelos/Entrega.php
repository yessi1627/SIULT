<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Entrega extends Model
{
    protected $table = 'entregas';
    protected $primaryKey = 'id_entrega';
    public $timestamps = false;
    protected $fillable = ['id_tarea', 'id_usuario', 'ruta_archivo', 'nombre_original', 'fecha_entrega'];

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
            'id' => $this->id_entrega,
            'id_tarea' => $this->id_tarea,
            'id_usuario' => $this->id_usuario,
            'archivo' => $this->ruta_archivo,
            'nombre_original' => $this->nombre_original,
            'fecha' => $this->fecha_entrega,
        ];
        if ($this->relationLoaded('usuario') && $this->usuario) {
            $datos['estudiante'] = $this->usuario->nombres;
        }
        return $datos;
    }
}
