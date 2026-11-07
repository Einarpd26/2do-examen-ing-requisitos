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
use App\Services\CitaService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-11: cancelar cita (RF09, RN09, RN10, RN15). El «ahora» de las pruebas es un lunes a las
 * 09:10 hora de La Paz; el médico atiende todos los días de 08:00 a 12:00 (especialidad de 30 min).
 */
class CancelarCitaTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private const AHORA = '2026-11-09 09:10:00';

    private const HOY = '2026-11-09';

    private const MANANA = '2026-11-10';

    private const LUNES = '2026-11-16';

    private Especialidad $especialidad;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::AHORA, 'America/La_Paz'));

        $this->especialidad = Especialidad::create(['nombre' => 'Cardiología', 'duracion_min' => 30])->refresh();
        $this->medico = $this->crearFichaMedico([], [], [$this->especialidad->id]);

        foreach (range(1, 7) as $dia) {
            HorarioAtencion::create([
                'medico_id' => $this->medico->id,
                'sucursal_id' => Sucursal::firstOrFail()->id,
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Cita::flushEventListeners();

        parent::tearDown();
    }

    // --- Ayudas -------------------------------------------------------------------------

    private function recepcionista(): Usuario
    {
        return $this->crearCuenta('RECEPCIONISTA');
    }

    /** Cuenta de paciente con su ficha. @return array{0: Usuario, 1: Paciente} */
    private function cuentaPaciente(): array
    {
        $cuenta = $this->crearCuenta('PACIENTE');
        $ficha = Paciente::create(['persona_id' => $cuenta->persona_id, 'verificado' => true])->refresh();

        return [$cuenta, $ficha];
    }

    private function cita(Paciente $paciente, string $fecha = self::LUNES, string $inicio = '08:00', string $fin = '08:30', string $estado = 'PROGRAMADA'): Cita
    {
        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $this->medico->id,
            'especialidad_id' => $this->especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $estado,
        ])->refresh();
    }

    private function cancelar(Usuario $actor, Cita $cita, string $motivo = 'No puedo asistir', bool $propia = false)
    {
        return $this->actingAs($actor)
            ->from('/volver')
            ->patch(route($propia ? 'mis-citas.cancelar' : 'citas.cancelar', $cita), ['motivo_cancelacion' => $motivo]);
    }

    // --- Prueba 6 del enunciado ---------------------------------------------------------

    public function test_cancela_una_cita_y_conserva_el_historial(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente);

        $this->cancelar($this->recepcionista(), $cita, 'El paciente viaja')
            ->assertRedirect(route('citas.comprobante', $cita))
            ->assertSessionHas('exito');

        // La fila sigue existiendo (historial, RN10): solo cambia de estado y guarda el motivo.
        $this->assertDatabaseCount('citas', 1);
        $cita->refresh();
        $this->assertSame('CANCELADA', $cita->estado);
        $this->assertSame('El paciente viaja', $cita->motivo_cancelacion);
        $this->assertSame($paciente->id, $cita->paciente_id);

        $this->assertDatabaseHas('bitacora', ['accion' => 'cita_cancelada', 'entidad_id' => $cita->id]);
        $this->assertSame(1, Bitacora::where('accion', 'cita_cancelada')->count());
    }

    public function test_la_cita_cancelada_se_ve_en_el_comprobante_con_su_motivo(): void
    {
        $cita = $this->cita($this->crearPaciente());
        $recepcion = $this->recepcionista();
        $this->cancelar($recepcion, $cita, 'Médico de licencia');

        $this->actingAs($recepcion)->get(route('citas.comprobante', $cita))
            ->assertOk()
            ->assertSee('Cancelada')
            ->assertSee('Médico de licencia')
            ->assertDontSee('Cancelar esta cita');
    }

    public function test_el_comprobante_de_una_cita_vigente_ofrece_cancelar(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->actingAs($this->recepcionista())->get(route('citas.comprobante', $cita))
            ->assertOk()
            ->assertSee('Cancelar esta cita');
    }

    // --- RN09: libera el horario --------------------------------------------------------

    public function test_el_horario_cancelado_queda_disponible_otra_vez(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente);
        $servicio = app(CitaService::class);
        $horas = fn () => array_column($servicio->horariosDisponibles($paciente, $this->medico, $this->especialidad, self::LUNES), 'hora_inicio');

        $this->assertNotContains('08:00', $horas());

        $this->cancelar($this->recepcionista(), $cita);

        $this->assertContains('08:00', $horas());
    }

    // --- Estados que no se pueden cancelar (RN10) ---------------------------------------

    public function test_no_cancela_una_cita_atendida_ni_una_inasistencia(): void
    {
        $recepcion = $this->recepcionista();

        foreach (['ATENDIDA' => ['08:00', '08:30'], 'INASISTENCIA' => ['09:00', '09:30']] as $estado => [$inicio, $fin]) {
            $cita = $this->cita($this->crearPaciente(), self::LUNES, $inicio, $fin, $estado);

            $this->cancelar($recepcion, $cita)
                ->assertRedirect('/volver')
                ->assertSessionHas('error', 'Solo se pueden cancelar las citas programadas o confirmadas.');

            $this->assertSame($estado, $cita->refresh()->estado);
        }
    }

    public function test_no_cancela_dos_veces_la_misma_cita(): void
    {
        $cita = $this->cita($this->crearPaciente());
        $recepcion = $this->recepcionista();

        $this->cancelar($recepcion, $cita);
        $this->cancelar($recepcion, $cita, 'Otra vez')
            ->assertSessionHas('error', 'La cita ya está cancelada.');

        $this->assertNotSame('Otra vez', $cita->refresh()->motivo_cancelacion);
        $this->assertSame(1, Bitacora::where('accion', 'cita_cancelada')->count());
    }

    public function test_cancela_una_cita_confirmada(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00', '08:30', 'CONFIRMADA');

        $this->cancelar($this->recepcionista(), $cita)->assertSessionHas('exito');

        $this->assertSame('CANCELADA', $cita->refresh()->estado);
    }

    public function test_no_cancela_una_cita_que_ya_comenzo(): void
    {
        // Ahora son las 09:10: la cita de las 09:00 ya comenzó, aunque la cancele la Recepcionista.
        $cita = $this->cita($this->crearPaciente(), self::HOY, '09:00', '09:30');

        $this->cancelar($this->recepcionista(), $cita)
            ->assertSessionHas('error', 'La cita ya comenzó o ya pasó: no se puede cancelar.');

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    // --- Validación del motivo ----------------------------------------------------------

    public function test_exige_un_motivo_de_cancelacion(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->cancelar($this->recepcionista(), $cita, '')
            ->assertSessionHasErrors('motivo_cancelacion');

        $this->actingAs($this->recepcionista())->from('/volver')
            ->patch(route('citas.cancelar', $cita), ['motivo_cancelacion' => str_repeat('a', 501)])
            ->assertSessionHasErrors('motivo_cancelacion');

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    public function test_un_motivo_de_solo_espacios_se_rechaza(): void
    {
        // Laravel recorta los espacios (TrimStrings) y el campo requerido queda vacío.
        $cita = $this->cita($this->crearPaciente());

        $this->cancelar($this->recepcionista(), $cita, '   ')
            ->assertSessionHasErrors('motivo_cancelacion');

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    // --- RN15: el Paciente cancela con 24 horas de anticipación -------------------------

    public function test_el_paciente_cancela_su_cita_con_mas_de_24_horas(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha, self::LUNES);

        $this->cancelar($cuenta, $cita, 'Cambio de planes', true)
            ->assertRedirect(route('mis-citas.comprobante', $cita))
            ->assertSessionHas('exito');

        $this->assertSame('CANCELADA', $cita->refresh()->estado);
    }

    public function test_el_paciente_no_cancela_con_menos_de_24_horas(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        // Hoy 09:10; mañana 08:00 está a menos de 24 horas.
        $cita = $this->cita($ficha, self::MANANA, '08:00', '08:30');

        $this->cancelar($cuenta, $cita, 'No puedo ir', true)
            ->assertRedirect('/volver')
            ->assertSessionHas('error');

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
        $this->assertSame(0, Bitacora::where('accion', 'cita_cancelada')->count());
    }

    public function test_el_limite_de_24_horas_es_exacto(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();

        // Mañana 09:10 está justo a 24 horas: se permite. Mañana 09:00 queda a 23 h 50 min: se rechaza.
        $enElLimite = $this->cita($ficha, self::MANANA, '09:10', '09:40');
        $this->cancelar($cuenta, $enElLimite, 'Motivo', true)->assertSessionHas('exito');

        $antes = $this->cita($ficha, self::MANANA, '09:00', '09:30');
        $this->cancelar($cuenta, $antes, 'Motivo', true)->assertSessionHas('error');

        $this->assertSame('CANCELADA', $enElLimite->refresh()->estado);
        $this->assertSame('PROGRAMADA', $antes->refresh()->estado);
    }

    public function test_la_recepcionista_cancela_sin_la_restriccion_de_24_horas(): void
    {
        // Hoy 11:00, dentro de 2 horas: la Recepcionista sí puede, el Paciente no.
        $cita = $this->cita($this->crearPaciente(), self::HOY, '11:00', '11:30');

        $this->cancelar($this->recepcionista(), $cita)->assertSessionHas('exito');

        $this->assertSame('CANCELADA', $cita->refresh()->estado);
    }

    // --- RN12: permisos y propiedad -----------------------------------------------------

    public function test_el_paciente_no_cancela_la_cita_de_otro(): void
    {
        [$cuenta] = $this->cuentaPaciente();
        $ajena = $this->cita($this->crearPaciente());

        $this->cancelar($cuenta, $ajena, 'Motivo', true)->assertNotFound();

        $this->assertSame('PROGRAMADA', $ajena->refresh()->estado);
    }

    public function test_cada_rol_solo_entra_a_su_ruta_de_cancelacion(): void
    {
        [$cuentaPaciente, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha);

        // El Paciente no usa la ruta de la Recepcionista.
        $this->actingAs($cuentaPaciente)->patch(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'x'])->assertForbidden();

        // Recepcionista, Médico y Administrador no usan la ruta del Paciente.
        foreach (['RECEPCIONISTA', 'MEDICO', 'ADMINISTRADOR'] as $rol) {
            $this->actingAs($this->crearCuenta($rol))
                ->patch(route('mis-citas.cancelar', $cita), ['motivo_cancelacion' => 'x'])
                ->assertForbidden();
        }

        // El Médico y el Administrador tampoco cancelan por la ruta de la Recepcionista.
        foreach (['MEDICO', 'ADMINISTRADOR'] as $rol) {
            $this->actingAs($this->crearCuenta($rol))
                ->patch(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'x'])
                ->assertForbidden();
        }

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    public function test_sin_sesion_no_se_puede_cancelar(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->patch(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'x'])
            ->assertRedirect(route('login'));

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    public function test_el_paciente_ve_el_boton_solo_en_sus_citas_vigentes(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha);

        $this->actingAs($cuenta)->get(route('mis-citas.comprobante', $cita))
            ->assertOk()
            ->assertSee('Cancelar esta cita')
            ->assertSee('24 horas');
    }

    public function test_el_formulario_no_acepta_otro_estado_ni_otros_campos(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->actingAs($this->recepcionista())->from('/volver')
            ->patch(route('citas.cancelar', $cita), ['motivo_cancelacion' => 'Motivo', 'estado' => 'ATENDIDA', 'fecha' => '2030-01-01'])
            ->assertSessionHas('exito');

        $cita->refresh();
        $this->assertSame('CANCELADA', $cita->estado);
        $this->assertSame('2026-11-16', $cita->fecha->toDateString());
    }

    public function test_una_cita_inexistente_responde_404(): void
    {
        $this->actingAs($this->recepcionista())
            ->patch(route('citas.cancelar', 999999), ['motivo_cancelacion' => 'x'])
            ->assertNotFound();
    }
}
