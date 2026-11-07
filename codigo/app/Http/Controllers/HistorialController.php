<?php

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\HistorialService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-14: historial de citas (RF12). Lo usan dos roles:
 * - la Recepcionista, en /pacientes/{id}/historial, para cualquier paciente (incluso desactivado);
 * - el Paciente, en /mi-historial, solo el suyo.
 * Ambos exigen historial:consultar; la ruta de la Recepcionista exige además pacientes:consultar.
 */
class HistorialController extends Controller
{
    public function paciente(Request $request, Paciente $paciente, HistorialService $historial): View
    {
        return $this->mostrar($request, $paciente, $historial, false);
    }

    /** El paciente de una cuenta de paciente es siempre el de la sesión; sin ficha responde 404. */
    public function propio(Request $request, HistorialService $historial): View
    {
        $paciente = $request->user()->persona->paciente;
        abort_if($paciente === null, 404);

        return $this->mostrar($request, $paciente, $historial, true);
    }

    private function mostrar(Request $request, Paciente $paciente, HistorialService $historial, bool $propio): View
    {
        $estado = $historial->estadoValido($request->query('estado'));

        return view('historial.index', [
            'propio' => $propio,
            'paciente' => $paciente->loadMissing('persona'),
            'estado' => $estado,
            'estados' => HistorialService::ESTADOS,
            'citas' => $historial->de($paciente, $estado),
            'resumen' => $historial->resumen($paciente),
        ]);
    }
}
