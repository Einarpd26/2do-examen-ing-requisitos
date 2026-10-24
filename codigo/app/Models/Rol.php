<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Rol del catálogo (ADMINISTRADOR, RECEPCIONISTA, MEDICO, PACIENTE). Es de solo lectura
 * para la aplicación: el catálogo se carga con 003_catalogos_iniciales.sql.
 */
class Rol extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'roles';

    protected $guarded = ['*'];

    public function permisos(): BelongsToMany
    {
        return $this->belongsToMany(Permiso::class, 'rol_permisos', 'rol_id', 'permiso_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(Usuario::class);
    }
}
