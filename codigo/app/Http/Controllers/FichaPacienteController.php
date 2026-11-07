<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\Paciente;
use App\Services\NotaClinicaService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-13: ficha de solo lectura de un paciente para su médico (RF11, enunciado 3.3). Muestra los
 * datos básicos y de contacto necesarios para atenderlo, su información médica, su contacto de
 * emergencia y sus citas con este médico. No incluye el correo, la dirección ni los datos de la
 * cuenta, y nunca permite editar. Con la ficha pendiente de verificación se ocultan la información
 * médica y el contacto de emergencia, igual que en el perfil del propio paciente.
 */
class FichaPacienteController extends Controller
{
    /** El Médico solo abre a los pacientes que tienen o tuvieron una cita no cancelada con él (RN12). */
    public function __invoke(Request $request, Paciente $paciente, NotaClinicaService $notas): View
    {
        $medico = $notas->medicoDe($request->user());
        abort_unless($medico !== null && $notas->atiende($medico, $paciente), 404);

        return view('pacientes.ficha-medico', [
            'paciente' => $paciente->loadMissing('persona'),
            'citas' => Cita::query()
                ->where('paciente_id', $paciente->id)->where('medico_id', $medico->id)
                ->with('especialidad')
                ->orderByDesc('fecha')->orderByDesc('hora_inicio')
                ->limit(20)->get(),
        ]);
    }
}
