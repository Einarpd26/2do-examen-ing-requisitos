<?php

namespace Tests\Feature\Citas;

use App\Exceptions\ReglaDeNegocioException;
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
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-09: registrar cita (RF06, RF07). El «ahora» de las pruebas es un lunes a las 09:10 hora de
 * La Paz; el médico atiende todos los días de 08:00 a 12:00 y su especialidad dura 30 minutos.
 */
class CitaTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private const AHORA = '2026-11-09 09:10:00';

    private const HOY = '2026-11-09';

    private const LUNES = '2026-11-16';

    private Especialidad $especialidad;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse(self::AHORA, 'America/La_Paz'));

        $this->especialidad = Especialidad::create(['nombre' => 'Cardiología', 'duracion_min' => 30])->refresh();
        $this->medico = $this->medicoConAgenda($this->especialidad);
    }

    protected function tearDown(): void
    {
        Cita::flushEventListeners();

        parent::tearDown();
    }

    // --- Ayudas -------------------------------------------------------------------------

    /** Médico activo que atiende todos los días de 08:00 a 12:00. */
    private function medicoConAgenda(Especialidad $especialidad, array $persona = []): Medico
    {
        $medico = $this->crearFichaMedico($persona, [], [$especialidad->id]);

        foreach (range(1, 7) as $dia) {
            HorarioAtencion::create([
                'medico_id' => $medico->id,
                'sucursal_id' => Sucursal::firstOrFail()->id,
                'dia_semana' => $dia,
                'hora_inicio' => '08:00',
                'hora_fin' => '12:00',
            ]);
        }

        return $medico;
    }

    private function recepcionista(): Usuario
    {
        return $this->crearCuenta('RECEPCIONISTA');
    }

    /** Cuenta de paciente con su ficha. @return array{0: Usuario, 1: Paciente} */
    private function cuentaPaciente(bool $verificado = true): array
    {
        $cuenta = $this->crearCuenta('PACIENTE');
        $ficha = Paciente::create(['persona_id' => $cuenta->persona_id, 'verificado' => $verificado])->refresh();

        return [$cuenta, $ficha];
    }

    private function datos(Paciente $paciente, array $reemplazo = []): array
    {
        return array_merge([
            'paciente_id' => $paciente->id,
            'especialidad_id' => $this->especialidad->id,
            'medico_id' => $this->medico->id,
            'fecha' => self::LUNES,
            'hora_inicio' => '08:00',
            'modalidad' => 'PRESENCIAL',
            'motivo' => 'Control',
        ], $reemplazo);
    }

    private function cita(Paciente $paciente, string $inicio, string $fin, string $estado = 'PROGRAMADA', ?Medico $medico = null, string $fecha = self::LUNES): Cita
    {
        $medico ??= $this->medico;

        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'especialidad_id' => $medico->especialidades->first()->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $estado,
        ])->refresh();
    }

    private function horas(Paciente $paciente, string $fecha = self::LUNES, ?Medico $medico = null): array
    {
        $disponibles = app(CitaService::class)
            ->horariosDisponibles($paciente, $medico ?? $this->medico, $this->especialidad, $fecha);

        return array_column($disponibles, 'hora_inicio');
    }

    // --- Pruebas 3, 4 y 5 del enunciado -------------------------------------------------

    public function test_programa_una_cita_en_un_horario_disponible(): void
    {
        $paciente = $this->crearPaciente();
        $recepcion = $this->recepcionista();

        // El formulario sin JavaScript lista los horarios libres como opciones.
        $this->actingAs($recepcion)
            ->get(route('citas.nueva', [
                'paciente' => $paciente->id,
                'especialidad' => $this->especialidad->id,
                'medico' => $this->medico->id,
                'fecha' => self::LUNES,
            ]))
            ->assertOk()
            ->assertSee('value="08:00"', false)
            ->assertSee('value="11:30"', false);

        $respuesta = $this->post(route('citas.store'), $this->datos($paciente, ['hora_inicio' => '08:30', 'motivo' => 'Dolor de pecho']));

        $cita = Cita::firstOrFail();
        $respuesta->assertRedirect(route('citas.comprobante', $cita));
        $this->assertSame($paciente->id, $cita->paciente_id);
        $this->assertSame($this->medico->id, $cita->medico_id);
        $this->assertSame('PROGRAMADA', $cita->estado);
        $this->assertSame('PRESENCIAL', $cita->modalidad);
        $this->assertSame('Dolor de pecho', $cita->motivo);
        $this->assertSame(self::LUNES, $cita->fecha->format('Y-m-d'));
        $this->assertSame('08:30', $cita->desde());
        $this->assertSame('09:00', $cita->hasta());
        $this->assertSame(Sucursal::firstOrFail()->id, $cita->sucursal_id);

        $this->assertDatabaseHas('bitacora', ['accion' => 'cita_creada', 'usuario_id' => $recepcion->id, 'entidad' => 'citas', 'entidad_id' => $cita->id]);

        $this->get(route('citas.comprobante', $cita))
            ->assertOk()
            ->assertSee($cita->codigo)
            ->assertSee($paciente->persona->nombre_completo)
            ->assertSee('08:30');
    }

    public function test_impide_otra_cita_superpuesta_para_el_mismo_medico(): void
    {
        $this->cita($this->crearPaciente(), '08:00', '08:30');
        $otro = $this->crearPaciente();

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($otro, ['hora_inicio' => '08:00']))
            ->assertSessionHas('error', 'El médico ya tiene una cita en ese horario: elija otra hora.');

        $this->assertSame(1, Cita::count());
    }

    public function test_parametros_manipulados_en_la_url_no_rompen_el_formulario(): void
    {
        $this->actingAs($this->recepcionista())
            ->get(route('citas.nueva').'?q[]=x&fecha[]=x&paciente[]=1&especialidad[]=1&medico[]=1')
            ->assertOk();
    }

    public function test_un_id_fuera_de_rango_se_rechaza_como_dato_invalido(): void
    {
        $paciente = $this->crearPaciente();
        $fueraDeRango = '99999999999999999999';

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, [
                'paciente_id' => $fueraDeRango,
                'medico_id' => $fueraDeRango,
                'especialidad_id' => $fueraDeRango,
            ]))
            ->assertSessionHasErrors(['paciente_id', 'medico_id', 'especialidad_id']);

        $this->assertSame(0, Cita::count());
    }

    public function test_rechaza_reservar_fuera_del_horario_del_medico(): void
    {
        $paciente = $this->crearPaciente();
        $this->actingAs($this->recepcionista());

        // Antes de abrir, después de cerrar, y una hora que no cae en la cuadrícula (08:15).
        foreach (['07:30', '12:00', '13:00', '08:15'] as $hora) {
            $this->post(route('citas.store'), $this->datos($paciente, ['hora_inicio' => $hora]))
                ->assertSessionHas('error');
        }

        // Un día en que el médico no atiende.
        HorarioAtencion::where('medico_id', $this->medico->id)->where('dia_semana', 2)->delete();
        $this->post(route('citas.store'), $this->datos($paciente, ['fecha' => '2026-11-17']))
            ->assertSessionHas('error');

        $this->assertSame(0, Cita::count());
    }

    // --- Disponibilidad (D3, RN05, RN06, RN09) -------------------------------------------

    public function test_la_cuadricula_parte_de_cada_bloque_y_cada_horario_termina_dentro_de_su_bloque(): void
    {
        $paciente = $this->crearPaciente();
        // Bloques de 08:00 a 09:45 y de 14:00 a 15:00: en el primero el último horario posible empieza a las 09:00.
        HorarioAtencion::where('medico_id', $this->medico->id)->where('dia_semana', 1)->delete();
        foreach ([['08:00', '09:45'], ['14:00', '15:00']] as [$inicio, $fin]) {
            HorarioAtencion::create([
                'medico_id' => $this->medico->id, 'sucursal_id' => Sucursal::firstOrFail()->id,
                'dia_semana' => 1, 'hora_inicio' => $inicio, 'hora_fin' => $fin,
            ]);
        }

        $this->assertSame(['08:00', '08:30', '09:00', '14:00', '14:30'], $this->horas($paciente));
    }

    public function test_quita_los_horarios_ocupados_por_el_medico_y_por_el_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $otro = $this->crearPaciente();
        $otroMedico = $this->medicoConAgenda($this->especialidad);

        $this->cita($otro, '08:00', '08:30');                        // ocupa al médico
        $this->cita($paciente, '09:30', '10:00', 'PROGRAMADA', $otroMedico); // ocupa al paciente (D2)

        $this->assertSame(['08:30', '09:00', '10:00', '10:30', '11:00', '11:30'], $this->horas($paciente));
    }

    public function test_las_citas_canceladas_no_ocupan_horario(): void
    {
        $paciente = $this->crearPaciente();
        $this->cita($this->crearPaciente(), '08:00', '08:30', 'CANCELADA');
        $this->cita($paciente, '09:00', '09:30', 'CANCELADA', $this->medicoConAgenda($this->especialidad));

        $this->assertContains('08:00', $this->horas($paciente));
        $this->assertContains('09:00', $this->horas($paciente));

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, ['hora_inicio' => '08:00']))
            ->assertSessionMissing('error');
        $this->assertSame(1, Cita::where('estado', 'PROGRAMADA')->count());
    }

    public function test_hoy_no_se_ofrecen_los_horarios_que_ya_pasaron(): void
    {
        $paciente = $this->crearPaciente();

        // Son las 09:10: ya pasaron 08:00, 08:30 y 09:00.
        $this->assertSame(['09:30', '10:00', '10:30', '11:00', '11:30'], $this->horas($paciente, self::HOY));

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, ['fecha' => self::HOY, 'hora_inicio' => '09:00']))
            ->assertSessionHas('error');
        $this->assertSame(0, Cita::count());
    }

    public function test_rechaza_las_fechas_pasadas(): void
    {
        $paciente = $this->crearPaciente();

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, ['fecha' => '2026-11-08']))
            ->assertSessionHas('error');
        $this->assertSame(0, Cita::count());
    }

    public function test_el_limite_de_anticipacion_es_de_60_dias_y_sale_de_la_configuracion(): void
    {
        $paciente = $this->crearPaciente();
        $this->actingAs($this->recepcionista());

        // 09/11/2026 + 60 días = 08/01/2027.
        $this->post(route('citas.store'), $this->datos($paciente, ['fecha' => '2027-01-08']))->assertSessionMissing('error');
        $this->assertSame(1, Cita::count());

        $this->post(route('citas.store'), $this->datos($paciente, ['fecha' => '2027-01-09']))->assertSessionHas('error');
        $this->assertSame(1, Cita::count());

        config(['clinica.citas.dias_anticipacion_max' => 7]);
        $this->post(route('citas.store'), $this->datos($paciente, ['fecha' => '2026-11-17']))->assertSessionHas('error');
        $this->post(route('citas.store'), $this->datos($paciente, ['fecha' => '2026-11-16']))->assertSessionMissing('error');
        $this->assertSame(2, Cita::count());
    }

    public function test_el_formulario_avisa_de_una_fecha_fuera_de_rango_sin_registrar_nada(): void
    {
        $paciente = $this->crearPaciente();

        $this->actingAs($this->recepcionista())
            ->get(route('citas.nueva', [
                'paciente' => $paciente->id, 'especialidad' => $this->especialidad->id,
                'medico' => $this->medico->id, 'fecha' => '2026-11-01',
            ]))
            ->assertOk()
            ->assertSee('No se pueden registrar citas en fechas pasadas.');
    }

    // --- Reglas de negocio ---------------------------------------------------------------

    public function test_rn17_un_paciente_no_puede_tener_dos_citas_superpuestas_con_medicos_distintos(): void
    {
        $paciente = $this->crearPaciente();
        $otroMedico = $this->medicoConAgenda($this->especialidad);
        $this->cita($paciente, '08:00', '08:30', 'PROGRAMADA', $otroMedico);

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, ['hora_inicio' => '08:00']))
            ->assertSessionHas('error', 'El paciente ya tiene otra cita que se superpone con ese horario: elija otra hora.');

        $this->assertSame(1, Cita::count());
    }

    public function test_rn04_y_rn03_rechaza_medico_especialidad_o_paciente_inactivos(): void
    {
        $paciente = $this->crearPaciente();
        $this->actingAs($this->recepcionista());

        $this->medico->update(['estado' => false]);
        $this->post(route('citas.store'), $this->datos($paciente))->assertSessionHas('error');
        $this->medico->update(['estado' => true]);

        $this->especialidad->update(['estado' => false]);
        $this->post(route('citas.store'), $this->datos($paciente))->assertSessionHas('error');
        $this->especialidad->update(['estado' => true]);

        $paciente->update(['estado' => false]);
        $this->post(route('citas.store'), $this->datos($paciente))->assertSessionHas('error');

        $this->assertSame(0, Cita::count());
    }

    public function test_rn03_el_medico_debe_ejercer_la_especialidad(): void
    {
        $paciente = $this->crearPaciente();
        $otra = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 20]);

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, ['especialidad_id' => $otra->id]))
            ->assertSessionHas('error');

        $this->assertSame(0, Cita::count());
    }

    public function test_rn03_rechaza_un_paciente_un_medico_o_una_especialidad_que_no_existen(): void
    {
        $paciente = $this->crearPaciente();
        $this->actingAs($this->recepcionista());

        foreach (['paciente_id', 'medico_id', 'especialidad_id'] as $campo) {
            $this->post(route('citas.store'), $this->datos($paciente, [$campo => 999999]))
                ->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, Cita::count());
    }

    public function test_rn16_un_medico_no_puede_agendarse_consigo_mismo(): void
    {
        $fichaPaciente = Paciente::create(['persona_id' => $this->medico->persona_id, 'verificado' => true])->refresh();

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($fichaPaciente))
            ->assertSessionHas('error');

        $this->assertSame(0, Cita::count());
    }

    public function test_rn08_la_hora_final_la_calcula_el_servidor(): void
    {
        $paciente = $this->crearPaciente();

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, ['hora_fin' => '12:00']))
            ->assertSessionMissing('error');

        $this->assertSame('08:30', Cita::firstOrFail()->hasta());
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        $this->actingAs($this->recepcionista());

        $this->post(route('citas.store'), [])->assertSessionHasErrors(
            ['paciente_id', 'especialidad_id', 'medico_id', 'fecha', 'hora_inicio', 'modalidad']
        );

        $paciente = $this->crearPaciente();
        $this->post(route('citas.store'), $this->datos($paciente, ['fecha' => '16/11/2026', 'hora_inicio' => '8', 'modalidad' => 'OTRA', 'motivo' => str_repeat('a', 501)]))
            ->assertSessionHasErrors(['fecha', 'hora_inicio', 'modalidad', 'motivo']);

        $this->assertSame(0, Cita::count());
    }

    public function test_la_modalidad_virtual_y_el_motivo_son_opcionales(): void
    {
        $paciente = $this->crearPaciente();

        $this->actingAs($this->recepcionista())
            ->post(route('citas.store'), $this->datos($paciente, ['modalidad' => 'VIRTUAL', 'motivo' => null]));

        $cita = Cita::firstOrFail();
        $this->assertSame('VIRTUAL', $cita->modalidad);
        $this->assertNull($cita->motivo);
    }

    // --- El paciente agenda para sí mismo (RN12) ---------------------------------------

    public function test_el_paciente_reserva_para_si_mismo_aunque_envie_otro_paciente(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $ajeno = $this->crearPaciente();

        $this->actingAs($cuenta)
            ->post(route('mis-citas.store'), $this->datos($ajeno))
            ->assertSessionMissing('error');

        $cita = Cita::firstOrFail();
        $this->assertSame($ficha->id, $cita->paciente_id);
        $this->assertSame(0, Cita::where('paciente_id', $ajeno->id)->count());
        $this->get(route('mis-citas.comprobante', $cita))->assertOk()->assertSee($cita->codigo);
    }

    public function test_un_paciente_con_la_ficha_pendiente_puede_reservar(): void
    {
        [$cuenta] = $this->cuentaPaciente(verificado: false);

        $this->actingAs($cuenta)
            ->get(route('mis-citas.nueva', [
                'especialidad' => $this->especialidad->id, 'medico' => $this->medico->id, 'fecha' => self::LUNES,
            ]))
            ->assertOk()
            ->assertSee('value="08:00"', false);

        $this->post(route('mis-citas.store'), $this->datos(Paciente::firstOrFail()))->assertSessionMissing('error');
        $this->assertSame(1, Cita::count());
    }

    public function test_una_cuenta_de_paciente_sin_ficha_recibe_404(): void
    {
        $cuenta = $this->crearCuenta('PACIENTE');
        $cita = $this->cita($this->crearPaciente(), '08:00', '08:30');

        $this->actingAs($cuenta);
        $this->get(route('mis-citas.nueva'))->assertNotFound();
        $this->post(route('mis-citas.store'), $this->datos($this->crearPaciente()))->assertNotFound();
        $this->get(route('mis-citas.comprobante', $cita))->assertNotFound();
    }

    public function test_el_paciente_solo_ve_el_comprobante_de_sus_propias_citas(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $propia = $this->cita($ficha, '08:00', '08:30');
        $ajena = $this->cita($this->crearPaciente(), '09:00', '09:30');

        $this->actingAs($cuenta);
        $this->get(route('mis-citas.comprobante', $propia))->assertOk();
        $this->get(route('mis-citas.comprobante', $ajena))->assertNotFound();

        // La recepción ve cualquiera.
        $this->actingAs($this->recepcionista());
        $this->get(route('citas.comprobante', $propia))->assertOk();
        $this->get(route('citas.comprobante', $ajena))->assertOk();
    }

    // --- Permisos y menú (RN12) ----------------------------------------------------------

    public function test_cada_rol_solo_entra_a_su_propio_grupo_de_rutas(): void
    {
        $cita = $this->cita($this->crearPaciente(), '08:00', '08:30');
        $datos = $this->datos($this->crearPaciente());

        foreach (['ADMINISTRADOR', 'MEDICO'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->get(route('citas.nueva'))->assertForbidden();
            $this->post(route('citas.store'), $datos)->assertForbidden();
            $this->get(route('citas.comprobante', $cita))->assertForbidden();
            $this->get(route('mis-citas.nueva'))->assertForbidden();
            $this->post(route('mis-citas.store'), $datos)->assertForbidden();
        }

        $this->actingAs($this->recepcionista());
        $this->get(route('mis-citas.nueva'))->assertForbidden();
        $this->post(route('mis-citas.store'), $datos)->assertForbidden();

        [$cuenta] = $this->cuentaPaciente();
        $this->actingAs($cuenta);
        $this->get(route('citas.nueva'))->assertForbidden();
        $this->post(route('citas.store'), $datos)->assertForbidden();
        $this->get(route('citas.comprobante', $cita))->assertForbidden();

        $this->assertSame(1, Cita::count());
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('citas.nueva'))->assertRedirect(route('login'));
        $this->get(route('mis-citas.nueva'))->assertRedirect(route('login'));
    }

    public function test_el_menu_enlaza_a_la_pantalla_de_cada_rol(): void
    {
        $this->actingAs($this->recepcionista())->get(route('inicio'))->assertSee(route('citas.nueva'), false);

        [$cuenta] = $this->cuentaPaciente();
        $this->actingAs($cuenta)->get(route('inicio'))->assertSee(route('mis-citas.nueva'), false);
    }

    public function test_la_recepcion_busca_al_paciente_por_ci_o_nombre(): void
    {
        $paciente = $this->crearPaciente(['primer_nombre' => 'Zulema', 'apellido_paterno' => 'Quiroga']);
        $inactivo = $this->crearPaciente(['primer_nombre' => 'Zulema', 'apellido_paterno' => 'Inactiva'], ['estado' => false]);
        $this->actingAs($this->recepcionista());

        $this->get(route('citas.nueva', ['q' => $paciente->persona->ci]))
            ->assertOk()->assertSee('Zulema')->assertSee(route('citas.nueva', ['paciente' => $paciente->id]), false);

        $this->get(route('citas.nueva', ['q' => 'quiroga']))->assertOk()->assertSee($paciente->codigo);

        // Un paciente desactivado no se ofrece: se comprueba su enlace, porque su código podría
        // coincidir con el ejemplo del buscador (PAC-000042).
        $this->get(route('citas.nueva', ['q' => 'zulema']))
            ->assertSee(route('citas.nueva', ['paciente' => $paciente->id]), false)
            ->assertDontSee(route('citas.nueva', ['paciente' => $inactivo->id]), false);
    }

    // --- Concurrencia: la base de datos es la última defensa (RN07, RN17) --------------------

    public function test_si_otra_solicitud_ocupa_al_medico_antes_de_guardar_la_base_lo_rechaza(): void
    {
        $paciente = $this->crearPaciente();
        $otro = $this->crearPaciente();
        $this->simularCarrera($otro->id, $this->medico->id);

        try {
            app(CitaService::class)->registrar(
                $paciente, $this->medico, $this->especialidad,
                ['fecha' => self::LUNES, 'hora_inicio' => '08:00'], $this->recepcionista()
            );
            $this->fail('Debía rechazarse la cita.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertStringContainsString('El médico ya tiene una cita', $e->getMessage());
        }

        // La cita «ganadora» se insertó dentro de la misma transacción, que se deshizo: no queda nada.
        $this->assertSame(0, Cita::count());
        $this->assertSame(0, Bitacora::where('accion', 'cita_creada')->count());
    }

    public function test_si_otra_solicitud_ocupa_al_paciente_antes_de_guardar_la_base_lo_rechaza(): void
    {
        $paciente = $this->crearPaciente();
        $this->simularCarrera($paciente->id, $this->medicoConAgenda($this->especialidad)->id);

        try {
            app(CitaService::class)->registrar(
                $paciente, $this->medico, $this->especialidad,
                ['fecha' => self::LUNES, 'hora_inicio' => '08:00'], $this->recepcionista()
            );
            $this->fail('Debía rechazarse la cita.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertStringContainsString('El paciente ya tiene otra cita', $e->getMessage());
        }

        $this->assertSame(0, Cita::count());
    }

    public function test_la_base_rechaza_citas_superpuestas_del_mismo_paciente_aunque_se_salte_la_aplicacion(): void
    {
        $paciente = $this->crearPaciente();
        $otroMedico = $this->medicoConAgenda($this->especialidad);
        $this->cita($paciente, '08:00', '08:30');

        try {
            DB::transaction(fn () => $this->cita($paciente, '08:15', '08:45', 'PROGRAMADA', $otroMedico));
            $this->fail('La base debía rechazar la cita.');
        } catch (QueryException $e) {
            $this->assertSame('23P01', $e->errorInfo[0]);
            $this->assertStringContainsString('citas_paciente_sin_superposicion', $e->getMessage());
        }

        // Pegadas no se superponen, y una cancelada no cuenta.
        $this->cita($paciente, '08:30', '09:00', 'PROGRAMADA', $otroMedico);
        $this->cita($paciente, '08:00', '08:30', 'CANCELADA', $otroMedico);
        $this->assertSame(3, Cita::count());
    }

    /**
     * Justo antes de insertar la cita, otra «solicitud» inserta una cita en el mismo horario
     * (08:00 del lunes) para el paciente y el médico indicados.
     */
    private function simularCarrera(int $pacienteId, int $medicoId): void
    {
        $hecho = false;

        Cita::creating(function () use ($pacienteId, $medicoId, &$hecho) {
            if ($hecho) {
                return;
            }
            $hecho = true;

            DB::table('citas')->insert([
                'paciente_id' => $pacienteId,
                'medico_id' => $medicoId,
                'especialidad_id' => $this->especialidad->id,
                'sucursal_id' => Sucursal::firstOrFail()->id,
                'fecha' => self::LUNES,
                'hora_inicio' => '08:00',
                'hora_fin' => '08:30',
            ]);
        });
    }
}
