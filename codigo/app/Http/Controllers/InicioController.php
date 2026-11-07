<?php

namespace App\Http\Controllers;

use App\Services\RecordatorioService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla de inicio después de entrar: saludo y accesos según los permisos del rol. Al Paciente
 * (recordatorios:consultar) se le muestra además el aviso de sus próximas citas (CU-18).
 */
class InicioController extends Controller
{
    public function __invoke(Request $request, RecordatorioService $recordatorios): View
    {
        $cuenta = $request->user();
        $paciente = $cuenta->tienePermiso('recordatorios:consultar') ? $cuenta->persona->paciente : null;

        return view('inicio', [
            'recordatorios' => $paciente === null ? collect() : $recordatorios->proximas($paciente),
        ]);
    }
}
