<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Usuario extends Model
{
    protected $table = 'usuarios';
    protected $primaryKey = 'id_usuario';
    public const CREATED_AT = 'hora_creacion';
    public const UPDATED_AT = 'hora_actualizacion';
    protected $fillable = ['nombres', 'rol_id', 'email', 'password', 'estado'];
    // Nunca devuelvo el hash de la contraseña en las respuestas
    protected $hidden = ['password'];

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class, 'rol_id', 'id_rol');
    }

    // Materias en las que esta matriculado (muchos a muchos a traves de la tabla matriculas)
    public function materias(): BelongsToMany
    {
        return $this->belongsToMany(Materia::class, 'matriculas', 'id_usuario', 'id_materia');
    }

    public function entregas(): HasMany
    {
        return $this->hasMany(Entrega::class, 'id_usuario', 'id_usuario');
    }

    public function calificaciones(): HasMany
    {
        return $this->hasMany(Calificacion::class, 'id_usuario', 'id_usuario');
    }

    public function scopeActivos(Builder $consulta): Builder
    {
        return $consulta->where('estado', '1');
    }

    public function scopeConRol(Builder $consulta, string $nombreRol): Builder
    {
        return $consulta->whereHas('rol', fn(Builder $rol) => $rol->where('nombre_rol', $nombreRol));
    }

    public function paraApi(): array
    {
        return [
            'id' => $this->id_usuario,
            'nombres' => $this->nombres,
            'email' => $this->email,
            'rol' => $this->relationLoaded('rol') && $this->rol ? $this->rol->paraApi() : null,
            'estado' => $this->estado,
        ];
    }
}
