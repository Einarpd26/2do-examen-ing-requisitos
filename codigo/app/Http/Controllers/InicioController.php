<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Pantalla de inicio después de entrar: saludo y accesos según los permisos del rol.
 */
class InicioController extends Controller
{
    public function __invoke(Request $request): View
    {
        return view('inicio');
    }
}
