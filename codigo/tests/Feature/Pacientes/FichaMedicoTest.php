<?php

namespace Tests\Feature\Pacientes;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Sucursal;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-13: ficha de solo lectura del paciente para su médico (enunciado 3.3, RN12).
 */
class FichaMedicoTest extends TestCase
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

    private static int $secuencia = 9000000;

    /** El correo y la dirección existen para comprobar que la ficha del Médico no los muestra. */
    private function paciente(bool $verificado = true): Paciente
    {
        $persona = Persona::create([
            'ci' => (string) ++self::$secuencia, 'primer_nombre' => 'Lucía', 'apellido_paterno' => 'Vaca',
            'fecha_nacimiento' => '1990-05-20', 'sexo' => 'F', 'telefono' => '70011122',
            'correo' => 'lucia.privada'.self::$secuencia.'@correo.test', 'direccion' => 'Calle Reservada 123',
        ]);

        return Paciente::create([
            'persona_id' => $persona->id, 'verificado' => $verificado, 'tipo_sangre' => 'O+', 'alergias' => 'Penicilina',
            'enfermedades_cronicas' => 'Asma', 'medicamentos' => 'Salbutamol', 'antecedentes' => 'Apendicectomía',
            'emergencia_nombre' => 'Carlos Vaca', 'emergencia_parentesco' => 'Hermano', 'emergencia_telefono' => '70099988',
        ])->refresh()->load('persona');
    }

    private function cita(Paciente $paciente, string $estado = 'ATENDIDA', ?Medico $medico = null, string $fecha = '2026-11-02'): Cita
    {
        return Cita::create([
            'paciente_id' => $paciente->id, 'medico_id' => ($medico ?? $this->medico)->id,
            'especialidad_id' => $this->especialidad->id, 'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha, 'hora_inicio' => '08:00', 'hora_fin' => '08:30', 'estado' => $estado,
        ])->refresh();
    }

    public function test_el_medico_ve_los_datos_necesarios_de_su_paciente(): void
    {
        $paciente = $this->paciente();
        $cita = $this->cita($paciente);

        $this->actingAs($this->cuentaMedico)->get(route('pacientes.ficha', $paciente))
            ->assertOk()
            // Datos básicos y teléfono, con la edad.
            ->assertSee('Lucía')->assertSee($paciente->codigo)->assertSee($paciente->persona->ci)
            ->assertSee('20/05/1990')->assertSee('36 años')->assertSee('Femenino')->assertSee('70011122')
            // Información médica y contacto de emergencia.
            ->assertSee('O+')->assertSee('Penicilina')->assertSee('Asma')->assertSee('Salbutamol')->assertSee('Apendicectomía')
            ->assertSee('Carlos Vaca')->assertSee('Hermano')->assertSee('70099988')
            // Sus citas con este médico y el enlace a las notas.
            ->assertSee($cita->codigo)
            ->assertSee(route('pacientes.notas', $paciente), false);
    }

    public function test_no_muestra_el_correo_ni_la_direccion_ni_permite_editar(): void
    {
        $paciente = $this->paciente();
        $this->cita($paciente);

        $this->actingAs($this->cuentaMedico)->get(route('pacientes.ficha', $paciente))
            ->assertOk()
            ->assertDontSee('lucia.privada')
            ->assertDontSee('Calle Reservada')
            ->assertDontSee('Editar')
            ->assertDontSee(route('pacientes.edit', $paciente), false);
    }

    public function test_con_la_ficha_pendiente_oculta_la_informacion_medica_y_el_contacto_de_emergencia(): void
    {
        $paciente = $this->paciente(false);
        $this->cita($paciente);

        $this->actingAs($this->cuentaMedico)->get(route('pacientes.ficha', $paciente))
            ->assertOk()
            ->assertSee('todavía no fue verificada')
            ->assertSee('Lucía')
            ->assertDontSee('Penicilina')->assertDontSee('Salbutamol')->assertDontSee('Carlos Vaca')->assertDontSee('70099988');
    }

    public function test_solo_lista_las_citas_de_este_medico(): void
    {
        [, $otro] = $this->nuevoMedico();
        $paciente = $this->paciente();
        $mia = $this->cita($paciente);
        $deOtro = $this->cita($paciente, 'ATENDIDA', $otro, '2026-11-03');

        $this->actingAs($this->cuentaMedico)->get(route('pacientes.ficha', $paciente))
            ->assertSee($mia->codigo)->assertDontSee($deOtro->codigo);
    }

    public function test_el_medico_no_abre_la_ficha_de_un_paciente_que_no_atiende(): void
    {
        $this->actingAs($this->cuentaMedico)->get(route('pacientes.ficha', $this->paciente()))->assertNotFound();

        // Una cita cancelada no lo vuelve su médico, y la cita de otro médico tampoco.
        [, $otro] = $this->nuevoMedico();
        $cancelada = $this->paciente();
        $this->cita($cancelada, 'CANCELADA');
        $deOtro = $this->paciente();
        $this->cita($deOtro, 'ATENDIDA', $otro);

        $this->actingAs($this->cuentaMedico)->get(route('pacientes.ficha', $cancelada))->assertNotFound();
        $this->actingAs($this->cuentaMedico)->get(route('pacientes.ficha', $deOtro))->assertNotFound();
    }

    public function test_los_demas_roles_no_usan_esta_ficha(): void
    {
        $paciente = $this->paciente();
        $this->cita($paciente);

        $this->actingAs($this->crearCuenta('PACIENTE'))->get(route('pacientes.ficha', $paciente))->assertForbidden();
        $this->actingAs($this->crearCuenta('ADMINISTRADOR'))->get(route('pacientes.ficha', $paciente))->assertForbidden();
        // La Recepcionista tiene su propia ficha completa y no es médico de nadie.
        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('pacientes.ficha', $paciente))->assertNotFound();
    }

    public function test_sin_sesion_redirige_al_login_y_un_paciente_inexistente_da_404(): void
    {
        $this->get(route('pacientes.ficha', $this->paciente()))->assertRedirect(route('login'));
        $this->actingAs($this->cuentaMedico)->get('/pacientes/999999/ficha')->assertNotFound();
    }

    public function test_la_agenda_ofrece_la_ficha_solo_en_citas_no_canceladas(): void
    {
        $viva = $this->paciente();
        $cancelada = $this->paciente();
        $this->cita($viva, 'PROGRAMADA', null, '2026-11-12');
        $this->cita($cancelada, 'CANCELADA', null, '2026-11-12');

        $html = $this->actingAs($this->cuentaMedico)->get(route('agenda.index', ['fecha' => '2026-11-12']))->getContent();

        $this->assertStringContainsString(route('pacientes.ficha', $viva), $html);
        $this->assertStringNotContainsString(route('pacientes.ficha', $cancelada), $html);
        // El enlace roto de las notas tampoco se ofrece en la cita cancelada.
        $this->assertStringNotContainsString(route('pacientes.notas', $cancelada), $html);
    }
}
