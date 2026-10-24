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
    /** Días de la semana según ISO 8601: 1 es lunes y 7 es domingo. */
    public const DIAS = [
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
        7 => 'Domingo',
    ];

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

    public function nombreDia(): string
    {
        return self::DIAS[$this->dia_semana];
    }

    /** Hora inicial como HH:MM (la base la devuelve con segundos). */
    public function desde(): string
    {
        return substr($this->hora_inicio, 0, 5);
    }

    /** Hora final como HH:MM. */
    public function hasta(): string
    {
        return substr($this->hora_fin, 0, 5);
    }

    /** Texto del bloque, por ejemplo «Lunes 08:00–12:00». */
    public function descripcion(): string
    {
        return "{$this->nombreDia()} {$this->desde()}–{$this->hasta()}";
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
