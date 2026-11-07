<?php

namespace App\Http\Controllers;

use App\Models\Medico;
use App\Services\AgendaService;
use App\Services\HistorialService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-13: agenda médica (RF11). Exige agenda:consultar. La Recepcionista elige al médico, la fecha
 * y el estado; el Médico ve siempre su propia agenda y nunca la de otro (RN12).
 */
class AgendaController extends Controller
{
    public function index(Request $request, AgendaService $agenda): View
    {
        $propia = $request->user()->rol->codigo === 'MEDICO';
        $estados = HistorialService::ESTADOS;

        if ($propia) {
            $medico = $request->user()->persona->medico;
            abort_if($medico === null, 404);
            $medico->loadMissing('persona');
        } else {
            $medico = $this->medicoElegido($request->query('medico'));
        }

        $fecha = $agenda->fechaValida($request->query('fecha'));
        $aviso = null;

        if ($fecha === null) {
            $aviso = $request->query('fecha') !== null && $request->query('fecha') !== ''
                ? 'La fecha no es válida; se muestra la agenda de hoy.' : null;
            $fecha = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        }

        $estado = $request->query('estado');
        $estado = is_string($estado) && array_key_exists($estado, $estados) ? $estado : null;

        return view('agenda.index', [
            'propia' => $propia,
            'medico' => $medico,
            'medicos' => $propia ? collect() : $this->medicos(),
            'fecha' => $fecha,
            'estado' => $estado,
            'estados' => $estados,
            'citas' => $medico === null ? collect() : $agenda->del($medico, $fecha, $estado),
            'aviso' => $aviso,
        ]);
    }

    /** El médico elegido por la Recepcionista, o null si no se eligió o no existe. */
    private function medicoElegido(mixed $id): ?Medico
    {
        return is_numeric($id) ? Medico::query()->with('persona')->find((int) $id) : null;
    }

    /** Todos los médicos por nombre; los desactivados conservan su agenda histórica (RN11). */
    private function medicos()
    {
        return Medico::query()
            ->join('personas', 'personas.id', '=', 'medicos.persona_id')
            ->select('medicos.*')
            ->with('persona')
            ->orderBy('personas.nombre_completo')
            ->get();
    }
}
