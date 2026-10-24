<?php

namespace App\Models;

use App\Models\Concerns\TieneCodigoFormateado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Médico: matrícula profesional, estado y especialidades. Los datos personales están
 * en la Persona asociada.
 */
class Medico extends Model
{
    use TieneCodigoFormateado;

    public const PREFIJO_CODIGO = 'MED';

    protected $table = 'medicos';

    protected $fillable = [
        'persona_id',
        'nro_registro_profesional',
        'estado',
    ];

    protected function casts(): array
    {
        return ['estado' => 'boolean'];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function especialidades(): BelongsToMany
    {
        return $this->belongsToMany(Especialidad::class, 'medico_especialidad', 'medico_id', 'especialidad_id');
    }

    public function horarios(): HasMany
    {
        return $this->hasMany(HorarioAtencion::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }

    public function notas(): HasMany
    {
        return $this->hasMany(NotaClinica::class);
    }
}
