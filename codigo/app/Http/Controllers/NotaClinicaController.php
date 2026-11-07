<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Requests\NotaClinicaRequest;
use App\Models\Cita;
use App\Models\NotaClinica;
use App\Models\Paciente;
use App\Services\NotaClinicaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-16 y CU-17: notas clínicas (RF16).
 * - El Médico (notas:registrar) escribe y edita la nota de sus propias citas atendidas.
 * - La Recepcionista y el Médico (notas:consultar) ven las notas de un paciente en solo lectura;
 *   el Médico solo las de pacientes que tienen o tuvieron cita con él.
 * - El Paciente (notas:consultar) ve las suyas, una vez que su ficha está verificada.
 */
class NotaClinicaController extends Controller
{
    /** CU-16: formulario de la nota de una cita atendida del propio médico. */
    public function formulario(Request $request, Cita $cita, NotaClinicaService $notas): View
    {
        $this->exigirCitaPropia($request, $cita, $notas);

        if ($cita->estado !== 'ATENDIDA') {
            throw new ReglaDeNegocioException('Solo se registra la nota clínica de una cita atendida.');
        }

        $cita->load('paciente.persona', 'especialidad');

        return view('notas.formulario', [
            'cita' => $cita,
            'nota' => NotaClinica::query()->where('cita_id', $cita->id)->first(),
        ]);
    }

    /** CU-16: guarda (crea o sobrescribe) la nota. */
    public function guardar(NotaClinicaRequest $request, Cita $cita, NotaClinicaService $notas): RedirectResponse
    {
        $this->exigirCitaPropia($request, $cita, $notas);

        $notas->guardar($cita, $request->validated(), $request->user());

        return redirect()->route('agenda.index', ['fecha' => $cita->fecha->toDateString()])
            ->with('exito', "Se guardó la nota clínica de la cita {$cita->codigo}.");
    }

    /** CU-17: notas de un paciente para la Recepcionista (cualquiera) y el Médico (los suyos). */
    public function paciente(Request $request, Paciente $paciente, NotaClinicaService $notas): View
    {
        $medico = $notas->medicoDe($request->user());

        if ($request->user()->rol->codigo === 'MEDICO') {
            abort_unless($medico !== null && $notas->atiende($medico, $paciente), 404);
        }

        return view('notas.index', [
            'propio' => false,
            'paciente' => $paciente->loadMissing('persona'),
            'pendiente' => false,
            'notas' => $notas->de($paciente),
            'puedeEditar' => $medico?->id,
        ]);
    }

    /** CU-17: el Paciente ve sus propias notas; con la ficha pendiente de verificación, no ve ninguna. */
    public function propias(Request $request, NotaClinicaService $notas): View
    {
        $paciente = $request->user()->persona->paciente;
        abort_if($paciente === null, 404);

        return view('notas.index', [
            'propio' => true,
            'paciente' => $paciente->loadMissing('persona'),
            'pendiente' => ! $paciente->verificado,
            'notas' => $paciente->verificado ? $notas->de($paciente) : collect(),
            'puedeEditar' => null,
        ]);
    }

    /** El Médico solo trabaja con las citas de su agenda; la de otro responde 404 (RN12). */
    private function exigirCitaPropia(Request $request, Cita $cita, NotaClinicaService $notas): void
    {
        $medico = $notas->medicoDe($request->user());
        abort_unless($medico !== null && $medico->id === $cita->medico_id, 404);
    }
}
