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
 * CU-10: reprogramar cita (RF08, RN05 a RN07, RN13, RN15, RN17). El «ahora» es un lunes a las
 * 09:10 hora de La Paz; el médico atiende todos los días de 08:00 a 12:00 en bloques de 30 minutos.
 */
class ReprogramarCitaTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private const HOY = '2026-11-09';

    private const MANANA = '2026-11-10';

    private const LUNES = '2026-11-16';

    private const MARTES = '2026-11-17';

    private Especialidad $especialidad;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:10:00', 'America/La_Paz'));

        $this->especialidad = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 30])->refresh();
        $this->medico = $this->crearFichaMedico([], [], [$this->especialidad->id]);

        foreach (range(1, 7) as $dia) {
            HorarioAtencion::create(['medico_id' => $this->medico->id, 'sucursal_id' => Sucursal::firstOrFail()->id, 'dia_semana' => $dia, 'hora_inicio' => '08:00', 'hora_fin' => '12:00']);
        }
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

    private function cita(Paciente $paciente, string $fecha = self::LUNES, string $inicio = '08:00', string $estado = 'PROGRAMADA', ?Medico $medico = null): Cita
    {
        $medico ??= $this->medico;

        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'especialidad_id' => $this->especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => CarbonImmutable::parse($inicio)->addMinutes(30)->format('H:i'),
            'estado' => $estado,
        ])->refresh();
    }

    private function reprogramar(Usuario $actor, Cita $cita, string $fecha, string $hora, bool $propia = false)
    {
        return $this->actingAs($actor)->from('/volver')
            ->put(route($propia ? 'mis-citas.actualizar' : 'citas.actualizar', $cita), ['fecha' => $fecha, 'hora_inicio' => $hora]);
    }

    // --- Reprogramación válida -------------------------------------------------------------

    public function test_la_recepcionista_cambia_la_fecha_y_la_hora_de_una_cita(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente, self::LUNES, '08:00');

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '10:30')
            ->assertRedirect(route('citas.comprobante', $cita))
            ->assertSessionHas('exito');

        $cita->refresh();
        $this->assertSame(self::MARTES, $cita->fecha->toDateString());
        $this->assertSame('10:30', $cita->desde());
        // La hora final la calcula el servidor con la duración de la especialidad (RN08).
        $this->assertSame('11:00', $cita->hasta());
        $this->assertSame('PROGRAMADA', $cita->estado);
        $this->assertSame($paciente->id, $cita->paciente_id);
        $this->assertSame($this->medico->id, $cita->medico_id);
        $this->assertDatabaseCount('citas', 1);
        $this->assertSame(1, Bitacora::where('accion', 'cita_reprogramada')->count());
    }

    public function test_una_cita_confirmada_vuelve_a_programada_al_cambiar_de_horario(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00', 'CONFIRMADA');

        $this->reprogramar($this->recepcionista(), $cita, self::LUNES, '09:00')->assertSessionHas('exito');

        $this->assertSame('PROGRAMADA', $cita->refresh()->estado);
    }

    public function test_el_horario_anterior_queda_libre_y_el_nuevo_ocupado(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente, self::LUNES, '08:00');
        $otro = $this->crearPaciente();

        $this->reprogramar($this->recepcionista(), $cita, self::LUNES, '09:00');

        $horas = array_column(app(CitaService::class)->horariosDisponibles($otro, $this->medico, $this->especialidad, self::LUNES), 'hora_inicio');
        $this->assertContains('08:00', $horas);
        $this->assertNotContains('09:00', $horas);
    }

    public function test_puede_moverse_a_un_horario_que_se_superpone_con_su_propio_horario_actual(): void
    {
        // La cita de 08:00 a 08:30 no choca consigo misma: la grilla ofrece 08:00, no hay solapamiento parcial.
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:30');

        $this->reprogramar($this->recepcionista(), $cita, self::LUNES, '08:00')->assertSessionHas('exito');

        $this->assertSame('08:00', $cita->refresh()->desde());
    }

    public function test_la_pantalla_lista_los_horarios_disponibles_y_no_el_ocupado_por_otra_cita(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00');
        $this->cita($this->crearPaciente(), self::MARTES, '08:00');

        $this->actingAs($this->recepcionista())->get(route('citas.reprogramar', ['cita' => $cita, 'fecha' => self::MARTES]))
            ->assertOk()
            ->assertSee('Reprogramar cita')
            ->assertSee('value="08:30"', false)
            ->assertDontSee('value="08:00"', false);
    }

    public function test_el_comprobante_ofrece_reprogramar_solo_en_citas_vigentes(): void
    {
        $recepcion = $this->recepcionista();
        $vigente = $this->cita($this->crearPaciente(), self::LUNES, '08:00');
        $cancelada = $this->cita($this->crearPaciente(), self::LUNES, '09:00', 'CANCELADA');

        $this->actingAs($recepcion)->get(route('citas.comprobante', $vigente))->assertSee(route('citas.reprogramar', $vigente), false);
        $this->actingAs($recepcion)->get(route('citas.comprobante', $cancelada))->assertDontSee(route('citas.reprogramar', $cancelada), false);
    }

    // --- Conflictos y reglas del horario (RN05 a RN07) -------------------------------------

    public function test_rechaza_un_horario_ocupado_por_el_medico(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00');
        $this->cita($this->crearPaciente(), self::MARTES, '10:00');

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '10:00')
            ->assertSessionHas('error', 'El médico ya tiene una cita en ese horario: elija otra hora.');

        $this->assertSame(self::LUNES, $cita->refresh()->fecha->toDateString());
    }

    public function test_rechaza_un_horario_que_se_superpone_con_otra_cita_del_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $otroMedico = $this->crearFichaMedico([], [], [$this->especialidad->id]);
        foreach (range(1, 7) as $dia) {
            HorarioAtencion::create(['medico_id' => $otroMedico->id, 'sucursal_id' => Sucursal::firstOrFail()->id, 'dia_semana' => $dia, 'hora_inicio' => '08:00', 'hora_fin' => '12:00']);
        }
        $cita = $this->cita($paciente, self::LUNES, '08:00');
        $this->cita($paciente, self::MARTES, '10:00', 'PROGRAMADA', $otroMedico);

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '10:00')
            ->assertSessionHas('error', 'El paciente ya tiene otra cita que se superpone con ese horario: elija otra hora.');

        $this->assertSame(self::LUNES, $cita->refresh()->fecha->toDateString());
    }

    public function test_una_cita_cancelada_del_mismo_horario_no_estorba(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00');
        $this->cita($this->crearPaciente(), self::MARTES, '10:00', 'CANCELADA');

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '10:00')->assertSessionHas('exito');
    }

    public function test_rechaza_horas_fuera_del_horario_del_medico_o_fuera_de_la_grilla(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00');
        $recepcion = $this->recepcionista();

        foreach (['07:30', '12:00', '08:10'] as $hora) {
            $this->reprogramar($recepcion, $cita, self::MARTES, $hora)
                ->assertSessionHas('error', 'La hora elegida no corresponde a un horario de atención del médico ese día.');
        }

        $this->assertSame('08:00', $cita->refresh()->desde());
    }

    public function test_rechaza_fechas_pasadas_lejanas_o_malformadas(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00');
        $recepcion = $this->recepcionista();

        $this->reprogramar($recepcion, $cita, '2026-11-02', '10:00')->assertSessionHas('error', 'No se pueden registrar citas en fechas pasadas.');
        $this->reprogramar($recepcion, $cita, '2027-03-01', '10:00')->assertSessionHas('error');
        $this->reprogramar($recepcion, $cita, '16/11/2026', '10:00')->assertSessionHasErrors('fecha');
        $this->reprogramar($recepcion, $cita, self::MARTES, '25:99')->assertSessionHasErrors('hora_inicio');

        $this->assertSame(self::LUNES, $cita->refresh()->fecha->toDateString());
    }

    public function test_rechaza_dejar_la_cita_en_el_mismo_horario(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00');

        $this->reprogramar($this->recepcionista(), $cita, self::LUNES, '08:00')
            ->assertSessionHas('error', 'Ese ya es el horario actual de la cita: elija otra fecha u hora.');

        $this->assertSame(0, Bitacora::where('accion', 'cita_reprogramada')->count());
    }

    public function test_rechaza_un_medico_desactivado(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::LUNES, '08:00');
        $this->medico->update(['estado' => false]);

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '10:00')->assertSessionHas('error');

        $this->assertSame(self::LUNES, $cita->refresh()->fecha->toDateString());
    }

    // --- Estados que no se reprograman (RF08, RN10) ----------------------------------------

    public function test_no_reprograma_citas_atendidas_canceladas_ni_con_inasistencia(): void
    {
        $recepcion = $this->recepcionista();

        foreach (['ATENDIDA' => '08:00', 'CANCELADA' => '08:30', 'INASISTENCIA' => '09:00'] as $estado => $hora) {
            $cita = $this->cita($this->crearPaciente(), self::LUNES, $hora, $estado);

            $this->reprogramar($recepcion, $cita, self::MARTES, '10:00')->assertSessionHas('error');

            $this->assertSame($estado, $cita->refresh()->estado);
            $this->assertSame(self::LUNES, $cita->fecha->toDateString());
        }
    }

    public function test_no_reprograma_una_cita_que_ya_comenzo(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::HOY, '09:00');

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '10:00')
            ->assertSessionHas('error', 'La cita ya comenzó o ya pasó: no se puede reprogramar.');
    }

    // --- RN15: el Paciente reprograma con 24 horas -----------------------------------------

    public function test_el_paciente_reprograma_su_cita_con_mas_de_24_horas(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha, self::LUNES, '08:00');

        $this->reprogramar($cuenta, $cita, self::MARTES, '09:00', true)
            ->assertRedirect(route('mis-citas.comprobante', $cita))
            ->assertSessionHas('exito');

        $this->assertSame(self::MARTES, $cita->refresh()->fecha->toDateString());
    }

    public function test_el_paciente_no_reprograma_con_menos_de_24_horas(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha, self::MANANA, '08:00');

        $this->reprogramar($cuenta, $cita, self::MARTES, '09:00', true)->assertSessionHas('error');

        $this->assertSame(self::MANANA, $cita->refresh()->fecha->toDateString());
    }

    public function test_la_recepcionista_reprograma_sin_la_restriccion_de_24_horas(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::HOY, '11:00');

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '09:00')->assertSessionHas('exito');
    }

    // --- Permisos y propiedad (RN12) --------------------------------------------------------

    public function test_el_paciente_no_reprograma_la_cita_de_otro(): void
    {
        [$cuenta] = $this->cuentaPaciente();
        $ajena = $this->cita($this->crearPaciente(), self::LUNES, '08:00');

        $this->reprogramar($cuenta, $ajena, self::MARTES, '09:00', true)->assertNotFound();
        $this->actingAs($cuenta)->get(route('mis-citas.reprogramar', $ajena))->assertNotFound();

        $this->assertSame(self::LUNES, $ajena->refresh()->fecha->toDateString());
    }

    public function test_cada_rol_solo_usa_sus_rutas(): void
    {
        [$cuentaPaciente, $ficha] = $this->cuentaPaciente();
        $cita = $this->cita($ficha, self::LUNES, '08:00');

        $this->reprogramar($cuentaPaciente, $cita, self::MARTES, '09:00')->assertForbidden();

        foreach (['MEDICO', 'ADMINISTRADOR'] as $rol) {
            $cuenta = $this->crearCuenta($rol);
            $this->reprogramar($cuenta, $cita, self::MARTES, '09:00')->assertForbidden();
            $this->reprogramar($cuenta, $cita, self::MARTES, '09:00', true)->assertForbidden();
            $this->actingAs($cuenta)->get(route('citas.reprogramar', $cita))->assertForbidden();
        }

        $this->reprogramar($this->recepcionista(), $cita, self::MARTES, '09:00', true)->assertForbidden();

        $this->assertSame(self::LUNES, $cita->refresh()->fecha->toDateString());
    }

    public function test_el_formulario_no_cambia_al_paciente_ni_al_medico(): void
    {
        $paciente = $this->crearPaciente();
        $otro = $this->crearPaciente();
        $cita = $this->cita($paciente, self::LUNES, '08:00');

        $this->actingAs($this->recepcionista())->from('/volver')
            ->put(route('citas.actualizar', $cita), ['fecha' => self::MARTES, 'hora_inicio' => '09:00', 'paciente_id' => $otro->id, 'estado' => 'ATENDIDA', 'medico_id' => 999]);

        $cita->refresh();
        $this->assertSame($paciente->id, $cita->paciente_id);
        $this->assertSame($this->medico->id, $cita->medico_id);
        $this->assertSame('PROGRAMADA', $cita->estado);
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->get(route('citas.reprogramar', $cita))->assertRedirect(route('login'));
        $this->put(route('citas.actualizar', $cita), [])->assertRedirect(route('login'));
    }
}
