<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListadoConPestanas;
use App\Http\Requests\MedicoRequest;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Services\MedicoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-05: gestionar médicos. Las rutas exigen el permiso medicos:gestionar.
 */
class MedicoController extends Controller
{
    use ListadoConPestanas;

    protected function etiquetasDePestanas(): array
    {
        return ['activas' => 'Activos', 'inactivas' => 'Inactivos', 'todas' => 'Todos'];
    }

    public function index(Request $request): View
    {
        $texto = trim((string) $request->query('q'));
        $vista = $this->vistaDelListado($request, $texto);

        $busqueda = Medico::query()
            ->join('personas', 'personas.id', '=', 'medicos.persona_id')
            ->select('medicos.*')
            ->with('persona.usuario.rol', 'especialidades')
            ->when($texto !== '', fn ($consulta) => $this->filtrarPorTexto($consulta, $texto));

        $activos = (clone $busqueda)->where('medicos.estado', true)->count();
        $inactivos = (clone $busqueda)->where('medicos.estado', false)->count();
        $cuentas = ['activas' => $activos, 'inactivas' => $inactivos, 'todas' => $activos + $inactivos];

        $medicos = $busqueda
            ->when($vista === 'activas', fn ($consulta) => $consulta->where('medicos.estado', true))
            ->when($vista === 'inactivas', fn ($consulta) => $consulta->where('medicos.estado', false))
            ->orderBy('personas.nombre_completo')
            ->paginate(15)
            ->withQueryString();

        return view('medicos.index', [
            'medicos' => $medicos,
            'texto' => $texto,
            'vista' => $vista,
            'pestanas' => $this->pestanas('medicos.index', $cuentas, $texto),
            'cuentas' => $cuentas,
        ]);
    }

    public function create(): View
    {
        return view('medicos.create', ['opciones' => $this->opcionesDeEspecialidad([])]);
    }

    public function store(MedicoRequest $request, MedicoService $medicos): RedirectResponse
    {
        [, $reutilizada] = $medicos->registrar($request->validated(), $request->user());

        return redirect()->route('medicos.index')->with(
            'exito',
            $reutilizada
                ? 'Esa persona ya estaba registrada: se creó su ficha de médico. Sus datos personales no se modificaron; puede corregirlos desde «Editar».'
                : 'Médico registrado.'
        );
    }

    public function edit(Medico $medico): View
    {
        $medico->load('persona', 'especialidades');

        return view('medicos.edit', [
            'medico' => $medico,
            'opciones' => $this->opcionesDeEspecialidad($medico->especialidades->pluck('id')->all()),
        ]);
    }

    public function update(MedicoRequest $request, Medico $medico, MedicoService $medicos): RedirectResponse
    {
        $medicos->actualizar($medico, $request->validated(), $request->user());

        return redirect()->route('medicos.index')->with('exito', 'Datos del médico actualizados.');
    }

    /**
     * Desactiva o reactiva al médico; nunca se elimina (RN11). Vuelve a la pestaña del listado
     * desde la que se hizo el cambio, con la misma búsqueda.
     */
    public function estado(Request $request, Medico $medico, MedicoService $medicos): RedirectResponse
    {
        $activo = $request->boolean('activo');
        $medicos->cambiarEstado($medico, $activo, $request->user());

        $vista = $this->vista($request->input('vista'));
        $texto = trim((string) $request->input('q'));

        return redirect()
            ->route('medicos.index', $this->parametrosDelListado($vista, $texto))
            ->with('exito', $activo
                ? 'Médico reactivado. Ya aparece en la pestaña Activos.'
                : 'Médico desactivado: no recibirá citas nuevas y su historial se conserva. Puede verlo en la pestaña Inactivos.');
    }

    /** Busca por CI, nombre o apellido, registro profesional, especialidad o código (MED-000042). */
    private function filtrarPorTexto($consulta, string $texto)
    {
        $patron = addcslashes($texto, '%_\\');
        $idPorCodigo = preg_match('/^med-/i', $texto) ? Medico::idDesdeCodigo($texto) : null;

        return $consulta->where(function ($coincidencia) use ($patron, $idPorCodigo) {
            $coincidencia
                ->where('personas.ci', 'ilike', $patron.'%')
                ->orWhere('personas.nombre_completo', 'ilike', '%'.$patron.'%')
                ->orWhere('medicos.nro_registro_profesional', 'ilike', $patron.'%')
                ->orWhereHas('especialidades', fn ($especialidad) => $especialidad->where('nombre', 'ilike', '%'.$patron.'%'));

            if ($idPorCodigo !== null) {
                $coincidencia->orWhere('medicos.id', $idPorCodigo);
            }
        });
    }

    /**
     * Especialidades que se pueden elegir: las activas y las que el médico ya tiene aunque
     * después se hayan desactivado, para no quitárselas sin querer.
     *
     * @param  array<int, int>  $asignadas
     */
    private function opcionesDeEspecialidad(array $asignadas)
    {
        return Especialidad::query()
            ->where('estado', true)
            ->orWhereIn('id', $asignadas)
            ->orderBy('nombre')
            ->get();
    }
}
