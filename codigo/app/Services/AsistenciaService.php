<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Cita;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * CU-12: control de asistencia (RF10). Transiciones válidas, solo desde una cita vigente:
 * - confirmar: Programada → Confirmada, antes de que la cita comience;
 * - atendida: Programada o Confirmada → Atendida, una vez que la cita comenzó;
 * - inasistencia: Programada o Confirmada → Inasistencia, una vez que la cita comenzó.
 * Una cita cancelada, atendida o con inasistencia no cambia de estado (RN10).
 */
class AsistenciaService
{
    public const CONFIRMAR = 'confirmar';

    public const ATENDIDA = 'atendida';

    public const INASISTENCIA = 'inasistencia';

    /** Estado al que lleva cada acción, y la acción que se anota en la bitácora. */
    private const ACCIONES = [
        self::CONFIRMAR => ['estado' => 'CONFIRMADA', 'bitacora' => 'cita_confirmada', 'texto' => 'confirmada'],
        self::ATENDIDA => ['estado' => 'ATENDIDA', 'bitacora' => 'cita_atendida', 'texto' => 'marcada como atendida'],
        self::INASISTENCIA => ['estado' => 'INASISTENCIA', 'bitacora' => 'cita_inasistencia', 'texto' => 'marcada con inasistencia'],
    ];

    public function __construct(private readonly BitacoraService $bitacora) {}

    /** @return list<string> acciones válidas */
    public static function acciones(): array
    {
        return array_keys(self::ACCIONES);
    }

    /** Momento actual en la zona horaria de la aplicación. */
    public function ahora(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'));
    }

    /**
     * Acciones que se pueden aplicar hoy a la cita, para mostrar solo los botones que corresponden.
     *
     * @return list<string>
     */
    public function posibles(Cita $cita): array
    {
        if (! $cita->vigente()) {
            return [];
        }

        if ($cita->inicio()->gt($this->ahora())) {
            return $cita->estado === 'PROGRAMADA' ? [self::CONFIRMAR] : [];
        }

        return [self::ATENDIDA, self::INASISTENCIA];
    }

    public function registrar(Cita $cita, string $accion, Usuario $actor): Cita
    {
        if (! isset(self::ACCIONES[$accion])) {
            throw new ReglaDeNegocioException('La acción pedida no es válida.');
        }

        return DB::transaction(function () use ($cita, $accion, $actor) {
            // Se bloquea la fila y se lee de nuevo: otra solicitud pudo cambiar su estado antes.
            $actual = Cita::query()->lockForUpdate()->findOrFail($cita->id);
            $datos = self::ACCIONES[$accion];

            if ($actual->estado === 'CANCELADA') {
                throw new ReglaDeNegocioException('La cita está cancelada: no se puede cambiar su estado.');
            }

            if (! $actual->vigente()) {
                throw new ReglaDeNegocioException('La cita ya está '.$this->etiqueta($actual->estado).': no se puede cambiar su estado.');
            }

            $comenzo = $actual->inicio()->lte($this->ahora());

            if ($accion === self::CONFIRMAR) {
                if ($comenzo) {
                    throw new ReglaDeNegocioException('La cita ya comenzó: no se puede confirmar.');
                }

                if ($actual->estado === 'CONFIRMADA') {
                    throw new ReglaDeNegocioException('La cita ya está confirmada.');
                }
            } elseif (! $comenzo) {
                throw new ReglaDeNegocioException('La cita todavía no comenzó: no se puede registrar la asistencia ni la inasistencia.');
            }

            $actual->update(['estado' => $datos['estado']]);

            $this->bitacora->registrar(
                $datos['bitacora'], $actor, 'citas', $actual->id,
                "{$actual->codigo}: {$datos['texto']} ({$actual->fecha->format('d/m/Y')} {$actual->desde()}–{$actual->hasta()})"
            );

            return $actual->refresh();
        });
    }

    private function etiqueta(string $estado): string
    {
        return match ($estado) {
            'ATENDIDA' => 'atendida',
            'INASISTENCIA' => 'registrada con inasistencia',
            default => strtolower($estado),
        };
    }
}
