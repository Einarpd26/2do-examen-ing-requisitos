<?php

namespace App\Http\Controllers;

use App\Exceptions\ReglaDeNegocioException;
use App\Http\Requests\CancelarCitaRequest;
use App\Http\Requests\CitaRequest;
use App\Http\Requests\ReprogramarCitaRequest;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Services\CitaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-09: registrar cita, CU-10: reprogramarla y CU-11: cancelarla. Lo usan dos roles con las mismas reglas:
 * - la Recepcionista (citas:gestionar), en /citas, elige al paciente;
 * - el Paciente (citas:gestionar_propias), en /mis-citas, siempre agenda para sí mismo.
 * El formulario avanza por pasos con parámetros GET (paciente, especialidad, médico y fecha)
 * y funciona sin JavaScript.
 */
class CitaController extends Controller
{
    /** Formulario por pasos: cada paso muestra lo que ya se puede elegir con lo anterior. */
    public function nueva(Request $request, CitaService $citas): View
    {
        $propia = $this->esPropia($request);
        $textoBusqueda = $this->textoDeLaConsulta($request, 'q');

        $paciente = $propia ? $this->pacienteDeLaCuenta($request) : $this->pacienteElegido($request->query('paciente'));
        $candidatos = ! $propia && $paciente === null && $textoBusqueda !== '' ? $this->buscarPacientes($textoBusqueda) : collect();

        $especialidades = Especialidad::query()->where('estado', true)->orderBy('nombre')->get();
        $especialidad = $especialidades->firstWhere('id', (int) $request->query('especialidad'));

        $medicos = $especialidad === null ? collect() : Medico::query()
            ->join('personas', 'personas.id', '=', 'medicos.persona_id')
            ->select('medicos.*')
            ->where('medicos.estado', true)
            ->whereHas('especialidades', fn ($consulta) => $consulta->whereKey($especialidad->id))
            ->with('persona')
            ->orderBy('personas.nombre_completo')
            ->get();
        $medico = $medicos->firstWhere('id', (int) $request->query('medico'));

        $fecha = $this->textoDeLaConsulta($request, 'fecha');
        $horarios = [];
        $aviso = null;

        if ($paciente !== null && $especialidad !== null && $medico !== null && $fecha !== '') {
            try {
                $horarios = $citas->horariosDisponibles($paciente, $medico, $especialidad, $fecha);
            } catch (ReglaDeNegocioException $e) {
                $aviso = $e->getMessage();
            }
        }

        return view('citas.nueva', [
            'propia' => $propia,
            'rutaNueva' => $propia ? 'mis-citas.nueva' : 'citas.nueva',
            'rutaGuardar' => $propia ? 'mis-citas.store' : 'citas.store',
            'paciente' => $paciente,
            'textoBusqueda' => $textoBusqueda,
            'candidatos' => $candidatos,
            'especialidades' => $especialidades,
            'especialidad' => $especialidad,
            'medicos' => $medicos,
            'medico' => $medico,
            'fecha' => $fecha,
            'primeraFecha' => $citas->primeraFecha()->toDateString(),
            'ultimaFecha' => $citas->ultimaFecha()->toDateString(),
            'horarios' => $horarios,
            'aviso' => $aviso,
        ]);
    }

    public function store(CitaRequest $request, CitaService $citas): RedirectResponse
    {
        $propia = $this->esPropia($request);
        $datos = $request->validated();

        // El paciente de una cuenta de paciente es siempre el de la sesión, nunca el que diga el formulario.
        $paciente = $propia ? $this->pacienteDeLaCuenta($request) : Paciente::findOrFail($datos['paciente_id']);

        $cita = $citas->registrar(
            $paciente,
            Medico::findOrFail($datos['medico_id']),
            Especialidad::findOrFail($datos['especialidad_id']),
            $datos,
            $request->user()
        );

        return redirect()->route($propia ? 'mis-citas.comprobante' : 'citas.comprobante', $cita)
            ->with('exito', "Cita registrada con el código {$cita->codigo}.");
    }

    /** Comprobante de la cita: la Recepcionista ve cualquiera; el Paciente, solo las suyas. */
    public function comprobante(Request $request, Cita $cita): View
    {
        if ($this->esPropia($request)) {
            abort_unless($this->pacienteDeLaCuenta($request)->id === $cita->paciente_id, 404);
        }

        $cita->load('paciente.persona', 'medico.persona', 'especialidad', 'sucursal');

        return view('citas.comprobante', ['cita' => $cita, 'propia' => $this->esPropia($request)]);
    }

