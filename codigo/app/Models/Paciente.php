<?php

namespace App\Models;

use App\Models\Concerns\TieneCodigoFormateado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Ficha del paciente: datos clínicos y estado de verificación. Los datos personales
 * están en la Persona asociada.
 */
class Paciente extends Model
{
    use TieneCodigoFormateado;

    public const PREFIJO_CODIGO = 'PAC';

    protected $table = 'pacientes';

    protected $fillable = [
        'persona_id',
        'verificado',
        'estado',
        'tipo_sangre',
        'alergias',
        'enfermedades_cronicas',
        'medicamentos',
        'antecedentes',
        'emergencia_nombre',
        'emergencia_parentesco',
        'emergencia_telefono',
    ];

    protected function casts(): array
    {
        return [
            'verificado' => 'boolean',
            'estado' => 'boolean',
        ];
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class);
    }
}
