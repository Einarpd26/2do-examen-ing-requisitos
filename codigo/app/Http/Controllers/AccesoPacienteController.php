<?php

namespace App\Http\Controllers;

use App\Http\Requests\RenovarAccesoRequest;
use App\Models\Paciente;
use App\Services\PacienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-04: cuenta del paciente (crear, renovar la contraseña temporal) y verificación de la
 * ficha. La contraseña temporal se muestra una sola vez: viaja en un mensaje de sesión que
 * se pierde al recargar la pantalla.
 */
class AccesoPacienteController extends Controller
{
    public function crear(Request $request, Paciente $paciente, PacienteService $pacientes): RedirectResponse
    {
        return $this->mostrar($paciente, $pacientes->crearAcceso($paciente, $request->user()));
    }

    /** CU-19: renovar la contraseña temporal, indicando cómo se verificó la identidad del titular. */
    public function renovar(RenovarAccesoRequest $request, Paciente $paciente, PacienteService $pacientes): RedirectResponse
    {
        return $this->mostrar(
            $paciente,
            $pacientes->renovarAcceso($paciente, $request->user(), $request->validated('verificacion'))
        );
    }

    public function verificar(Request $request, Paciente $paciente, PacienteService $pacientes): RedirectResponse
    {
        $emitida = $pacientes->verificar($paciente, $request->boolean('renovar_acceso'), $request->user());

        if ($emitida !== null) {
            return $this->mostrar($paciente, $emitida);
        }

        return redirect()->route('pacientes.show', $paciente)->with('exito', 'Ficha verificada.');
    }

    public function credenciales(Paciente $paciente): View|RedirectResponse
    {
        $credenciales = session('credenciales');

        if (! is_array($credenciales) || $credenciales['paciente'] !== $paciente->id) {
            return redirect()->route('pacientes.show', $paciente)->with(
                'error',
                'La contraseña temporal se muestra una sola vez. Si se perdió, renueve la contraseña.'
            );
        }

        return view('pacientes.credenciales', ['paciente' => $paciente->load('persona'), 'credenciales' => $credenciales]);
    }

    /** @param  array{contrasena: string, vence: \Carbon\CarbonImmutable, usuario: string}  $emitida */
    private function mostrar(Paciente $paciente, array $emitida): RedirectResponse
    {
        return redirect()->route('pacientes.credenciales', $paciente)->with('credenciales', [
            'paciente' => $paciente->id,
            'usuario' => $emitida['usuario'],
            'contrasena' => $emitida['contrasena'],
            'vence' => $emitida['vence']->toIso8601String(),
        ]);
    }
}