    /** CU-10: pantalla para elegir la nueva fecha y hora. Funciona con parámetros GET, sin JavaScript. */
    public function reprogramar(Request $request, Cita $cita, CitaService $citas): View
    {
        $propia = $this->esPropia($request);
        $this->exigirCitaVisible($request, $cita);

        // Si no se puede reprogramar, el mensaje vuelve a la pantalla anterior (ReglaDeNegocioException).
        $citas->exigirReprogramable($cita, $propia);

        $cita->load('paciente.persona', 'medico.persona', 'especialidad', 'sucursal');
        $fecha = $this->textoDeLaConsulta($request, 'fecha');
        $horarios = [];
        $aviso = null;

        if ($fecha !== '') {
            try {
                $horarios = $citas->horariosDisponibles($cita->paciente, $cita->medico, $cita->especialidad, $fecha, $cita);
            } catch (ReglaDeNegocioException $e) {
                $aviso = $e->getMessage();
            }
        }

        return view('citas.reprogramar', [
            'cita' => $cita,
            'propia' => $propia,
            'fecha' => $fecha,
            'primeraFecha' => $citas->primeraFecha()->toDateString(),
            'ultimaFecha' => $citas->ultimaFecha()->toDateString(),
            'horarios' => $horarios,
            'aviso' => $aviso,
        ]);
    }

    /** CU-10: guarda la nueva fecha y hora. El Paciente solo las suyas y con 24 horas (RN15). */
    public function actualizar(ReprogramarCitaRequest $request, Cita $cita, CitaService $citas): RedirectResponse
    {
        $propia = $this->esPropia($request);
        $this->exigirCitaVisible($request, $cita);

        $citas->reprogramar($cita, $request->validated(), $request->user(), $propia);

        return redirect()->route($propia ? 'mis-citas.comprobante' : 'citas.comprobante', $cita)
            ->with('exito', "La cita {$cita->codigo} fue reprogramada.");
    }

    /** El Paciente solo ve y modifica sus citas; la de otro responde 404. */
    private function exigirCitaVisible(Request $request, Cita $cita): void
    {
        if ($this->esPropia($request)) {
            abort_unless($this->pacienteDeLaCuenta($request)->id === $cita->paciente_id, 404);
        }
    }

    /** CU-11: cancela la cita. El Paciente solo las suyas y con 24 horas de anticipación (RN15). */
    public function cancelar(CancelarCitaRequest $request, Cita $cita, CitaService $citas): RedirectResponse
    {
        $propia = $this->esPropia($request);

        if ($propia) {
            abort_unless($this->pacienteDeLaCuenta($request)->id === $cita->paciente_id, 404);
        }

        $citas->cancelar($cita, $request->validated('motivo_cancelacion'), $request->user(), $propia);

        return redirect()->route($propia ? 'mis-citas.comprobante' : 'citas.comprobante', $cita)
            ->with('exito', "La cita {$cita->codigo} fue cancelada.");
    }

    /** Un parámetro de la URL como texto; si llega manipulado como arreglo (?fecha[]=x), se ignora. */
    private function textoDeLaConsulta(Request $request, string $parametro): string
    {
        $valor = $request->query($parametro);

        return is_string($valor) ? trim($valor) : '';
    }

    private function esPropia(Request $request): bool
    {
        return $request->routeIs('mis-citas.*');
    }

    /** La ficha del paciente que inició sesión; una cuenta sin ficha no puede agendar (404, como /perfil). */
    private function pacienteDeLaCuenta(Request $request): Paciente
    {
        $paciente = $request->user()->persona->paciente;
        abort_if($paciente === null, 404);

        return $paciente->loadMissing('persona');
    }

    /** El paciente activo elegido por la Recepcionista, o null si no se eligió o no existe. */
    private function pacienteElegido(mixed $id): ?Paciente
    {
        if (! is_numeric($id)) {
            return null;
        }

        return Paciente::query()->with('persona')->where('estado', true)->find((int) $id);
    }

    /** Pacientes activos por CI, nombre, apellido o código (PAC-000042). Máximo 10. */
    private function buscarPacientes(string $texto)
    {
        $patron = addcslashes($texto, '%_\\');
        $idPorCodigo = preg_match('/^pac-/i', $texto) ? Paciente::idDesdeCodigo($texto) : null;

        return Paciente::query()
            ->with('persona')
            ->where('estado', true)
            ->where(function ($coincidencia) use ($patron, $idPorCodigo) {
                $coincidencia->whereHas('persona', function ($persona) use ($patron) {
                    $persona->where('ci', 'ilike', $patron.'%')
                        ->orWhere('nombre_completo', 'ilike', '%'.$patron.'%');
                });

                if ($idPorCodigo !== null) {
                    $coincidencia->orWhere('pacientes.id', $idPorCodigo);
                }
            })
            ->orderByDesc('id')
            ->limit(10)
            ->get();
    }
}
