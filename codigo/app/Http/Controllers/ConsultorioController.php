<?php

namespace App\Http\Controllers;

use App\Http\Requests\ConsultorioRequest;
use App\Models\Consultorio;
use App\Services\ConsultorioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-20: gestionar consultorios (ADR-002). Las rutas exigen el permiso consultorios:gestionar.
 */
class ConsultorioController extends Controller
{
    /** Pestañas del listado: por defecto solo los activos; los dados de baja siguen a un clic. */
    private const VISTAS = ['activos', 'inactivos', 'todos'];

    public function index(Request $request): View
    {
        $vista = $this->vista($request->query('estado'));

        $consultorios = Consultorio::query()
            ->withCount('horarios')
            ->when($vista === 'activos', fn ($consulta) => $consulta->where('estado', true))
            ->when($vista === 'inactivos', fn ($consulta) => $consulta->where('estado', false))
            ->orderBy('nombre')
            ->get();

        $activos = Consultorio::where('estado', true)->count();
        $inactivos = Consultorio::where('estado', false)->count();

        return view('consultorios.index', [
            'consultorios' => $consultorios,
            'vista' => $vista,
            'cuentas' => ['activos' => $activos, 'inactivos' => $inactivos, 'todos' => $activos + $inactivos],
        ]);
    }

    public function create(): View
    {
        return view('consultorios.create');
    }

    public function store(ConsultorioRequest $request, ConsultorioService $consultorios): RedirectResponse
    {
        $consultorios->registrar($request->validated(), $request->user());

        return redirect()->route('consultorios.index')->with('exito', 'Consultorio registrado.');
    }

    public function edit(Consultorio $consultorio): View
    {
        return view('consultorios.edit', ['consultorio' => $consultorio]);
    }

    public function update(ConsultorioRequest $request, Consultorio $consultorio, ConsultorioService $consultorios): RedirectResponse
    {
        $consultorios->actualizar($consultorio, $request->validated(), $request->user());

        return redirect()->route('consultorios.index')->with('exito', 'Consultorio actualizado.');
    }

    /**
     * Desactiva o reactiva el consultorio; nunca se elimina. Vuelve a la misma pestaña del
     * listado desde la que se hizo el cambio.
     */
    public function estado(Request $request, Consultorio $consultorio, ConsultorioService $consultorios): RedirectResponse
    {
        $activo = $request->boolean('activo');
        $consultorios->cambiarEstado($consultorio, $activo, $request->user());

        $vista = $this->vista($request->input('vista'));

        return redirect()
            ->route('consultorios.index', $vista === 'activos' ? [] : ['estado' => $vista])
            ->with('exito', $activo
                ? 'Consultorio reactivado. Ya se puede asignar a los horarios.'
                : 'Consultorio desactivado. Puede verlo en la pestaña Inactivos.');
    }

    /** Una pestaña desconocida cae en la de activos. */
    private function vista(mixed $valor): string
    {
        return in_array($valor, self::VISTAS, true) ? $valor : 'activos';
    }
}
