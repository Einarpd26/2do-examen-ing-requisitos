<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Services\AsistenciaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * CU-12: control de asistencia. Tres rutas con permisos distintos (RN12):
 * - la Recepcionista (citas:gestionar) confirma, marca atendida o registra inasistencia en cualquier cita;
 * - el Médico (citas:atender) solo marca como atendidas las citas de su propia agenda;
 * - el Paciente (citas:gestionar_propias) solo confirma su propia asistencia.
 */
class AsistenciaController extends Controller
{
    /** Recepcionista: la acción llega en el formulario y solo puede ser una de las tres conocidas. */
    public function recepcion(Request $request, Cita $cita, AsistenciaService $asistencia): RedirectResponse
    {
        $accion = $request->validate([
            'accion' => ['required', Rule::in(AsistenciaService::acciones())],
        ], ['accion.required' => 'Elija la acción a registrar.', 'accion.in' => 'La acción pedida no es válida.'])['accion'];

        $cita = $asistencia->registrar($cita, $accion, $request->user());

        return redirect()->route('citas.comprobante', $cita)
            ->with('exito', "La cita {$cita->codigo} quedó en estado {$this->estado($cita)}.");
    }

    /** Médico: solo las citas de su agenda (otra cita responde 404, como si no existiera para él). */
    public function medico(Request $request, Cita $cita, AsistenciaService $asistencia): RedirectResponse
    {
        $propio = $request->user()->persona->medico;
        abort_unless($propio !== null && $propio->id === $cita->medico_id, 404);

        $cita = $asistencia->registrar($cita, AsistenciaService::ATENDIDA, $request->user());

        return redirect()->route('agenda.index', ['fecha' => $cita->fecha->toDateString()])
            ->with('exito', "La cita {$cita->codigo} quedó en estado {$this->estado($cita)}.");
    }

    /** Paciente: confirma su propia cita. */
    public function paciente(Request $request, Cita $cita, AsistenciaService $asistencia): RedirectResponse
    {
        $propio = $request->user()->persona->paciente;
        abort_unless($propio !== null && $propio->id === $cita->paciente_id, 404);

        $cita = $asistencia->registrar($cita, AsistenciaService::CONFIRMAR, $request->user());

        return redirect()->route('mis-citas.comprobante', $cita)
            ->with('exito', "Su cita {$cita->codigo} quedó confirmada.");
    }

    private function estado(Cita $cita): string
    {
        return ['CONFIRMADA' => 'confirmada', 'ATENDIDA' => 'atendida', 'INASISTENCIA' => 'inasistencia'][$cita->estado] ?? strtolower($cita->estado);
    }
}
