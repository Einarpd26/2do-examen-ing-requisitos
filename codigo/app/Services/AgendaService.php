<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Medico;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * CU-13: agenda de un médico para una fecha (RF11). Lista las citas del día en orden de hora,
 * incluidas las canceladas, y permite filtrar por estado.
 */
class AgendaService
{
    /** La fecha en formato AAAA-MM-DD, o null si falta o no es una fecha real. */
    public function fechaValida(mixed $fecha): ?CarbonImmutable
    {
        if (! is_string($fecha) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            return null;
        }

        $dia = CarbonImmutable::createFromFormat('!Y-m-d', $fecha, config('app.timezone'));

        return $dia !== false && $dia->format('Y-m-d') === $fecha ? $dia : null;
    }

    /** @return Collection<int, Cita> */
    public function del(Medico $medico, CarbonImmutable $fecha, ?string $estado = null): Collection
    {
        return Cita::query()
            ->where('medico_id', $medico->id)
            ->whereDate('fecha', $fecha->toDateString())
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->with('paciente.persona', 'especialidad')
            ->orderBy('hora_inicio')
            ->orderBy('id')
            ->get();
    }
}
