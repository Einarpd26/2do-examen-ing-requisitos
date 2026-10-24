<?php

namespace Tests\Feature\Pacientes;

use App\Models\Bitacora;
use App\Models\Paciente;
use App\Models\Persona;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-04: gestionar pacientes (RF02, RF14, RN01, RN11).
 */
class PacienteTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private function recepcionista(): \App\Models\Usuario
    {
        return $this->crearCuenta('RECEPCIONISTA');
    }

    // --- Permisos -----------------------------------------------------------------------

    public function test_solo_la_recepcionista_gestiona_pacientes(): void
    {
        $paciente = $this->crearPaciente();

        foreach (['ADMINISTRADOR', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol))
                ->get(route('pacientes.index'))->assertForbidden();
            $this->get(route('pacientes.show', $paciente))->assertForbidden();
            $this->post(route('pacientes.store'), $this->datosPaciente())->assertForbidden();
        }

        $this->actingAs($this->recepcionista())->get(route('pacientes.index'))->assertOk();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('pacientes.index'))->assertRedirect(route('login'));
    }

    // --- Listado y búsqueda ---------------------------------------------------------------

    public function test_el_listado_muestra_los_pacientes_con_su_codigo(): void
    {
        $paciente = $this->crearPaciente(['primer_nombre' => 'Luis', 'apellido_paterno' => 'Rojas']);

        $this->actingAs($this->recepcionista())
            ->get(route('pacientes.index'))
            ->assertOk()
            ->assertSee('Luis Rojas')
            ->assertSee($paciente->codigo);
    }

    public function test_busca_por_ci_nombre_apellido_y_codigo(): void
    {
        $ana = $this->crearPaciente(['ci' => '5551111', 'primer_nombre' => 'Ana', 'apellido_paterno' => 'Quispe']);
        $this->crearPaciente(['ci' => '6662222', 'primer_nombre' => 'Bruno', 'apellido_paterno' => 'Mamani']);
        $this->actingAs($this->recepcionista());

        $this->get(route('pacientes.index', ['q' => '5551']))->assertSee('Ana Quispe')->assertDontSee('Bruno Mamani');
        $this->get(route('pacientes.index', ['q' => 'bruno']))->assertSee('Bruno Mamani')->assertDontSee('Ana Quispe');
        $this->get(route('pacientes.index', ['q' => 'quispe']))->assertSee('Ana Quispe')->assertDontSee('Bruno Mamani');
        $this->get(route('pacientes.index', ['q' => $ana->codigo]))->assertSee('Ana Quispe')->assertDontSee('Bruno Mamani');
    }

    public function test_los_comodines_de_la_busqueda_se_toman_como_texto(): void
    {
        $this->crearPaciente(['primer_nombre' => 'Ana']);

        $this->actingAs($this->recepcionista())
            ->get(route('pacientes.index', ['q' => '%']))
            ->assertOk()
            ->assertDontSee('Ana Paz')
            ->assertSee('No hay pacientes que coincidan');
    }

    // --- Registrar -----------------------------------------------------------------------

    public function test_registra_un_paciente_verificado_con_su_persona_y_su_ficha(): void
    {
        $recepcionista = $this->recepcionista();
        $datos = $this->datosPaciente(['ci' => '4567890']);

        $respuesta = $this->actingAs($recepcionista)->post(route('pacientes.store'), $datos);

        $paciente = Paciente::with('persona')->firstOrFail();
        $respuesta->assertRedirect(route('pacientes.show', $paciente));

        $this->assertSame('4567890', $paciente->persona->ci);
        $this->assertSame('María Elena López Vega', $paciente->persona->nombre_completo);
        $this->assertSame('O+', $paciente->tipo_sangre);
        $this->assertSame('Carlos López', $paciente->emergencia_nombre);
        $this->assertTrue($paciente->verificado);
        $this->assertTrue($paciente->estado);
        $this->assertNull($paciente->persona->usuario);

        $this->assertSame(1, Bitacora::where('usuario_id', $recepcionista->id)->where('accion', 'paciente_creado')->count());
    }

    public function test_acepta_solo_los_datos_obligatorios(): void
    {
        $this->actingAs($this->recepcionista())->post(route('pacientes.store'), [
            'ci' => '1230001',
            'primer_nombre' => 'Eva',
            'apellido_paterno' => 'Soto',
        ])->assertSessionDoesntHaveErrors();

        $this->assertSame('Eva Soto', Paciente::firstOrFail()->persona->nombre_completo);
    }

    public function test_normaliza_el_ci_el_correo_y_los_telefonos(): void
    {
        $this->actingAs($this->recepcionista())->post(route('pacientes.store'), $this->datosPaciente([
            'ci' => ' 7654321-1a ',
            'correo' => '  MARIA@Mail.COM ',
            'telefono' => '700 123-45',
            'emergencia_telefono' => '(591) 712-34567',
        ]))->assertSessionDoesntHaveErrors();

        $persona = Persona::where('ci', '7654321-1A')->firstOrFail();
        $this->assertSame('7654321-1A', $persona->ci);
        $this->assertSame('maria@mail.com', $persona->correo);
        $this->assertSame('70012345', $persona->telefono);
        $this->assertSame('59171234567', Paciente::firstOrFail()->emergencia_telefono);
    }

    public function test_rechaza_un_ci_que_ya_tiene_ficha_de_paciente(): void
    {
        $existente = $this->crearPaciente(['ci' => '9990001']);
        $this->actingAs($this->recepcionista());

        $this->post(route('pacientes.store'), $this->datosPaciente(['ci' => '9990001']))
            ->assertSessionHasErrors('ci');

        $this->assertSame(1, Paciente::count());
        $this->assertSame($existente->id, Paciente::firstOrFail()->id);
    }

    public function test_reutiliza_la_persona_que_existe_sin_ficha_de_paciente_y_no_toca_sus_datos(): void
    {
        $medico = $this->crearCuenta('MEDICO', ['ci' => '8880001', 'primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna']);

        $respuesta = $this->actingAs($this->recepcionista())->post(route('pacientes.store'), $this->datosPaciente([
            'ci' => '8880001',
            'primer_nombre' => 'Nombre distinto',
            'correo' => $medico->persona->correo,
        ]));

        $paciente = Paciente::with('persona')->firstOrFail();
        $respuesta->assertRedirect(route('pacientes.show', $paciente));
        $respuesta->assertSessionHas('exito');

        $this->assertSame($medico->persona_id, $paciente->persona_id);
        $this->assertSame(1, Persona::where('ci', '8880001')->count());
        $this->assertSame('Rosa Luna', $paciente->persona->fresh()->nombre_completo);
        $this->assertTrue($paciente->verificado);
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        $this->crearCuenta('MEDICO', ['correo' => 'ocupado@clinica.test']);
        $this->actingAs($this->recepcionista());

        $casos = [
            'sin nombre' => [['primer_nombre' => ''], 'primer_nombre'],
            'sin apellido paterno' => [['apellido_paterno' => ''], 'apellido_paterno'],
            'sin ci' => [['ci' => ''], 'ci'],
            'ci con letras' => [['ci' => 'ABC123'], 'ci'],
            'ci muy corto' => [['ci' => '123'], 'ci'],
            'telefono invalido' => [['telefono' => '12'], 'telefono'],
            'telefono de emergencia invalido' => [['emergencia_telefono' => 'abc'], 'emergencia_telefono'],
            'tipo de sangre invalido' => [['tipo_sangre' => 'Z+'], 'tipo_sangre'],
            'sexo invalido' => [['sexo' => 'X'], 'sexo'],
            'fecha futura' => [['fecha_nacimiento' => now()->addDay()->format('Y-m-d')], 'fecha_nacimiento'],
            'correo mal escrito' => [['correo' => 'no-es-correo'], 'correo'],
            'correo de otra persona, con otras mayusculas' => [['correo' => 'OCUPADO@clinica.test'], 'correo'],
        ];

        foreach ($casos as [$reemplazo, $campo]) {
            $this->post(route('pacientes.store'), $this->datosPaciente($reemplazo))
                ->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, Paciente::count());
    }

    public function test_los_mensajes_de_error_salen_en_espanol(): void
    {
        $this->actingAs($this->recepcionista())
            ->post(route('pacientes.store'), $this->datosPaciente(['primer_nombre' => '']));

        $this->assertSame('El campo primer nombre es obligatorio.', $this->mensajeDeError('primer_nombre'));
    }

    // --- Modificar -----------------------------------------------------------------------

    public function test_modifica_los_datos_personales_y_la_ficha(): void
    {
        $recepcionista = $this->recepcionista();
        $paciente = $this->crearPaciente(['ci' => '3330001']);

        $this->actingAs($recepcionista)
            ->put(route('pacientes.update', $paciente), $this->datosPaciente([
                'ci' => '3330001',
                'primer_nombre' => 'Lucía',
                'tipo_sangre' => 'A-',
                'alergias' => 'Polen',
            ]))
            ->assertRedirect(route('pacientes.show', $paciente));

        $paciente->refresh()->load('persona');
        $this->assertSame('Lucía', $paciente->persona->primer_nombre);
        $this->assertSame('A-', $paciente->tipo_sangre);
        $this->assertSame('Polen', $paciente->alergias);
        $this->assertSame(1, Bitacora::where('usuario_id', $recepcionista->id)->where('accion', 'paciente_modificado')->count());
    }

    public function test_al_modificar_puede_conservar_su_propio_ci_y_correo_pero_no_el_de_otro(): void
    {
        $this->crearPaciente(['ci' => '3330001', 'correo' => 'otro@mail.com']);
        $paciente = $this->crearPaciente(['ci' => '3330002', 'correo' => 'propio@mail.com']);
        $this->actingAs($this->recepcionista());

        $this->put(route('pacientes.update', $paciente), $this->datosPaciente(['ci' => '3330002', 'correo' => 'propio@mail.com']))
            ->assertSessionDoesntHaveErrors();

        $this->put(route('pacientes.update', $paciente), $this->datosPaciente(['ci' => '3330001']))
            ->assertSessionHasErrors('ci');

        $this->put(route('pacientes.update', $paciente), $this->datosPaciente(['ci' => '3330002', 'correo' => 'OTRO@mail.com']))
            ->assertSessionHasErrors('correo');
    }

    public function test_se_pueden_vaciar_los_datos_opcionales(): void
    {
        $paciente = $this->crearPaciente(['ci' => '3330003'], ['alergias' => 'Polen', 'tipo_sangre' => 'A+']);

        $this->actingAs($this->recepcionista())->put(route('pacientes.update', $paciente), [
            'ci' => '3330003',
            'primer_nombre' => 'Ana',
            'apellido_paterno' => 'Paz',
            'alergias' => '',
            'tipo_sangre' => '',
        ])->assertSessionDoesntHaveErrors();

        $paciente->refresh();
        $this->assertNull($paciente->alergias);
        $this->assertNull($paciente->tipo_sangre);
    }

    // --- Desactivar y reactivar ----------------------------------------------------------

    public function test_desactivar_y_reactivar_siguen_a_la_cuenta_del_paciente(): void
    {
        $recepcionista = $this->recepcionista();
        $paciente = $this->crearPaciente(['ci' => '2220002']);
        $cuenta = \App\Models\Usuario::create([
            'persona_id' => $paciente->persona_id,
            'rol_id' => \App\Models\Rol::where('codigo', 'PACIENTE')->firstOrFail()->id,
            'password_hash' => self::CLAVE,
        ])->refresh();

        $this->actingAs($recepcionista)
            ->patch(route('pacientes.estado', $paciente), ['activo' => 0])
            ->assertRedirect(route('pacientes.show', $paciente));

        $this->assertFalse($paciente->fresh()->estado);
        $this->assertFalse($cuenta->fresh()->estado);
        $this->assertSame(1, Paciente::count(), 'desactivar no elimina al paciente');

        $this->patch(route('pacientes.estado', $paciente), ['activo' => 1]);

        $this->assertTrue($paciente->fresh()->estado);
        $this->assertTrue($cuenta->fresh()->estado);

        $acciones = Bitacora::where('usuario_id', $recepcionista->id)->orderBy('id')->pluck('accion')->all();
        $this->assertSame(['paciente_desactivado', 'paciente_reactivado'], $acciones);
    }

    public function test_desactivar_a_un_paciente_no_toca_la_cuenta_de_medico_de_la_misma_persona(): void
    {
        $medico = $this->crearCuenta('MEDICO', ['ci' => '2220003']);
        $paciente = Paciente::create(['persona_id' => $medico->persona_id, 'verificado' => true])->refresh();

        $this->actingAs($this->recepcionista())->patch(route('pacientes.estado', $paciente), ['activo' => 0]);

        $this->assertFalse($paciente->fresh()->estado);
        $this->assertTrue($medico->fresh()->estado, 'la cuenta de médico sigue activa');
    }

    // --- Ficha ---------------------------------------------------------------------------

    public function test_la_ficha_muestra_los_datos_y_el_estado(): void
    {
        $paciente = $this->crearPaciente(['ci' => '1110001', 'primer_nombre' => 'Rosa'], ['alergias' => 'Polen']);

        $this->actingAs($this->recepcionista())
            ->get(route('pacientes.show', $paciente))
            ->assertOk()
            ->assertSee('Rosa Paz')
            ->assertSee('1110001')
            ->assertSee('Polen')
            ->assertSee($paciente->codigo)
            ->assertSee('todavía no tiene cuenta')
            ->assertSee('Crear acceso');
    }

    public function test_la_ficha_de_una_persona_con_cuenta_de_medico_no_ofrece_crear_acceso(): void
    {
        $medico = $this->crearCuenta('MEDICO');
        $paciente = Paciente::create(['persona_id' => $medico->persona_id, 'verificado' => true])->refresh();

        $this->actingAs($this->recepcionista())
            ->get(route('pacientes.show', $paciente))
            ->assertOk()
            ->assertSee('cuenta con rol')
            ->assertDontSee('Crear acceso');
    }

    public function test_el_formulario_de_alta_y_el_de_edicion_se_muestran(): void
    {
        $paciente = $this->crearPaciente();
        $this->actingAs($this->recepcionista());

        $this->get(route('pacientes.create'))->assertOk()->assertSee('Registrar paciente');
        $this->get(route('pacientes.edit', $paciente))->assertOk()->assertSee('Guardar cambios');
    }
}
