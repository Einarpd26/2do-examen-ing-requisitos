<?php

namespace App\Models;

use App\Exceptions\ReglaDeNegocioException;
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

    /**
     * La sucursal donde se registra un horario. Hoy hay una sola; mientras sea así se asigna
     * sola. Si algún día hay varias, esta regla debe reemplazarse por una elección.
     */
    public static function predeterminada(): self
    {
        return static::query()->where('estado', true)->orderBy('id')->first()
            ?? throw new ReglaDeNegocioException('No hay una sucursal activa donde registrar el horario.');
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
