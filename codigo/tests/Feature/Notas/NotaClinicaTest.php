<?php

namespace Tests\Feature\Notas;

use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\NotaClinica;
use App\Models\Paciente;
use App\Models\Sucursal;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-16 y CU-17: notas clínicas (RF16, RN10, RN12). El «ahora» es el lunes 09/11/2026 a las 09:10.
 */
class NotaClinicaTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private Especialidad $especialidad;

    private Usuario $cuentaMedico;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:10:00', 'America/La_Paz'));

        $this->especialidad = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 30])->refresh();
        [$this->cuentaMedico, $this->medico] = $this->nuevoMedico();
    }

    protected function tearDown(): void
    {
        Cita::flushEventListeners();

        parent::tearDown();
    }

    /** @return array{0: Usuario, 1: Medico} */
    private function nuevoMedico(): array
    {
        $cuenta = $this->crearCuenta('MEDICO');
        $ficha = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-'.$cuenta->id])->refresh();
        $ficha->especialidades()->sync([$this->especialidad->id]);

        return [$cuenta, $ficha];
    }

    private function cita(Paciente $paciente, ?Medico $medico = null, string $estado = 'ATENDIDA', string $inicio = '08:00', string $fecha = '2026-11-09'): Cita
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

    private function datos(array $reemplazo = []): array
    {
        return array_merge([
            'motivo_consulta' => 'Fiebre de tres días',
            'diagnostico' => 'Faringitis aguda',
            'tratamiento' => 'Reposo e hidratación',
            'observaciones' => 'Control en una semana',
        ], $reemplazo);
    }

    private function guardar(Usuario $actor, Cita $cita, array $datos)
    {
        return $this->actingAs($actor)->from('/volver')->put(route('notas.guardar', $cita), $datos);
    }

    /** @return array{0: Usuario, 1: Paciente} */
    private function cuentaPaciente(bool $verificado = true): array
    {
        $cuenta = $this->crearCuenta('PACIENTE');

        return [$cuenta, Paciente::create(['persona_id' => $cuenta->persona_id, 'verificado' => $verificado])->refresh()];
    }

    // --- CU-16: registrar y editar -----------------------------------------------------------

    public function test_el_medico_registra_la_nota_de_una_cita_atendida(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->guardar($this->cuentaMedico, $cita, $this->datos())
            ->assertRedirect(route('agenda.index', ['fecha' => '2026-11-09']))
            ->assertSessionHas('exito');

        $nota = NotaClinica::firstOrFail();
        $this->assertSame($cita->id, $nota->cita_id);
        $this->assertSame($this->medico->id, $nota->medico_id);
        $this->assertSame('Faringitis aguda', $nota->diagnostico);
        $this->assertSame($this->cuentaMedico->id, $nota->modificado_por);
        $this->assertSame(1, Bitacora::where('accion', 'nota_registrada')->count());
    }

    public function test_editar_sobrescribe_la_nota_y_guarda_quien_y_cuando(): void
    {
        $cita = $this->cita($this->crearPaciente());
        $this->guardar($this->cuentaMedico, $cita, $this->datos());

        $this->travelTo(CarbonImmutable::parse('2026-11-09 15:30:00', 'America/La_Paz'));
        $this->guardar($this->cuentaMedico, $cita, $this->datos(['diagnostico' => 'Amigdalitis']))->assertSessionHas('exito');

        // Una sola nota por cita, sin versiones: se sobrescribe.
        $this->assertSame(1, NotaClinica::count());
        $nota = NotaClinica::firstOrFail();
        $this->assertSame('Amigdalitis', $nota->diagnostico);
        $this->assertSame($this->cuentaMedico->id, $nota->modificado_por);
        $this->assertSame('2026-11-09 15:30', $nota->updated_at->timezone('America/La_Paz')->format('Y-m-d H:i'));
        $this->assertSame(1, Bitacora::where('accion', 'nota_modificada')->count());
    }

    public function test_el_formulario_se_carga_con_la_nota_existente(): void
    {
        $cita = $this->cita($this->crearPaciente());
        $this->guardar($this->cuentaMedico, $cita, $this->datos());

        $this->actingAs($this->cuentaMedico)->get(route('notas.formulario', $cita))
            ->assertOk()->assertSee('Faringitis aguda')->assertSee('Guardar cambios');
    }

    public function test_solo_se_registra_la_nota_de_una_cita_atendida(): void
    {
        foreach (['PROGRAMADA' => '08:00', 'CONFIRMADA' => '08:30', 'CANCELADA' => '09:00', 'INASISTENCIA' => '09:30'] as $estado => $hora) {
            $cita = $this->cita($this->crearPaciente(), null, $estado, $hora);

            $this->guardar($this->cuentaMedico, $cita, $this->datos())
                ->assertSessionHas('error', 'Solo se registra la nota clínica de una cita atendida.');
            $this->actingAs($this->cuentaMedico)->from('/volver')->get(route('notas.formulario', $cita))->assertSessionHas('error');
        }

        $this->assertSame(0, NotaClinica::count());
    }

    public function test_exige_al_menos_un_campo_y_limita_el_largo(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->guardar($this->cuentaMedico, $cita, [])->assertSessionHasErrors('motivo_consulta');
        $this->guardar($this->cuentaMedico, $cita, ['diagnostico' => '   '])->assertSessionHasErrors('motivo_consulta');
        $this->guardar($this->cuentaMedico, $cita, ['diagnostico' => str_repeat('a', 2001)])->assertSessionHasErrors('diagnostico');

        $this->assertSame(0, NotaClinica::count());
    }

    public function test_basta_con_un_campo(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->guardar($this->cuentaMedico, $cita, ['diagnostico' => 'Sano'])->assertSessionHas('exito');

        $nota = NotaClinica::firstOrFail();
        $this->assertSame('Sano', $nota->diagnostico);
        $this->assertNull($nota->tratamiento);
    }

    public function test_el_medico_no_escribe_ni_edita_la_nota_de_otro_medico(): void
    {
        [$otraCuenta] = $this->nuevoMedico();
        $cita = $this->cita($this->crearPaciente());
        $this->guardar($this->cuentaMedico, $cita, $this->datos());

        $this->guardar($otraCuenta, $cita, $this->datos(['diagnostico' => 'Cambiado']))->assertNotFound();
        $this->actingAs($otraCuenta)->get(route('notas.formulario', $cita))->assertNotFound();

        $this->assertSame('Faringitis aguda', NotaClinica::firstOrFail()->diagnostico);
    }

    public function test_el_formulario_no_acepta_autor_ni_cita(): void
    {
        $cita = $this->cita($this->crearPaciente());
        $otra = $this->cita($this->crearPaciente(), null, 'ATENDIDA', '09:00');

        $this->guardar($this->cuentaMedico, $cita, $this->datos(['cita_id' => $otra->id, 'medico_id' => 999, 'modificado_por' => 999]));

        $nota = NotaClinica::firstOrFail();
        $this->assertSame($cita->id, $nota->cita_id);
        $this->assertSame($this->medico->id, $nota->medico_id);
        $this->assertSame($this->cuentaMedico->id, $nota->modificado_por);
    }

    public function test_solo_el_medico_registra_notas(): void
    {
        $cita = $this->cita($this->crearPaciente());
        [$cuentaPaciente] = $this->cuentaPaciente();

        foreach (['RECEPCIONISTA', 'ADMINISTRADOR'] as $rol) {
            $this->guardar($this->crearCuenta($rol), $cita, $this->datos())->assertForbidden();
            $this->actingAs($this->crearCuenta($rol))->get(route('notas.formulario', $cita))->assertForbidden();
        }
        $this->guardar($cuentaPaciente, $cita, $this->datos())->assertForbidden();

        $this->assertSame(0, NotaClinica::count());
    }

    public function test_la_agenda_ofrece_la_nota_solo_en_citas_atendidas_del_medico(): void
    {
        $atendida = $this->cita($this->crearPaciente());
        $this->cita($this->crearPaciente(), null, 'PROGRAMADA', '09:00');

        $respuesta = $this->actingAs($this->cuentaMedico)->get(route('agenda.index', ['fecha' => '2026-11-09']));
        $respuesta->assertSee(route('notas.formulario', $atendida), false);
        $this->assertSame(1, substr_count($respuesta->getContent(), '>Nota clínica</a>'));
    }

    // --- CU-17: consultar -----------------------------------------------------------------------

    public function test_la_recepcionista_ve_las_notas_de_un_paciente_en_solo_lectura(): void
    {
        $paciente = $this->crearPaciente();
        $cita = $this->cita($paciente);
        $this->guardar($this->cuentaMedico, $cita, $this->datos());

        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('pacientes.notas', $paciente))
            ->assertOk()
            ->assertSee('Faringitis aguda')
            ->assertSee('Reposo e hidratación')
            ->assertSee($cita->codigo)
            ->assertSee('Pediatría')
            ->assertSee('Última modificación')
            ->assertDontSee(route('notas.formulario', $cita), false);
    }

    public function test_el_medico_ve_las_notas_de_sus_pacientes_incluidas_las_de_otros_medicos(): void
    {
        [$otraCuenta, $otro] = $this->nuevoMedico();
        $paciente = $this->crearPaciente();
        $mia = $this->cita($paciente, null, 'ATENDIDA', '08:00');
        $ajena = $this->cita($paciente, $otro, 'ATENDIDA', '09:00', '2026-11-02');
        $this->guardar($this->cuentaMedico, $mia, $this->datos());
        $this->guardar($otraCuenta, $ajena, $this->datos(['diagnostico' => 'Diagnóstico del otro médico']));

        $this->actingAs($this->cuentaMedico)->get(route('pacientes.notas', $paciente))
            ->assertOk()
            ->assertSee('Faringitis aguda')
            ->assertSee('Diagnóstico del otro médico')
            // Solo puede editar las suyas.
            ->assertSee(route('notas.formulario', $mia), false)
            ->assertDontSee(route('notas.formulario', $ajena), false);
    }

    public function test_el_medico_no_ve_pacientes_que_no_atiende(): void
    {
        $this->actingAs($this->cuentaMedico)->get(route('pacientes.notas', $this->crearPaciente()))->assertNotFound();

        // Una cita cancelada no lo convierte en su médico.
        $paciente = $this->crearPaciente();
        $this->cita($paciente, null, 'CANCELADA');
        $this->actingAs($this->cuentaMedico)->get(route('pacientes.notas', $paciente))->assertNotFound();
    }

    public function test_las_notas_van_de_la_cita_mas_reciente_a_la_mas_antigua(): void
    {
        $paciente = $this->crearPaciente();
        $vieja = $this->cita($paciente, null, 'ATENDIDA', '08:00', '2026-10-01');
        $nueva = $this->cita($paciente, null, 'ATENDIDA', '08:00', '2026-11-05');
        $this->guardar($this->cuentaMedico, $vieja, $this->datos());
        $this->guardar($this->cuentaMedico, $nueva, $this->datos());

        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('pacientes.notas', $paciente))
            ->assertSeeInOrder([$nueva->codigo, $vieja->codigo]);
    }

    public function test_un_paciente_sin_notas_muestra_un_aviso(): void
    {
        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('pacientes.notas', $this->crearPaciente()))
            ->assertOk()->assertSee('todavía no tiene notas clínicas');
    }

    public function test_el_paciente_ve_solo_sus_notas(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente();
        $suya = $this->cita($ficha);
        $ajena = $this->cita($this->crearPaciente(), null, 'ATENDIDA', '09:00');
        $this->guardar($this->cuentaMedico, $suya, $this->datos(['diagnostico' => 'Mi diagnóstico']));
        $this->guardar($this->cuentaMedico, $ajena, $this->datos(['diagnostico' => 'Diagnóstico ajeno']));

        $this->actingAs($cuenta)->get(route('notas.propias'))
            ->assertOk()
            ->assertSee('Mi diagnóstico')
            ->assertDontSee('Diagnóstico ajeno')
            ->assertDontSee('Editar');
    }

    public function test_con_la_ficha_pendiente_el_paciente_no_ve_sus_notas(): void
    {
        [$cuenta, $ficha] = $this->cuentaPaciente(false);
        $this->guardar($this->cuentaMedico, $this->cita($ficha), $this->datos(['diagnostico' => 'Reservado']));

        $this->actingAs($cuenta)->get(route('notas.propias'))
            ->assertOk()
            ->assertSee('todavía debe verificar')
            ->assertDontSee('Reservado');
    }

    public function test_permisos_de_consulta(): void
    {
        $paciente = $this->crearPaciente();
        [$cuentaPaciente] = $this->cuentaPaciente();

        // El Paciente y el Administrador no abren las notas de un paciente por su ruta.
        $this->actingAs($cuentaPaciente)->get(route('pacientes.notas', $paciente))->assertForbidden();
        $this->actingAs($this->crearCuenta('ADMINISTRADOR'))->get(route('pacientes.notas', $paciente))->assertForbidden();
        $this->actingAs($this->crearCuenta('ADMINISTRADOR'))->get(route('notas.propias'))->assertForbidden();

        // Una cuenta sin ficha de paciente recibe 404 en /mis-notas.
        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('notas.propias'))->assertNotFound();
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $cita = $this->cita($this->crearPaciente());

        $this->get(route('notas.propias'))->assertRedirect(route('login'));
        $this->get(route('pacientes.notas', $cita->paciente_id))->assertRedirect(route('login'));
        $this->get(route('notas.formulario', $cita))->assertRedirect(route('login'));
        $this->put(route('notas.guardar', $cita), $this->datos())->assertRedirect(route('login'));
    }

    public function test_el_menu_y_la_ficha_enlazan_a_las_notas(): void
    {
        $paciente = $this->crearPaciente();
        [$cuenta] = $this->cuentaPaciente();

        $this->actingAs($cuenta)->get(route('inicio'))->assertSee('Mis notas');
        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('inicio'))->assertDontSee('Mis notas');
        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('pacientes.show', $paciente))
            ->assertSee(route('pacientes.notas', $paciente), false);
    }
}
