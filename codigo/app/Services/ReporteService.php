<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\Paciente;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * CU-15: reportes administrativos (RF13). Todos filtran por un rango de fechas (inclusivo) y
 * muestran totales. Las citas se pueden filtrar además por médico, especialidad y estado.
 */
class ReporteService
{
    /** La fecha en formato AAAA-MM-DD, o null si falta o no es una fecha real. */
    public function fecha(mixed $texto): ?CarbonImmutable
    {
        if (! is_string($texto) || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $texto)) {
            return null;
        }

        $dia = CarbonImmutable::createFromFormat('!Y-m-d', $texto, config('app.timezone'));

        return $dia !== false && $dia->format('Y-m-d') === $texto ? $dia : null;
    }

    /** Rango por defecto: del primer día del mes en curso a hoy (hora de La Paz). */
    public function rangoPorDefecto(): array
    {
        $hoy = CarbonImmutable::now(config('app.timezone'))->startOfDay();

        return [$hoy->startOfMonth(), $hoy];
    }

    /** Consulta base de citas del rango, con los filtros opcionales. */
    private function citas(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $medico, ?int $especialidad, ?string $estado): Builder
    {
        return Cita::query()
            ->whereBetween('citas.fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->when($medico !== null, fn ($consulta) => $consulta->where('citas.medico_id', $medico))
            ->when($especialidad !== null, fn ($consulta) => $consulta->where('citas.especialidad_id', $especialidad))
            ->when($estado !== null, fn ($consulta) => $consulta->where('citas.estado', $estado));
    }

    /** Listado de citas en orden cronológico, de 25 en 25. */
    public function listado(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $medico, ?int $especialidad, ?string $estado): LengthAwarePaginator
    {
        return $this->citas($desde, $hasta, $medico, $especialidad, $estado)
            ->with('paciente.persona', 'medico.persona', 'especialidad')
            ->orderBy('citas.fecha')->orderBy('citas.hora_inicio')->orderBy('citas.id')
            ->paginate(25)
            ->withQueryString();
    }

    /**
     * Cantidad de citas por estado en el rango (incluye los estados en cero).
     *
     * @return array<string, int>
     */
    public function porEstado(CarbonImmutable $desde, CarbonImmutable $hasta, ?int $medico, ?int $especialidad, ?string $estado): array
    {
        $conteo = $this->citas($desde, $hasta, $medico, $especialidad, $estado)
            ->selectRaw('citas.estado, count(*) as total')->groupBy('citas.estado')->pluck('total', 'estado');

        return collect(HistorialService::ESTADOS)->map(fn ($etiqueta, $codigo) => (int) ($conteo[$codigo] ?? 0))->all();
    }

    /**
     * Cantidad de citas por médico o por especialidad, de mayor a menor.
     *
     * @return Collection<int, object{nombre: string, total: int}>
     */
    public function agrupadas(string $por, CarbonImmutable $desde, CarbonImmutable $hasta, ?int $medico, ?int $especialidad, ?string $estado): Collection
    {
        $consulta = $this->citas($desde, $hasta, $medico, $especialidad, $estado)->reorder();

        if ($por === 'medico') {
            return $consulta->join('medicos', 'medicos.id', '=', 'citas.medico_id')
                ->join('personas', 'personas.id', '=', 'medicos.persona_id')
                ->selectRaw('personas.nombre_completo as nombre, count(*) as total')
                ->groupBy('personas.nombre_completo')->orderByDesc('total')->orderBy('nombre')->get();
        }

        return $consulta->join('especialidades', 'especialidades.id', '=', 'citas.especialidad_id')
            ->selectRaw('especialidades.nombre as nombre, count(*) as total')
            ->groupBy('especialidades.nombre')->orderByDesc('total')->orderBy('nombre')->get();
    }

    /** Pacientes registrados en el rango, del más reciente al más antiguo, de 25 en 25. */
    public function pacientes(CarbonImmutable $desde, CarbonImmutable $hasta): LengthAwarePaginator
    {
        return $this->consultaPacientes($desde, $hasta)
            ->with('persona')
            ->orderByDesc('pacientes.created_at')->orderByDesc('pacientes.id')
            ->paginate(25)
            ->withQueryString();
    }

    /** Cuántos de los pacientes del rango están verificados y cuántos activos. */
    public function totalesPacientes(CarbonImmutable $desde, CarbonImmutable $hasta): array
    {
        $consulta = $this->consultaPacientes($desde, $hasta);

        return [
            'total' => (clone $consulta)->count(),
            'verificados' => (clone $consulta)->where('verificado', true)->count(),
            'activos' => (clone $consulta)->where('estado', true)->count(),
        ];
    }

    private function consultaPacientes(CarbonImmutable $desde, CarbonImmutable $hasta): Builder
    {
        return Paciente::query()->whereBetween('pacientes.created_at', [$desde->startOfDay(), $hasta->endOfDay()]);
    }
}
