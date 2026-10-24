<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Especialidad médica. Define la duración de la cita, en minutos.
 */
class Especialidad extends Model
{
    public $timestamps = false;

    protected $table = 'especialidades';

    protected $fillable = [
        'nombre',
        'descripcion',
        'duracion_min',
        'estado',
    ];

    protected function casts(): array
    {
        return [
            'duracion_min' => 'integer',
            'estado' => 'boolean',
        ];
    }

    public function medicos(): BelongsToMany
    {
        return $this->belongsToMany(Medico::class, 'medico_especialidad', 'especialidad_id', 'medico_id');
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }
}
