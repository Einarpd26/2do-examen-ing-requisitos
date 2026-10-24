<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Services\AutenticacionService;
use App\Services\BitacoraService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * CU-01: iniciar y cerrar sesión. La sesión usa una cookie que solo lleva el identificador;
 * los datos quedan en el servidor.
 */
class AccesoController extends Controller
{
    public function formulario(): View
    {
        return view('auth.login');
    }

    public function autenticar(LoginRequest $request, AutenticacionService $acceso): RedirectResponse
    {
        $intento = $acceso->intentar(
            $request->validated('identificador'),
            $request->validated('contrasena'),
        );

        if (! $intento->fueCorrecto()) {
            return back()
                ->withInput($request->only('identificador'))
                ->withErrors(['identificador' => $intento->mensaje()]);
        }

        Auth::login($intento->cuenta);
        // Nuevo identificador de sesión al entrar, para evitar la fijación de sesión.
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function salir(Request $request, BitacoraService $bitacora): RedirectResponse
    {
        $cuenta = $request->user();
        $bitacora->registrar('logout', $cuenta, 'usuarios', $cuenta?->id);

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
