<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Consultorio de una sucursal (CU-20, ADR-002). Se asigna a los bloques de atención y la base
 * impide que dos bloques se superpongan en el mismo consultorio el mismo día (RN18).
 */
class Consultorio extends Model
{
    public $timestamps = false;

    protected $table = 'consultorios';

    protected $fillable = [
        'sucursal_id',
        'nombre',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'estado' => 'boolean',
        ];
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioAtencion::class);
    }
}
