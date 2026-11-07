<?php

namespace Tests\Feature\Recordatorios;

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
 * CU-18: recordatorio de próximas citas en el inicio del Paciente (RF15, RNF16). El «ahora» es el
 * lunes 09/11/2026 a las 09:10: «hoy» es el 09, «mañana» el 10 y el límite de 2 días el 11.
 */
class RecordatorioTest extends TestCase
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

    /** @return array{0: Usuario, 1: Paciente} */
    private function cuentaPaciente(bool $verificado = true): array
    {
        $cuenta = $this->crearCuenta('PACIENTE');

        return [$cuenta, Paciente::create(['persona_id' => $cuenta->persona_id, 'verificado' => $verificado])->refresh()];
    }

    private function cita(Paciente $paciente, string $fecha, string $inicio, string $estado = 'PROGRAMADA', ?string $motivo = null): Cita
    {
        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $this->medico->id,
            'especialidad_id' => $this->especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => CarbonImmutable::parse($inicio)->addMinutes(30)->format('H:i'),
            'estado' => $estado,
            'motivo' => $motivo,
        ])->refresh();
    }

    public function test_el_paciente_ve_sus_citas_de_hoy_y_de_los_proximos_dos_dias(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $hoy = $this->cita($ficha, '2026-11-09', '11:00');
        $manana = $this->cita($ficha, '2026-11-10', '08:30', 'CONFIRMADA');
        $limite = $this->cita($ficha, '2026-11-11', '10:00');

        $this->actingAs($cuenta)->get(route('inicio'))
            ->assertOk()
            ->assertSee('Sus próximas citas')
            ->assertSeeInOrder(['Hoy a las 11:00', 'Mañana a las 08:30', '11/11/2026 a las 10:00'])
            ->assertSee('Marta')
            ->assertSee(route('mis-citas.comprobante', $hoy), false)
            ->assertSee(route('mis-citas.comprobante', $manana), false)
            ->assertSee(route('mis-citas.comprobante', $limite), false);
    }

    public function test_no_recuerda_citas_lejanas_pasadas_ni_que_ya_comenzaron(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $this->cita($ficha, '2026-11-12', '09:00');
        $this->cita($ficha, '2026-11-08', '09:00');
        $this->cita($ficha, '2026-11-09', '08:00');

        $this->actingAs($cuenta)->get(route('inicio'))
            ->assertOk()
            ->assertDontSee('Sus próximas citas');
    }

    public function test_no_recuerda_citas_canceladas_atendidas_ni_con_inasistencia(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        foreach (['CANCELADA' => '10:00', 'ATENDIDA' => '10:30', 'INASISTENCIA' => '11:00'] as $estado => $hora) {
            $this->cita($ficha, '2026-11-10', $hora, $estado);
        }

        $this->actingAs($cuenta)->get(route('inicio'))->assertDontSee('Sus próximas citas');
    }

    public function test_solo_muestra_fecha_hora_y_medico_nunca_el_motivo(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $this->cita($ficha, '2026-11-10', '09:00', 'PROGRAMADA', 'Control de diabetes');

        $this->actingAs($cuenta)->get(route('inicio'))
            ->assertSee('Mañana a las 09:00')
            ->assertDontSee('Control de diabetes')
            ->assertDontSee('diabetes');
    }

    public function test_no_muestra_las_citas_de_otros_pacientes(): void
    {
        [$cuenta] = $this->cuentaPaciente();
        $otro = $this->crearPaciente();
        $ajena = $this->cita($otro, '2026-11-10', '09:00');

        $this->actingAs($cuenta)->get(route('inicio'))
            ->assertDontSee('Sus próximas citas')
            ->assertDontSee($ajena->codigo);
    }

    public function test_el_paciente_con_la_ficha_pendiente_tambien_recibe_el_aviso(): void
    {
        // Con la ficha pendiente puede reservar (sección 4.1), así que también se le recuerdan sus citas.
        [$cuenta, $ficha] = $this->cuentaPaciente(false);
        $this->cita($ficha, '2026-11-10', '09:00');

        $this->actingAs($cuenta)->get(route('inicio'))->assertSee('Sus próximas citas');
    }

    public function test_el_resto_de_los_roles_no_ve_el_aviso(): void
    {
        $paciente = $this->crearPaciente();
        $this->cita($paciente, '2026-11-10', '09:00');

        foreach (['RECEPCIONISTA', 'MEDICO', 'ADMINISTRADOR'] as $rol) {
            $this->actingAs($this->crearCuenta($rol))->get(route('inicio'))
                ->assertOk()->assertDontSee('Sus próximas citas');
        }
    }

    public function test_un_paciente_sin_citas_ve_el_inicio_sin_aviso(): void
    {
        [$cuenta] = $this->cuentaPaciente();

        $this->actingAs($cuenta)->get(route('inicio'))->assertOk()->assertSee('Hola')->assertDontSee('Sus próximas citas');
    }

    public function test_una_cuenta_de_paciente_sin_ficha_no_falla(): void
    {
        $cuenta = $this->crearCuenta('PACIENTE');

        $this->actingAs($cuenta)->get(route('inicio'))->assertOk()->assertDontSee('Sus próximas citas');
    }

    public function test_el_aviso_aparece_al_iniciar_sesion(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $this->cita($ficha, '2026-11-10', '09:00');

        $this->followingRedirects()
            ->post(route('login.autenticar'), ['identificador' => $cuenta->persona->ci, 'contrasena' => self::CLAVE])
            ->assertSee('Sus próximas citas');
    }
}
