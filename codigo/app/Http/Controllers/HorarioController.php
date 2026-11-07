<?php

namespace App\Http\Controllers;

use App\Http\Requests\HorarioRequest;
use App\Models\Consultorio;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Services\HorarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * CU-07: gestionar horarios de atención. Las rutas exigen el permiso horarios:gestionar.
 * Se elige primero un médico activo y después se administran los bloques de su semana.
 */
class HorarioController extends Controller
{
    /** Médicos activos con el resumen de su semana, para elegir a quién administrar. */
    public function index(): View
    {
        $medicos = Medico::query()
            ->join('personas', 'personas.id', '=', 'medicos.persona_id')
            ->select('medicos.*')
            ->where('medicos.estado', true)
            ->with('persona', 'especialidades', 'horarios')
            ->orderBy('personas.nombre_completo')
            ->get();

        return view('horarios.index', ['medicos' => $medicos]);
    }

    /** La semana de un médico y el formulario para agregar un bloque. */
    public function medico(Medico $medico): View
    {
        $medico->load('persona', 'especialidades');

        return view('horarios.medico', [
            'medico' => $medico,
            'horarios' => $medico->horarios()->with('consultorio')->orderBy('dia_semana')->orderBy('hora_inicio')->get()->groupBy('dia_semana'),
            'consultorios' => $this->consultoriosParaElegir(),
        ]);
    }

    public function store(HorarioRequest $request, Medico $medico, HorarioService $horarios): RedirectResponse
    {
        $horario = $horarios->registrar($medico, $request->validated(), $request->user());

        return redirect()->route('horarios.medico', $medico)
            ->with('exito', "Horario registrado: {$horario->descripcion()}.");
    }

    public function edit(HorarioAtencion $horario): View
    {
        $horario->load('medico.persona');

        return view('horarios.edit', [
            'horario' => $horario,
            'consultorios' => $this->consultoriosParaElegir($horario->consultorio_id),
        ]);
    }

    public function update(HorarioRequest $request, HorarioAtencion $horario, HorarioService $horarios): RedirectResponse
    {
        $horarios->actualizar($horario, $request->validated(), $request->user());

        return redirect()->route('horarios.medico', $horario->medico_id)->with('exito', 'Horario actualizado.');
    }

    public function destroy(Request $request, HorarioAtencion $horario, HorarioService $horarios): RedirectResponse
    {
        $horarios->eliminar($horario, $request->user());

        return redirect()->route('horarios.medico', $horario->medico_id)
            ->with('exito', "Horario eliminado: {$horario->descripcion()}.");
    }

    /**
     * Consultorios activos para el selector. Al editar se incluye también el del bloque, por si
     * quedara inactivo, para que el formulario no lo pierda en silencio.
     *
     * @return Collection<int, Consultorio>
     */
    private function consultoriosParaElegir(?int $actual = null): Collection
    {
        return Consultorio::query()
            ->where('estado', true)
            ->when($actual, fn ($consulta) => $consulta->orWhere('id', $actual))
            ->orderBy('nombre')
            ->get();
    }
}
