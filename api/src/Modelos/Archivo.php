<?php
declare(strict_types=1);

namespace Api\Modelos;

use Illuminate\Database\Eloquent\Model;

// Material adjunto a una tarea por el profesor o el administrador
class Archivo extends Model
{
    protected $table = 'archivos';
    protected $primaryKey = 'id';
    public $timestamps = false;
    protected $fillable = ['id_tarea', 'ruta_archivo'];
}
