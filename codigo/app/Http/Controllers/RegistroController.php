<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegistroPacienteRequest;
use App\Services\RegistroPacienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * CU-02: registro propio del paciente. Es público, así que las rutas limitan la cantidad
 * de solicitudes por IP.
 */
class RegistroController extends Controller
{
    public function formulario(): View
    {
        return view('auth.registro');
    }

    public function guardar(RegistroPacienteRequest $request, RegistroPacienteService $registro): RedirectResponse
    {
        $registro->registrar($request->validated());

        return redirect()->route('login')->with(
            'exito',
            'Su cuenta fue creada. Ingrese con su CI y su contraseña. Presente su documento de identidad en recepción para verificar su ficha.'
        );
    }
}
