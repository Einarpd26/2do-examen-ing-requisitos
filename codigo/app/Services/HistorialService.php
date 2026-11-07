<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Paciente;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * CU-14: historial de citas de un paciente (RF12). Incluye las citas pasadas, futuras y
 * canceladas, de la más reciente a la más antigua, sin perder la trazabilidad (RN10).
 */
class HistorialService
{
    public const ESTADOS = [
        'PROGRAMADA' => 'Programada',
        'CONFIRMADA' => 'Confirmada',
        'ATENDIDA' => 'Atendida',
        'CANCELADA' => 'Cancelada',
        'INASISTENCIA' => 'Inasistencia',
    ];

    /** Devuelve el estado si es uno de los conocidos; cualquier otro valor se ignora. */
    public function estadoValido(mixed $estado): ?string
    {
        return is_string($estado) && array_key_exists($estado, self::ESTADOS) ? $estado : null;
    }

    /** Citas del paciente, opcionalmente de un solo estado, de 15 en 15. */
    public function de(Paciente $paciente, ?string $estado = null): LengthAwarePaginator
    {
        return Cita::query()
            ->where('paciente_id', $paciente->id)
            ->when($estado !== null, fn ($consulta) => $consulta->where('estado', $estado))
            ->with('medico.persona', 'especialidad', 'sucursal')
            ->orderByDesc('fecha')
            ->orderByDesc('hora_inicio')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();
    }

    /** Cantidad de citas del paciente por estado, para el resumen de la pantalla. */
    public function resumen(Paciente $paciente): array
    {
        $conteo = Cita::query()->where('paciente_id', $paciente->id)
            ->selectRaw('estado, count(*) as total')->groupBy('estado')->pluck('total', 'estado');

        return collect(self::ESTADOS)->map(fn ($etiqueta, $estado) => (int) ($conteo[$estado] ?? 0))->all();
    }
}
