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
    /** Pestañas del listado: por defecto solo las activas; las dadas de baja siguen a un clic. */
    private const VISTAS = ['activas', 'inactivas', 'todas'];

    public function index(Request $request): View
    {
        $vista = $this->vista($request->query('estado'));

        $especialidades = Especialidad::query()
            ->withCount('medicos')
            ->when($vista === 'activas', fn ($consulta) => $consulta->where('estado', true))
            ->when($vista === 'inactivas', fn ($consulta) => $consulta->where('estado', false))
            ->orderBy('nombre')
            ->get();

        // Los contadores muestran cuántas hay en cada pestaña, para que lo oculto no pase inadvertido.
        $activas = Especialidad::where('estado', true)->count();
        $inactivas = Especialidad::where('estado', false)->count();

        return view('especialidades.index', [
            'especialidades' => $especialidades,
            'vista' => $vista,
            'cuentas' => ['activas' => $activas, 'inactivas' => $inactivas, 'todas' => $activas + $inactivas],
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

    /**
     * Desactiva o reactiva la especialidad; nunca se elimina. Vuelve a la misma pestaña del
     * listado desde la que se hizo el cambio.
     */
    public function estado(Request $request, Especialidad $especialidad, EspecialidadService $especialidades): RedirectResponse
    {
        $activa = $request->boolean('activa');
        $especialidades->cambiarEstado($especialidad, $activa, $request->user());

        $vista = $this->vista($request->input('vista'));

        return redirect()
            ->route('especialidades.index', $vista === 'activas' ? [] : ['estado' => $vista])
            ->with('exito', $activa
                ? 'Especialidad reactivada. Ya aparece en la pestaña Activas.'
                : 'Especialidad desactivada. Puede verla en la pestaña Inactivas; lo ya registrado se conserva.');
    }

    /** Una pestaña desconocida cae en la de activas. */
    private function vista(mixed $valor): string
    {
        return in_array($valor, self::VISTAS, true) ? $valor : 'activas';
    }
}
