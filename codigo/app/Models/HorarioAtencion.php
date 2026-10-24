<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bloque de atención de un médico: día de la semana (1 a 7), hora inicial y hora final.
 * La base impide bloques superpuestos del mismo médico en el mismo día.
 */
class HorarioAtencion extends Model
{
    public $timestamps = false;

    protected $table = 'horarios_atencion';

    // rango lo calcula la base de datos y no se asigna.
    protected $fillable = [
        'medico_id',
        'sucursal_id',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'consultorio',
    ];

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
