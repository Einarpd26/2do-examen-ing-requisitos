<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Paciente;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * CU-18: recordatorio de próximas citas (RF15). Aviso dentro del sistema, sin correo: las citas
 * programadas o confirmadas del paciente que todavía no comenzaron y caen hoy o en los próximos
 * días configurados (por defecto 2). Solo expone fecha, hora y médico, nunca el motivo ni datos
 * médicos (RNF16).
 */
class RecordatorioService
{
    /** @return Collection<int, Cita> */
    public function proximas(Paciente $paciente): Collection
    {
        $ahora = CarbonImmutable::now(config('app.timezone'));
        $hasta = $ahora->startOfDay()->addDays((int) config('clinica.citas.recordatorio_dias'));

        return Cita::query()
            ->where('paciente_id', $paciente->id)
            ->whereIn('estado', ['PROGRAMADA', 'CONFIRMADA'])
            ->whereBetween('fecha', [$ahora->toDateString(), $hasta->toDateString()])
            ->with('medico.persona', 'especialidad')
            ->orderBy('fecha')->orderBy('hora_inicio')->orderBy('id')
            ->get()
            // Las de hoy que ya comenzaron no se recuerdan.
            ->filter(fn (Cita $cita) => $cita->inicio()->gt($ahora))
            ->values();
    }
}
