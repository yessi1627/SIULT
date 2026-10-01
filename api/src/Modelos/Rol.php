<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rol extends Model
{
    protected $table = 'roles';
    protected $primaryKey = 'id_rol';
    // La tabla usa nombres propios para las fechas de creacion y actualizacion
    public const CREATED_AT = 'hora_creacion';
    public const UPDATED_AT = 'hora_actualizacion';
    protected $fillable = ['nombre_rol', 'estado'];

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class, 'rol_id', 'id_rol');
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('estado', '1');
    }

    public function paraApi(): array
    {
        return ['id' => $this->id_rol, 'nombre' => $this->nombre_rol];
    }
}
