<?php

namespace Tests\Feature\Citas;

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
 * CU-14: historial de citas de un paciente (RF12, RN10, RN11, RN12). Prueba 7 del enunciado.
 */
class HistorialTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private Especialidad $especialidad;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:10:00', 'America/La_Paz'));

        $this->especialidad = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 30])->refresh();
        $this->medico = $this->crearFichaMedico(['primer_nombre' => 'Marta', 'apellido_paterno' => 'Rojas'], [], [$this->especialidad->id]);
    }

    protected function tearDown(): void
    {
        Cita::flushEventListeners();

        parent::tearDown();
    }

    private function recepcionista(): Usuario
    {
        return $this->crearCuenta('RECEPCIONISTA');
    }

    /** @return array{0: Usuario, 1: Paciente} */
    private function cuentaPaciente(): array
    {
        $cuenta = $this->crearCuenta('PACIENTE');

        return [$cuenta, Paciente::create(['persona_id' => $cuenta->persona_id, 'verificado' => true])->refresh()];
    }

    private function cita(Paciente $paciente, string $fecha, string $inicio = '08:00', string $estado = 'PROGRAMADA', ?string $motivoCancelacion = null): Cita
    {
        $fin = CarbonImmutable::parse($inicio)->addMinutes(30)->format('H:i');

        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $this->medico->id,
            'especialidad_id' => $this->especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $estado,
            'motivo_cancelacion' => $motivoCancelacion,
        ])->refresh();
    }

    // --- Prueba 7 del enunciado ---------------------------------------------------------

    public function test_la_recepcionista_ve_las_citas_registradas_del_paciente(): void
    {
        $paciente = $this->crearPaciente(['primer_nombre' => 'Lucía', 'apellido_paterno' => 'Vaca']);
        $pasada = $this->cita($paciente, '2026-10-01', '09:00', 'ATENDIDA');
        $futura = $this->cita($paciente, '2026-11-20', '10:00');
        $cancelada = $this->cita($paciente, '2026-11-25', '11:00', 'CANCELADA', 'Viaje imprevisto');

        $respuesta = $this->actingAs($this->recepcionista())
            ->get(route('pacientes.historial', $paciente))
            ->assertOk()
            ->assertSee('Lucía')
            // Fecha y hora, médico, especialidad, estado y fecha de registro (RF12).
            ->assertSee('01/10/2026')
            ->assertSee('09:00–09:30')
            ->assertSee('Marta')
            ->assertSee('Pediatría')
            ->assertSee('Atendida')
            ->assertSee('Cancelada')
            ->assertSee('Viaje imprevisto')
            ->assertSee('09/11/2026 09:10')
            ->assertSee($pasada->codigo)
            ->assertSee($futura->codigo)
            ->assertSee($cancelada->codigo);

        // De la más reciente a la más antigua.
        $respuesta->assertSeeInOrder([$cancelada->codigo, $futura->codigo, $pasada->codigo]);
    }

    public function test_solo_aparecen_las_citas_de_ese_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $otro = $this->crearPaciente();
        $mia = $this->cita($paciente, '2026-11-20', '08:00');
        $ajena = $this->cita($otro, '2026-11-20', '09:00');

        $this->actingAs($this->recepcionista())->get(route('pacientes.historial', $paciente))
            ->assertSee($mia->codigo)
            ->assertDontSee($ajena->codigo);
    }

    public function test_un_paciente_sin_citas_muestra_un_aviso(): void
    {
        $this->actingAs($this->recepcionista())->get(route('pacientes.historial', $this->crearPaciente()))
            ->assertOk()
            ->assertSee('todavía no tiene citas registradas');
    }

    public function test_la_cita_cancelada_sigue_en_el_historial(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente, '2026-11-20');
        $recepcion = $this->recepcionista();

        $this->actingAs($recepcion)->from('/volver')
            ->patch(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'Cambio de agenda']);

        $this->actingAs($recepcion)->get(route('pacientes.historial', $paciente))
            ->assertSee($cita->codigo)
            ->assertSee('Cancelada')
            ->assertSee('Cambio de agenda');
    }

    public function test_el_historial_de_un_paciente_desactivado_se_conserva(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente, '2026-11-20');
        $paciente->update(['estado' => false]);

        $this->actingAs($this->recepcionista())->get(route('pacientes.historial', $paciente))
            ->assertOk()
            ->assertSee($cita->codigo)
            ->assertSee('desactivado');
    }

    // --- Filtro por estado ---------------------------------------------------------------

    public function test_filtra_por_estado(): void
    {
        $paciente = $this->crearPaciente();
        $programada = $this->cita($paciente, '2026-11-20', '08:00');
        $atendida = $this->cita($paciente, '2026-10-01', '08:00', 'ATENDIDA');

        $this->actingAs($this->recepcionista())->get(route('pacientes.historial', ['paciente' => $paciente, 'estado' => 'ATENDIDA']))
            ->assertSee($atendida->codigo)
            ->assertDontSee($programada->codigo);
    }

    public function test_un_estado_desconocido_o_manipulado_se_ignora(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente, '2026-11-20');
        $recepcion = $this->recepcionista();

        $this->actingAs($recepcion)->get(route('pacientes.historial', ['paciente' => $paciente, 'estado' => 'INVENTADO']))
            ->assertOk()->assertSee($cita->codigo);

        $this->actingAs($recepcion)->get(route('pacientes.historial', $paciente).'?estado[]=ATENDIDA')
            ->assertOk()->assertSee($cita->codigo);
    }

    public function test_pagina_el_historial_de_15_en_15(): void
    {
        $paciente = $this->crearPaciente();

        foreach (range(1, 17) as $n) {
            $this->cita($paciente, CarbonImmutable::parse('2026-12-01')->addDays($n)->toDateString());
        }

        $recepcion = $this->recepcionista();
        $this->actingAs($recepcion)->get(route('pacientes.historial', $paciente))
            ->assertOk()->assertSee('page=2', false);
        $this->actingAs($recepcion)->get(route('pacientes.historial', ['paciente' => $paciente, 'page' => 2]))
            ->assertOk();
    }

    // --- El Paciente ve solo lo suyo -----------------------------------------------------

    public function test_el_paciente_ve_su_propio_historial_y_no_el_de_otros(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $suya = $this->cita($ficha, '2026-11-20', '08:00');
        $ajena = $this->cita($this->crearPaciente(), '2026-11-20', '09:00');

        $this->actingAs($cuenta)->get(route('historial.propio'))
            ->assertOk()
            ->assertSee($suya->codigo)
            ->assertDontSee($ajena->codigo)
            ->assertSee(route('mis-citas.comprobante', $suya), false);
    }

    public function test_el_paciente_no_entra_al_historial_de_otro_paciente(): void
    {
        [$cuenta] = $this->cuentaPaciente();

        $this->actingAs($cuenta)->get(route('pacientes.historial', $this->crearPaciente()))->assertForbidden();
    }

    public function test_una_cuenta_sin_ficha_de_paciente_recibe_404_en_mi_historial(): void
    {
        $this->actingAs($this->recepcionista())->get(route('historial.propio'))->assertNotFound();
    }

    public function test_el_medico_y_el_administrador_no_consultan_el_historial(): void
    {
        $paciente = $this->crearPaciente();

        foreach (['MEDICO', 'ADMINISTRADOR'] as $rol) {
            $cuenta = $this->crearCuenta($rol);
            $this->actingAs($cuenta)->get(route('pacientes.historial', $paciente))->assertForbidden();
            $this->actingAs($cuenta)->get(route('historial.propio'))->assertForbidden();
        }
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get(route('pacientes.historial', $this->crearPaciente()))->assertRedirect(route('login'));
        $this->get(route('historial.propio'))->assertRedirect(route('login'));
    }

    public function test_un_paciente_inexistente_responde_404(): void
    {
        $this->actingAs($this->recepcionista())->get('/pacientes/999999/historial')->assertNotFound();
    }

    // --- Menú y ficha ---------------------------------------------------------------------

    public function test_el_menu_ofrece_mi_historial_solo_al_paciente(): void
    {
        [$cuenta] = $this->cuentaPaciente();

        $this->actingAs($cuenta)->get(route('inicio'))->assertSee('Mi historial');
        $this->actingAs($this->recepcionista())->get(route('inicio'))->assertDontSee('Mi historial');
    }

    public function test_la_ficha_del_paciente_enlaza_a_su_historial(): void
    {
        $paciente = $this->crearPaciente();

        $this->actingAs($this->recepcionista())->get(route('pacientes.show', $paciente))
            ->assertSee(route('pacientes.historial', $paciente), false);
    }
}
