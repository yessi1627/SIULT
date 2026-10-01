<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Notificacion extends Model
{
    protected $table = 'notificaciones';
    protected $primaryKey = 'id_notificacion';
    public $timestamps = false;
    protected $fillable = ['mensaje', 'id_tarea', 'leido'];
    protected $casts = ['leido' => 'boolean'];

    public function tarea(): BelongsTo
    {
        return $this->belongsTo(Tarea::class, 'id_tarea', 'id_tarea');
    }

    public function paraApi(): array
    {
        return [
            'id' => $this->id_notificacion,
            'mensaje' => $this->mensaje,
            'id_tarea' => $this->id_tarea,
            'fecha' => $this->fecha_creacion,
            'leido' => $this->leido,
        ];
    }
}
