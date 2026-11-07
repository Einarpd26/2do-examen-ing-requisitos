<?php

namespace Tests\Feature\Citas;

use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Sucursal;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-12: control de asistencia (RF10, RN10, RN12). El «ahora» es un lunes a las 09:10 hora de
 * La Paz: la cita de las 08:00 ya comenzó y la de las 11:00 todavía no.
 */
class AsistenciaTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private const HOY = '2026-11-09';

    private const MANANA = '2026-11-10';

    private Especialidad $especialidad;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:10:00', 'America/La_Paz'));

        $this->especialidad = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 30])->refresh();
        $this->medico = $this->crearFichaMedico([], [], [$this->especialidad->id]);
        HorarioAtencion::create(['medico_id' => $this->medico->id, 'sucursal_id' => Sucursal::firstOrFail()->id, 'dia_semana' => 1, 'hora_inicio' => '08:00', 'hora_fin' => '12:00']);
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

    private function cita(Paciente $paciente, string $fecha = self::HOY, string $inicio = '08:00', string $estado = 'PROGRAMADA', ?Medico $medico = null): Cita
    {
        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => ($medico ?? $this->medico)->id,
            'especialidad_id' => $this->especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => CarbonImmutable::parse($inicio)->addMinutes(30)->format('H:i'),
            'estado' => $estado,
        ])->refresh();
    }

    private function accion(Usuario $actor, Cita $cita, string $accion)
    {
        return $this->actingAs($actor)->from('/volver')->patch(route('citas.asistencia', $cita), ['accion' => $accion]);
    }

    /** @return array{0: Usuario, 1: Paciente} */
    private function cuentaPaciente(): array
    {
        $cuenta = $this->crearCuenta('PACIENTE');

        return [$cuenta, Paciente::create(['persona_id' => $cuenta->persona_id, 'verificado' => true])->refresh()];
    }

    /** @return array{0: Usuario, 1: Medico} */
    private function cuentaMedico(): array
    {
        $cuenta = $this->crearCuenta('MEDICO');
        $ficha = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-PROPIO'])->refresh();
        $ficha->especialidades()->sync([$this->especialidad->id]);

        return [$cuenta, $ficha];
    }

    // --- Transiciones de la Recepcionista -------------------------------------------------

    public function test_la_recepcionista_confirma_una_cita_futura(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::MANANA, '09:00');

        $this->accion($this->recepcionista(), $cita, 'confirmar')
            ->assertRedirect(route('citas.comprobante', $cita))
            ->assertSessionHas('exito');

        $this->assertSame('CONFIRMADA', $cita->refresh()->estado);
        $this->assertSame(1, Bitacora::where('accion', 'cita_confirmada')->count());
    }

    public function test_marca_atendida_una_cita_que_ya_comenzo_conservando_el_registro(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->accion($this->recepcionista(), $cita, 'atendida')->assertSessionHas('exito');

        $this->assertSame('ATENDIDA', $cita->refresh()->estado);
        $this->assertDatabaseCount('citas', 1);
        $this->assertSame(1, Bitacora::where('accion', 'cita_atendida')->count());
    }

    public function test_registra_la_inasistencia_de_una_cita_que_ya_comenzo(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::HOY, '08:00', 'CONFIRMADA');

        $this->accion($this->recepcionista(), $cita, 'inasistencia')->assertSessionHas('exito');

        $this->assertSame('INASISTENCIA', $cita->refresh()->estado);
        $this->assertSame(1, Bitacora::where('accion', 'cita_inasistencia')->count());
    }

    // --- Transiciones inválidas ------------------------------------------------------------

    public function test_una_cita_cancelada_no_puede_marcarse_como_atendida(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::HOY, '08:00', 'CANCELADA');

        $this->accion($this->recepcionista(), $cita, 'atendida')
            ->assertSessionHas('error', 'La cita está cancelada: no se puede cambiar su estado.');

        $this->assertSame('CANCELADA', $cita->refresh()->estado);
    }

    public function test_no_cambia_el_estado_de_una_cita_atendida_ni_con_inasistencia(): void
    {
        $recepcion = $this->recepcionista();

        foreach (['ATENDIDA' => '08:00', 'INASISTENCIA' => '08:30'] as $estado => $hora) {
            $cita = $this->cita($this->crearPaciente(), self::HOY, $hora, $estado);

            foreach (['confirmar', 'atendida', 'inasistencia'] as $accion) {
                $this->accion($recepcion, $cita, $accion)->assertSessionHas('error');
            }

            $this->assertSame($estado, $cita->refresh()->estado);
        }
    }

    public function test_no_registra_asistencia_ni_inasistencia_de_una_cita_futura(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::HOY, '11:00');
        $recepcion = $this->recepcionista();

        $this->accion($recepcion, $cita, 'atendida')->assertSessionHas('error');
        $this->accion($recepcion, $cita, 'inasistencia')->assertSessionHas('error');

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    public function test_no_confirma_una_cita_que_ya_comenzo_ni_una_ya_confirmada(): void
    {
        $recepcion = $this->recepcionista();
        $comenzada = $this->cita($this->crearPaciente());
        $confirmada = $this->cita($this->crearPaciente(), self::MANANA, '09:00', 'CONFIRMADA');

        $this->accion($recepcion, $comenzada, 'confirmar')->assertSessionHas('error', 'La cita ya comenzó: no se puede confirmar.');
        $this->accion($recepcion, $confirmada, 'confirmar')->assertSessionHas('error', 'La cita ya está confirmada.');
    }

    public function test_una_accion_inventada_se_rechaza(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->accion($this->recepcionista(), $cita, 'borrar')->assertSessionHasErrors('accion');
        $this->actingAs($this->recepcionista())->from('/volver')->patch(route('citas.asistencia', $cita), [])->assertSessionHasErrors('accion');

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    // --- Médico ----------------------------------------------------------------------------

    public function test_el_medico_marca_atendida_una_cita_de_su_agenda(): void
    {
        [$cuenta, $ficha] = $this->cuentaMedico();
        $cita = $this->cita($this->crearPaciente(), self::HOY, '08:00', 'PROGRAMADA', $ficha);

        $this->actingAs($cuenta)->patch(route('agenda.atender', $cita))
            ->assertRedirect(route('agenda.index', ['fecha' => self::HOY]))
            ->assertSessionHas('exito');

        $this->assertSame('ATENDIDA', $cita->refresh()->estado);
    }

    public function test_el_medico_no_marca_la_cita_de_otro_medico(): void
    {
        [$cuenta] = $this->cuentaMedico();
        $ajena = $this->cita($this->crearPaciente());

        $this->actingAs($cuenta)->patch(route('agenda.atender', $ajena))->assertNotFound();

        $this->assertSame('PROGRAMADA', $ajena->refresh()->estado);
    }

    public function test_el_medico_no_marca_una_cita_cancelada_ni_futura(): void
    {
        [$cuenta, $ficha] = $this->cuentaMedico();
        $cancelada = $this->cita($this->crearPaciente(), self::HOY, '08:00', 'CANCELADA', $ficha);
        $futura = $this->cita($this->crearPaciente(), self::HOY, '11:00', 'PROGRAMADA', $ficha);

        $this->actingAs($cuenta)->from('/volver')->patch(route('agenda.atender', $cancelada))->assertSessionHas('error');
        $this->actingAs($cuenta)->from('/volver')->patch(route('agenda.atender', $futura))->assertSessionHas('error');

        $this->assertSame('CANCELADA', $cancelada->refresh()->estado);
        $this->assertSame('PROGRAMADA', $futura->refresh()->estado);
    }

    public function test_la_agenda_ofrece_marcar_atendida_solo_al_medico_y_solo_si_corresponde(): void
    {
        [$cuenta, $ficha] = $this->cuentaMedico();
        $this->cita($this->crearPaciente(), self::HOY, '08:00', 'PROGRAMADA', $ficha);
        $this->cita($this->crearPaciente(), self::HOY, '11:00', 'PROGRAMADA', $ficha);

        $this->actingAs($cuenta)->get(route('agenda.index', ['fecha' => self::HOY]))
            ->assertSee('Marcar atendida', false);

        $this->actingAs($this->recepcionista())->get(route('agenda.index', ['medico' => $ficha->id, 'fecha' => self::HOY]))
            ->assertDontSee('Marcar atendida', false);
    }

    // --- Paciente --------------------------------------------------------------------------

    public function test_el_paciente_confirma_su_propia_cita(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha, self::MANANA, '09:00');

        $this->actingAs($cuenta)->patch(route('mis-citas.confirmar', $cita))
            ->assertRedirect(route('mis-citas.comprobante', $cita));

        $this->assertSame('CONFIRMADA', $cita->refresh()->estado);
    }

    public function test_el_paciente_no_confirma_la_cita_de_otro(): void
    {
        [$cuenta] = $this->cuentaPaciente();
        $ajena = $this->cita($this->crearPaciente(), self::MANANA, '09:00');

        $this->actingAs($cuenta)->patch(route('mis-citas.confirmar', $ajena))->assertNotFound();

        $this->assertSame('PROGRAMADA', $ajena->refresh()->estado);
    }

    public function test_el_paciente_no_puede_marcar_atendida_ni_inasistencia(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha);

        $this->actingAs($cuenta)->patch(route('citas.asistencia', $cita), ['accion' => 'atendida'])->assertForbidden();
        $this->actingAs($cuenta)->patch(route('agenda.atender', $cita))->assertForbidden();

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    // --- Permisos y pantalla ---------------------------------------------------------------

    public function test_cada_rol_solo_usa_sus_rutas(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::MANANA, '09:00');

        foreach (['MEDICO', 'ADMINISTRADOR'] as $rol) {
            $cuenta = $this->crearCuenta($rol);
            $this->actingAs($cuenta)->patch(route('citas.asistencia', $cita), ['accion' => 'confirmar'])->assertForbidden();
            $this->actingAs($cuenta)->patch(route('mis-citas.confirmar', $cita))->assertForbidden();
        }

        $this->actingAs($this->recepcionista())->patch(route('agenda.atender', $cita))->assertForbidden();
        $this->actingAs($this->crearCuenta('ADMINISTRADOR'))->patch(route('agenda.atender', $cita))->assertForbidden();

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->patch(route('citas.asistencia', $cita), ['accion' => 'atendida'])->assertRedirect(route('login'));
        $this->patch(route('agenda.atender', $cita))->assertRedirect(route('login'));
        $this->patch(route('mis-citas.confirmar', $cita))->assertRedirect(route('login'));
    }

    public function test_el_comprobante_ofrece_solo_las_acciones_que_corresponden(): void
    {
        $recepcion = $this->recepcionista();
        $futura = $this->cita($this->crearPaciente(), self::MANANA, '09:00');
        $comenzada = $this->cita($this->crearPaciente());

        $this->actingAs($recepcion)->get(route('citas.comprobante', $futura))
            ->assertSee('Confirmar asistencia')->assertDontSee('Marcar como atendida');

        $this->actingAs($recepcion)->get(route('citas.comprobante', $comenzada))
            ->assertSee('Marcar como atendida')->assertSee('Registrar inasistencia')->assertDontSee('Confirmar asistencia');

        $this->accion($recepcion, $comenzada, 'atendida');
        $this->actingAs($recepcion)->get(route('citas.comprobante', $comenzada))
            ->assertDontSee('Marcar como atendida')->assertDontSee('Registrar inasistencia')->assertSee('Atendida');
    }
}
