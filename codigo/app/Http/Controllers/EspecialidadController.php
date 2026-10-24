<?php

namespace App\Http\Controllers;

use App\Http\Requests\EspecialidadRequest;
use App\Models\Especialidad;
use App\Services\EspecialidadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-06: gestionar especialidades. Las rutas exigen el permiso especialidades:gestionar.
 */
class EspecialidadController extends Controller
{
    public function index(): View
    {
        return view('especialidades.index', [
            'especialidades' => Especialidad::query()->withCount('medicos')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        return view('especialidades.create');
    }

    public function store(EspecialidadRequest $request, EspecialidadService $especialidades): RedirectResponse
    {
        $especialidades->registrar($request->validated(), $request->user());

        return redirect()->route('especialidades.index')->with('exito', 'Especialidad registrada.');
    }

    public function edit(Especialidad $especialidad): View
    {
        return view('especialidades.edit', ['especialidad' => $especialidad]);
    }

    public function update(
        EspecialidadRequest $request,
        Especialidad $especialidad,
        EspecialidadService $especialidades
    ): RedirectResponse {
        $especialidades->actualizar($especialidad, $request->validated(), $request->user());

        return redirect()->route('especialidades.index')->with('exito', 'Especialidad actualizada.');
    }

    /** Desactiva o reactiva la especialidad; nunca se elimina. */
    public function estado(Request $request, Especialidad $especialidad, EspecialidadService $especialidades): RedirectResponse
    {
        $activa = $request->boolean('activa');
        $especialidades->cambiarEstado($especialidad, $activa, $request->user());

        return redirect()->route('especialidades.index')->with(
            'exito',
            $activa ? 'Especialidad reactivada.' : 'Especialidad desactivada. Lo ya registrado se conserva.'
        );
    }
}
