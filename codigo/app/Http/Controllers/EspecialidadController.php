<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListadoConPestanas;
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
    use ListadoConPestanas;

    /** Pestañas del listado: por defecto solo las activas; las dadas de baja siguen a un clic. */
    protected function etiquetasDePestanas(): array
    {
        return ['activas' => 'Activas', 'inactivas' => 'Inactivas', 'todas' => 'Todas'];
    }

    /**
     * Con una búsqueda y sin pestaña elegida se busca en todas, también en las inactivas,
     * como en el listado de médicos.
     */
    public function index(Request $request): View
    {
        $texto = trim((string) $request->query('q'));
        $vista = $this->vistaDelListado($request, $texto);
        $patron = addcslashes($texto, '%_\\');

        $busqueda = Especialidad::query()
            ->when($texto !== '', fn ($consulta) => $consulta->where('nombre', 'ilike', '%'.$patron.'%'));

        // Los contadores muestran cuántas hay en cada pestaña, para que lo oculto no pase inadvertido.
        $activas = (clone $busqueda)->where('estado', true)->count();
        $inactivas = (clone $busqueda)->where('estado', false)->count();
        $cuentas = ['activas' => $activas, 'inactivas' => $inactivas, 'todas' => $activas + $inactivas];

        $especialidades = $busqueda
            ->withCount('medicos')
            ->when($vista === 'activas', fn ($consulta) => $consulta->where('estado', true))
            ->when($vista === 'inactivas', fn ($consulta) => $consulta->where('estado', false))
            ->orderBy('nombre')
            ->get();

        return view('especialidades.index', [
            'especialidades' => $especialidades,
            'texto' => $texto,
            'vista' => $vista,
            'pestanas' => $this->pestanas('especialidades.index', $cuentas, $texto),
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
}
