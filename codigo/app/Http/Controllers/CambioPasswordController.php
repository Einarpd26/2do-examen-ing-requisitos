<?php

namespace App\Http\Controllers;

use App\Http\Requests\CambiarPasswordRequest;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Cambio de contraseña. Es obligatorio en el primer ingreso con una contraseña temporal
 * (RNF18) y también lo usa el paciente desde su perfil (CU-08).
 */
class CambioPasswordController extends Controller
{
    public function formulario(): View
    {
        return view('auth.cambiar-password');
    }

    public function actualizar(CambiarPasswordRequest $request, BitacoraService $bitacora): RedirectResponse
    {
        $cuenta = $request->user();

        if (! Hash::check($request->validated('password_actual'), $cuenta->password_hash)) {
            return back()->withErrors(['password_actual' => 'La contraseña actual no es correcta.']);
        }

        // Las dos columnas de la contraseña temporal se limpian a la vez: la base lo exige.
        $cuenta->forceFill([
            'password_hash' => $request->validated('password'),
            'debe_cambiar_password' => false,
            'password_temporal_vence' => null,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        $bitacora->registrar('cambio_password', $cuenta, 'usuarios', $cuenta->id);
        $request->session()->regenerate();

        return redirect()->route('inicio')->with('exito', 'Su contraseña fue actualizada.');
    }
}
