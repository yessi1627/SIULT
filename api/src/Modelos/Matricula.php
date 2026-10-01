<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Matricula extends Model
{
    protected $table = 'matriculas';
    protected $primaryKey = 'id_matricula';
    // Esta tabla solo tiene fecha_matricula, que asigno yo
    public $timestamps = false;
    protected $fillable = ['id_usuario', 'id_materia', 'fecha_matricula'];

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'id_usuario', 'id_usuario');
    }

    public function materia(): BelongsTo
    {
        return $this->belongsTo(Materia::class, 'id_materia', 'id_materia');
    }

    public function paraApi(): array
    {
        return [
            'id' => $this->id_matricula,
            'fecha' => $this->fecha_matricula,
            'estudiante' => $this->relationLoaded('usuario') && $this->usuario
                ? ['id' => $this->usuario->id_usuario, 'nombres' => $this->usuario->nombres, 'email' => $this->usuario->email]
                : null,
            'materia' => $this->relationLoaded('materia') && $this->materia ? $this->materia->paraApi() : null,
        ];
    }
}
