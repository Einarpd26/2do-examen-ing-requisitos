<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sucursal de la clínica. Hoy hay una sola, cargada con los catálogos iniciales.
 */
class Sucursal extends Model
{
    public $timestamps = false;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre',
        'direccion',
        'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioAtencion::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }
}
