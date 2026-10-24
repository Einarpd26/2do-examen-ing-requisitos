<?php

namespace App\Http\Controllers;

use App\Http\Requests\PacienteRequest;
use App\Models\Paciente;
use App\Services\PacienteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-04: gestionar pacientes. Las rutas exigen el permiso pacientes:gestionar.
 */
class PacienteController extends Controller
{
    public function index(Request $request): View
    {
        $texto = trim((string) $request->query('q'));

        $pacientes = Paciente::query()
            ->with('persona.usuario.rol')
            ->when($texto !== '', function ($consulta) use ($texto) {
                // Busca por CI, por nombre o apellido, o por código (PAC-000042).
                $patron = addcslashes($texto, '%_\\');
                $idPorCodigo = preg_match('/^pac-/i', $texto) ? Paciente::idDesdeCodigo($texto) : null;

                $consulta->where(function ($coincidencia) use ($patron, $idPorCodigo) {
                    $coincidencia->whereHas('persona', function ($persona) use ($patron) {
                        $persona->where('ci', 'ilike', $patron.'%')
                            ->orWhere('nombre_completo', 'ilike', '%'.$patron.'%');
                    });

                    if ($idPorCodigo !== null) {
                        $coincidencia->orWhere('pacientes.id', $idPorCodigo);
                    }
                });
            })
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('pacientes.index', ['pacientes' => $pacientes, 'texto' => $texto]);
    }

    public function create(): View
    {
        return view('pacientes.create');
    }

    public function store(PacienteRequest $request, PacienteService $pacientes): RedirectResponse
    {
        [$paciente, $reutilizada] = $pacientes->registrar($request->validated(), $request->user());

        return redirect()->route('pacientes.show', $paciente)->with(
            'exito',
            $reutilizada
                ? 'Esa persona ya estaba registrada: se creó su ficha de paciente. Sus datos personales no se modificaron; puede corregirlos desde «Editar».'
                : 'Paciente registrado.'
        );
    }

    public function show(Paciente $paciente): View
    {
        $paciente->load('persona.usuario.rol');

        return view('pacientes.show', ['paciente' => $paciente]);
    }

    public function edit(Paciente $paciente): View
    {
        $paciente->load('persona');

        return view('pacientes.edit', ['paciente' => $paciente]);
    }

    public function update(PacienteRequest $request, Paciente $paciente, PacienteService $pacientes): RedirectResponse
    {
        $pacientes->actualizar($paciente, $request->validated(), $request->user());

        return redirect()->route('pacientes.show', $paciente)->with('exito', 'Datos del paciente actualizados.');
    }

    /** Desactiva o reactiva al paciente; nunca se elimina (RN11). */
    public function estado(Request $request, Paciente $paciente, PacienteService $pacientes): RedirectResponse
    {
        $activo = $request->boolean('activo');
        $pacientes->cambiarEstado($paciente, $activo, $request->user());

        return redirect()->route('pacientes.show', $paciente)->with(
            'exito',
            $activo ? 'Paciente reactivado.' : 'Paciente desactivado. Su historial se conserva.'
        );
    }
}
