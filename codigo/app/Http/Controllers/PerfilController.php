<?php

namespace App\Http\Controllers;

use App\Http\Requests\PerfilRequest;
use App\Models\Paciente;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** CU-08: el paciente consulta su perfil y modifica su teléfono; la contraseña se cambia aparte. */
class PerfilController extends Controller
{
    public function mostrar(): View
    {
        return view('perfil.mostrar', ['paciente' => $this->paciente()]);
    }

    public function actualizar(PerfilRequest $request, BitacoraService $bitacora): RedirectResponse
    {
        $paciente = $this->paciente();
        $telefono = $request->validated('telefono');

        if ($paciente->persona->telefono !== $telefono) {
            $paciente->persona->update(['telefono' => $telefono]);
            $bitacora->registrar('perfil_modificado', $request->user(), 'personas', $paciente->persona_id, 'Teléfono');
        }

        return redirect()->route('perfil.mostrar')->with('exito', 'Su teléfono fue actualizado.');
    }

    /** La ficha del paciente que inició sesión; una cuenta sin ficha no tiene perfil. */
    private function paciente(): Paciente
    {
        $paciente = request()->user()->persona->paciente;
        abort_if($paciente === null, 404);

        return $paciente->loadMissing('persona');
    }
}
