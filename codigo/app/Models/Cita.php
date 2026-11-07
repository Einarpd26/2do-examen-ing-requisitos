<?php

namespace App\Models;

use App\Models\Concerns\TieneCodigoFormateado;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Cita médica (Ciclo #2). La base impide citas superpuestas del mismo médico y del mismo
 * paciente, salvo las canceladas, y exige que el médico ejerza la especialidad elegida.
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

    /** Momento en que empieza la cita, en la zona horaria de la aplicación (America/La_Paz). */
    public function inicio(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fecha->toDateString().' '.$this->hora_inicio, config('app.timezone'));
    }

    /** ¿La cita sigue vigente (programada o confirmada)? Solo esas pueden cambiar de estado. */
    public function vigente(): bool
    {
        return in_array($this->estado, ['PROGRAMADA', 'CONFIRMADA'], true);
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
