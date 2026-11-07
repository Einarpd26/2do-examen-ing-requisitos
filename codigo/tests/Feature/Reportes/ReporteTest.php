<?php

namespace Tests\Feature\Reportes;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Sucursal;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-15: reportes administrativos (RF13, RN12). El «ahora» es el lunes 09/11/2026: el período por
 * defecto va del 01/11/2026 al 09/11/2026.
 */
class ReporteTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private Especialidad $pediatria;

    private Especialidad $cardiologia;

    private Medico $marta;

    private Medico $jorge;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:10:00', 'America/La_Paz'));

        $this->pediatria = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 30])->refresh();
        $this->cardiologia = Especialidad::create(['nombre' => 'Cardiología', 'duracion_min' => 30])->refresh();
        $this->marta = $this->crearFichaMedico(['primer_nombre' => 'Marta', 'apellido_paterno' => 'Rojas'], [], [$this->pediatria->id]);
        $this->jorge = $this->crearFichaMedico(['primer_nombre' => 'Jorge', 'apellido_paterno' => 'Vaca'], [], [$this->cardiologia->id]);
    }

    protected function tearDown(): void
    {
        Cita::flushEventListeners();

        parent::tearDown();
    }

    private function administrador(): Usuario
    {
        return $this->crearCuenta('ADMINISTRADOR');
    }

    private function cita(Paciente $paciente, string $fecha, string $inicio, string $estado, Medico $medico, Especialidad $especialidad): Cita
    {
        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'especialidad_id' => $especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => CarbonImmutable::parse($inicio)->addMinutes(30)->format('H:i'),
            'estado' => $estado,
        ])->refresh();
    }

    /** Cuatro citas: dos de Marta (una atendida, una cancelada), una de Jorge (inasistencia) y una fuera de período. */
    private function datos(): array
    {
        $paciente = $this->crearPaciente();

        return [
            'atendida' => $this->cita($paciente, '2026-11-03', '08:00', 'ATENDIDA', $this->marta, $this->pediatria),
            'cancelada' => $this->cita($this->crearPaciente(), '2026-11-04', '09:00', 'CANCELADA', $this->marta, $this->pediatria),
            'inasistencia' => $this->cita($this->crearPaciente(), '2026-11-05', '10:00', 'INASISTENCIA', $this->jorge, $this->cardiologia),
            'fuera' => $this->cita($this->crearPaciente(), '2026-10-05', '10:00', 'ATENDIDA', $this->marta, $this->pediatria),
        ];
    }

    // --- Citas: listado, filtros y totales ------------------------------------------------

    public function test_el_periodo_por_defecto_es_el_mes_en_curso_con_sus_totales(): void
    {
        $citas = $this->datos();

        $this->actingAs($this->administrador())->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Del 01/11/2026 al 09/11/2026')
            ->assertSee('Total de citas: 3')
            ->assertSee('Atendida: 1')
            ->assertSee('Cancelada: 1')
            ->assertSee('Inasistencia: 1')
            ->assertSee('Programada: 0')
            ->assertSee($citas['atendida']->codigo)
            ->assertSee($citas['cancelada']->codigo)
            ->assertSee($citas['inasistencia']->codigo)
            ->assertDontSee($citas['fuera']->codigo);
    }

    public function test_filtra_por_rango_de_fechas(): void
    {
        $citas = $this->datos();

        $this->actingAs($this->administrador())->get(route('reportes.index', ['desde' => '2026-10-01', 'hasta' => '2026-10-31']))
            ->assertSee('Total de citas: 1')
            ->assertSee($citas['fuera']->codigo)
            ->assertDontSee($citas['atendida']->codigo);
    }

    public function test_citas_de_una_sola_fecha_incluyen_los_dos_extremos(): void
    {
        $citas = $this->datos();

        $this->actingAs($this->administrador())->get(route('reportes.index', ['desde' => '2026-11-04', 'hasta' => '2026-11-04']))
            ->assertSee('Total de citas: 1')
            ->assertSee($citas['cancelada']->codigo);
    }

    public function test_filtra_por_medico_especialidad_y_estado(): void
    {
        $citas = $this->datos();
        $admin = $this->administrador();

        $this->actingAs($admin)->get(route('reportes.index', ['medico' => $this->jorge->id]))
            ->assertSee('Total de citas: 1')->assertSee($citas['inasistencia']->codigo)->assertDontSee($citas['atendida']->codigo);

        $this->actingAs($admin)->get(route('reportes.index', ['especialidad' => $this->pediatria->id]))
            ->assertSee('Total de citas: 2')->assertDontSee($citas['inasistencia']->codigo);

        $this->actingAs($admin)->get(route('reportes.index', ['estado' => 'CANCELADA']))
            ->assertSee('Total de citas: 1')->assertSee($citas['cancelada']->codigo)->assertDontSee($citas['atendida']->codigo);
    }

    public function test_muestra_los_totales_por_medico_y_por_especialidad(): void
    {
        $this->datos();

        $this->actingAs($this->administrador())->get(route('reportes.index'))
            ->assertSeeInOrder(['Por médico', 'Marta', '2', 'Jorge', '1'])
            ->assertSeeInOrder(['Por especialidad', 'Pediatría', '2', 'Cardiología', '1']);
    }

    public function test_agenda_diaria_de_un_medico_es_un_reporte_de_un_dia_y_un_medico(): void
    {
        $citas = $this->datos();

        $this->actingAs($this->administrador())
            ->get(route('reportes.index', ['desde' => '2026-11-03', 'hasta' => '2026-11-03', 'medico' => $this->marta->id]))
            ->assertSee('Total de citas: 1')
            ->assertSee($citas['atendida']->codigo)
            ->assertSee('08:00–08:30');
    }

    public function test_sin_resultados_muestra_un_aviso(): void
    {
        $this->actingAs($this->administrador())->get(route('reportes.index'))
            ->assertOk()
            ->assertSee('Total de citas: 0')
            ->assertSee('No hay citas con esos filtros');
    }

    public function test_pagina_el_detalle_de_25_en_25(): void
    {
        $paciente = $this->crearPaciente();

        foreach (range(0, 26) as $n) {
            $this->cita($paciente, '2026-11-03', CarbonImmutable::parse('00:00')->addMinutes($n * 30)->format('H:i'), 'ATENDIDA', $this->marta, $this->pediatria);
        }

        $this->actingAs($this->administrador())->get(route('reportes.index'))
            ->assertSee('Total de citas: 27')
            ->assertSee('page=2', false);
    }

    // --- Parámetros inválidos --------------------------------------------------------------

    public function test_fechas_invalidas_o_al_reves_usan_el_periodo_por_defecto_con_aviso(): void
    {
        $this->datos();
        $admin = $this->administrador();

        $this->actingAs($admin)->get(route('reportes.index', ['desde' => '2026-13-45']))
            ->assertOk()->assertSee('no es válida')->assertSee('Del 01/11/2026 al 09/11/2026');

        $this->actingAs($admin)->get(route('reportes.index', ['desde' => '2026-11-09', 'hasta' => '2026-11-01']))
            ->assertOk()->assertSee('no puede ser posterior')->assertSee('Del 01/11/2026 al 09/11/2026');
    }

    public function test_solo_una_fecha_se_completa_con_la_otra_sin_error(): void
    {
        $this->actingAs($this->administrador())->get(route('reportes.index', ['desde' => '2026-12-01']))
            ->assertOk()->assertSee('Del 01/12/2026 al 01/12/2026');
    }

    public function test_los_parametros_manipulados_se_ignoran_sin_error(): void
    {
        $admin = $this->administrador();

        $this->actingAs($admin)->get(route('reportes.index').'?medico[]=1&especialidad=abc&estado=INVENTADO&tipo[]=x&desde[]=1')
            ->assertOk();
        $this->actingAs($admin)->get(route('reportes.index', ['medico' => '99999999999999', 'especialidad' => '-5']))
            ->assertOk()->assertSee('Total de citas: 0');
    }

    // --- Pacientes registrados ---------------------------------------------------------------

    public function test_pacientes_registrados_en_el_periodo_con_totales(): void
    {
        $dentro = $this->crearPaciente(['primer_nombre' => 'Lucía', 'apellido_paterno' => 'Vaca'], ['verificado' => false]);
        $verificado = $this->crearPaciente(['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Soria']);
        $viejo = $this->crearPaciente(['primer_nombre' => 'Antiguo', 'apellido_paterno' => 'Registro']);
        $viejo->forceFill(['created_at' => '2026-09-01 10:00:00'])->save();

        $this->actingAs($this->administrador())->get(route('reportes.index', ['tipo' => 'pacientes']))
            ->assertOk()
            ->assertSee('Registrados: 2')
            ->assertSee('Verificados: 1')
            ->assertSee('Activos: 2')
            ->assertSee('Lucía')
            ->assertSee('Pedro')
            ->assertDontSee('Antiguo')
            ->assertSee($dentro->codigo)
            ->assertSee($verificado->codigo);
    }

    public function test_pacientes_sin_registros_muestra_un_aviso(): void
    {
        $this->actingAs($this->administrador())->get(route('reportes.index', ['tipo' => 'pacientes', 'desde' => '2020-01-01', 'hasta' => '2020-01-31']))
            ->assertSee('Registrados: 0')->assertSee('No se registraron pacientes');
    }

    // --- Permisos --------------------------------------------------------------------------

    public function test_solo_el_administrador_consulta_los_reportes(): void
    {
        foreach (['RECEPCIONISTA', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol))->get(route('reportes.index'))->assertForbidden();
            $this->actingAs($this->crearCuenta($rol))->get(route('reportes.index', ['tipo' => 'pacientes']))->assertForbidden();
        }
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get(route('reportes.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_ofrece_reportes_solo_al_administrador(): void
    {
        $this->actingAs($this->administrador())->get(route('inicio'))->assertSee('Reportes');
        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('inicio'))->assertDontSee('Reportes');
    }
}
