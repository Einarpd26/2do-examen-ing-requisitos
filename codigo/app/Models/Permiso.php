<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Permiso del catálogo, con formato recurso:accion (por ejemplo, pacientes:gestionar).
 */
class Permiso extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'permisos';

    protected $guarded = ['*'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Rol::class, 'rol_permisos', 'permiso_id', 'rol_id');
    }
}
