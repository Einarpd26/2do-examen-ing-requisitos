<?php

namespace App\Models;

use App\Models\Concerns\TieneCodigoFormateado;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cita médica (Ciclo #2). La base impide citas superpuestas del mismo médico, salvo las
 * canceladas, y exige que el médico ejerza la especialidad elegida.
 */
class Cita extends Model
{
    use TieneCodigoFormateado;

    public const PREFIJO_CODIGO = 'CM';

    protected $table = 'citas';

    // periodo lo calcula la base de datos y no se asigna.
    protected $fillable = [
        'paciente_id',
        'medico_id',
        'especialidad_id',
        'sucursal_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
        'modalidad',
        'estado',
        'motivo',
        'motivo_cancelacion',
    ];

    protected function casts(): array
    {
        return ['fecha' => 'date'];
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function especialidad(): BelongsTo
    {
        return $this->belongsTo(Especialidad::class);
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function nota(): HasOne
    {
        return $this->hasOne(NotaClinica::class);
    }
}
