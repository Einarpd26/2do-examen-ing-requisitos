<?php

namespace App\Http\Controllers;

use App\Models\Especialidad;
use App\Models\Medico;
use App\Services\HistorialService;
use App\Services\ReporteService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-15: reportes administrativos (RF13). Exige reportes:consultar, que solo concede el
 * Administrador (RN12). Cada reporte se pide con parámetros GET y no modifica datos.
 */
class ReporteController extends Controller
{
    public function index(Request $request, ReporteService $reportes): View
    {
        $tipo = $request->query('tipo') === 'pacientes' ? 'pacientes' : 'citas';
        $estados = HistorialService::ESTADOS;

        [$desde, $hasta] = $reportes->rangoPorDefecto();
        $aviso = null;

        $pedidoDesde = $reportes->fecha($request->query('desde'));
        $pedidoHasta = $reportes->fecha($request->query('hasta'));

        if ($this->hayTexto($request, 'desde') && $pedidoDesde === null || $this->hayTexto($request, 'hasta') && $pedidoHasta === null) {
            $aviso = 'Una de las fechas no es válida; se usa el período por defecto.';
        } elseif ($pedidoDesde !== null && $pedidoHasta !== null && $pedidoDesde->gt($pedidoHasta)) {
            $aviso = 'La fecha inicial no puede ser posterior a la final; se usa el período por defecto.';
        } else {
            $desde = $pedidoDesde ?? $desde;
            $hasta = $pedidoHasta ?? $hasta;

            // Si solo se indicó un extremo y deja el rango al revés, el otro extremo lo acompaña.
            if ($desde->gt($hasta)) {
                $pedidoDesde !== null ? $hasta = $desde : $desde = $hasta;
            }
        }

        $datos = ['tipo' => $tipo, 'desde' => $desde, 'hasta' => $hasta, 'aviso' => $aviso, 'estados' => $estados];

        if ($tipo === 'pacientes') {
            return view('reportes.index', $datos + [
                'pacientes' => $reportes->pacientes($desde, $hasta),
                'totales' => $reportes->totalesPacientes($desde, $hasta),
            ]);
        }

        $medico = $this->entero($request->query('medico'));
        $especialidad = $this->entero($request->query('especialidad'));
        $estado = $request->query('estado');
        $estado = is_string($estado) && array_key_exists($estado, $estados) ? $estado : null;

        $porEstado = $reportes->porEstado($desde, $hasta, $medico, $especialidad, $estado);

        return view('reportes.index', $datos + [
            'medicos' => Medico::query()->join('personas', 'personas.id', '=', 'medicos.persona_id')
                ->select('medicos.*')->with('persona')->orderBy('personas.nombre_completo')->get(),
            'especialidades' => Especialidad::query()->orderBy('nombre')->get(),
            'medico' => $medico,
            'especialidad' => $especialidad,
            'estado' => $estado,
            'citas' => $reportes->listado($desde, $hasta, $medico, $especialidad, $estado),
            'porEstado' => $porEstado,
            'totalCitas' => array_sum($porEstado),
            'porMedico' => $reportes->agrupadas('medico', $desde, $hasta, $medico, $especialidad, $estado),
            'porEspecialidad' => $reportes->agrupadas('especialidad', $desde, $hasta, $medico, $especialidad, $estado),
        ]);
    }

    private function hayTexto(Request $request, string $parametro): bool
    {
        $valor = $request->query($parametro);

        return $valor !== null && $valor !== '';
    }

    /** Un identificador de la URL como entero positivo; texto, arreglos o fuera de rango se ignoran. */
    private function entero(mixed $valor): ?int
    {
        return is_string($valor) && ctype_digit($valor) && strlen($valor) < 10 && (int) $valor > 0 ? (int) $valor : null;
    }
}
