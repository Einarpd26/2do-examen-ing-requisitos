<?php

namespace Tests\Feature\Acceso;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Bitacora;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\RegistroPacienteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-02: registro propio del paciente (RF14, RN01).
 */
class RegistroTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private const CLAVE_NUEVA = 'Mi-Clave-Segura-9';

    /** Datos válidos del formulario de registro, con un CI nuevo en cada llamada. */
    private function datosRegistro(array $reemplazo = []): array
    {
        $ci = (string) random_int(1000000, 9999999);

        return array_merge([
            'primer_nombre' => 'María',
            'apellido_paterno' => 'López',
            'ci' => $ci,
            'telefono' => '70012345',
            'password' => self::CLAVE_NUEVA,
            'password_confirmation' => self::CLAVE_NUEVA,
        ], $reemplazo);
    }

    public function test_el_formulario_de_registro_se_muestra_y_el_login_lo_enlaza(): void
    {
        $this->get(route('registro.formulario'))
            ->assertOk()
            ->assertSee('Crear mi cuenta')
            ->assertSee('pendiente de verificación');

        $this->get(route('login'))->assertSee(route('registro.formulario'), false);
    }

    public function test_quien_ya_inicio_sesion_no_ve_el_registro(): void
    {
        $this->actingAs($this->crearCuenta('MEDICO'));

        $this->get(route('registro.formulario'))->assertRedirect(route('inicio'));
        $this->post(route('registro.guardar'), $this->datosRegistro())->assertRedirect(route('inicio'));
    }

    public function test_crea_la_persona_la_ficha_pendiente_y_la_cuenta_de_paciente(): void
    {
        $datos = $this->datosRegistro(['ci' => '4567890']);

        $this->post(route('registro.guardar'), $datos)
            ->assertRedirect(route('login'))
            ->assertSessionHas('exito');

        $persona = Persona::where('ci', '4567890')->firstOrFail();
        $this->assertSame('María López', $persona->nombre_completo);
        $this->assertSame('70012345', $persona->telefono);

        $ficha = Paciente::where('persona_id', $persona->id)->firstOrFail();
        $this->assertFalse($ficha->verificado, 'la ficha queda pendiente de verificación');
        $this->assertTrue($ficha->estado);

        $cuenta = Usuario::with('rol')->where('persona_id', $persona->id)->firstOrFail();
        $this->assertSame('PACIENTE', $cuenta->rol->codigo);
        $this->assertTrue($cuenta->estado);
        $this->assertFalse($cuenta->debe_cambiar_password, 'la contraseña la eligió el paciente, no es temporal');
        $this->assertNull($cuenta->password_temporal_vence);
        $this->assertTrue(Hash::check(self::CLAVE_NUEVA, $cuenta->password_hash));
        $this->assertNotSame(self::CLAVE_NUEVA, $cuenta->password_hash);

        $this->assertSame(1, Bitacora::where('usuario_id', $cuenta->id)->where('accion', 'paciente_registro_propio')->count());
    }

    public function test_guarda_los_datos_opcionales_cuando_se_dan(): void
    {
        $this->post(route('registro.guardar'), $this->datosRegistro([
            'ci' => '1112223',
            'segundo_nombre' => 'Elena',
            'apellido_materno' => 'Vega',
            'correo' => '  MARIA@Mail.COM ',
        ]))->assertSessionDoesntHaveErrors();

        $persona = Persona::where('ci', '1112223')->firstOrFail();
        $this->assertSame('María Elena López Vega', $persona->nombre_completo);
        $this->assertSame('maria@mail.com', $persona->correo);
    }

    public function test_el_paciente_recien_registrado_puede_entrar_con_su_ci_aunque_su_ficha_siga_pendiente(): void
    {
        $this->post(route('registro.guardar'), $this->datosRegistro(['ci' => '2223334']));

        $this->post(route('login.autenticar'), $this->credenciales('2223334', self::CLAVE_NUEVA))
            ->assertRedirect(route('inicio'));

        $this->get(route('inicio'))->assertOk();
    }

    public function test_normaliza_el_ci_y_el_celular(): void
    {
        $this->post(route('registro.guardar'), $this->datosRegistro([
            'ci' => ' 7654321-1a ',
            'telefono' => '700 123-45',
        ]))->assertSessionDoesntHaveErrors();

        $persona = Persona::where('ci', '7654321-1A')->firstOrFail();
        $this->assertSame('70012345', $persona->telefono);
    }

    public function test_rechaza_un_ci_que_ya_esta_registrado_y_manda_a_recepcion(): void
    {
        $existente = $this->crearPaciente(['ci' => '9990001']);

        $this->post(route('registro.guardar'), $this->datosRegistro(['ci' => '9990001']))
            ->assertSessionHasErrors('ci');

        $this->assertStringContainsString('pida su acceso en recepción', $this->mensajeDeError('ci'));
        $this->assertSame(1, Persona::where('ci', '9990001')->count());
        $this->assertSame(0, Usuario::where('persona_id', $existente->persona_id)->count(), 'no se le crea cuenta a una ficha ajena');
    }

    public function test_rechaza_un_ci_que_pertenece_a_un_medico_o_al_personal(): void
    {
        $medico = $this->crearCuenta('MEDICO', ['ci' => '8880001']);

        $this->post(route('registro.guardar'), $this->datosRegistro(['ci' => '8880001']))
            ->assertSessionHasErrors('ci');

        $this->assertSame(1, Usuario::where('persona_id', $medico->persona_id)->count());
        $this->assertSame(0, Paciente::count());
    }

    public function test_rechaza_un_correo_ya_registrado_sin_distinguir_mayusculas(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['correo' => 'ocupado@clinica.test']);

        $this->post(route('registro.guardar'), $this->datosRegistro(['correo' => 'OCUPADO@clinica.test']))
            ->assertSessionHasErrors('correo');

        $this->assertSame(0, Paciente::count());
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        // Esta prueba envía más de 10 solicitudes seguidas; el límite se prueba aparte.
        $this->withoutMiddleware(ThrottleRequests::class);

        $casos = [
            'sin nombre' => [['primer_nombre' => ''], 'primer_nombre'],
            'sin apellido paterno' => [['apellido_paterno' => ''], 'apellido_paterno'],
            'sin ci' => [['ci' => ''], 'ci'],
            'ci con letras' => [['ci' => 'ABC123'], 'ci'],
            'ci muy corto' => [['ci' => '123'], 'ci'],
            'sin celular' => [['telefono' => ''], 'telefono'],
            'celular invalido' => [['telefono' => '12'], 'telefono'],
            'correo mal escrito' => [['correo' => 'no-es-correo'], 'correo'],
            'sin contraseña' => [['password' => '', 'password_confirmation' => ''], 'password'],
            'contraseña corta' => [['password' => 'Ab1', 'password_confirmation' => 'Ab1'], 'password'],
            'contraseña sin mayuscula' => [['password' => 'sinmayuscula1', 'password_confirmation' => 'sinmayuscula1'], 'password'],
            'contraseña sin minuscula' => [['password' => 'SINMINUSCULA1', 'password_confirmation' => 'SINMINUSCULA1'], 'password'],
            'contraseña sin numero' => [['password' => 'SinNumeroAqui', 'password_confirmation' => 'SinNumeroAqui'], 'password'],
            'confirmacion distinta' => [['password_confirmation' => 'Otra-Clave-9'], 'password'],
        ];

        foreach ($casos as [$reemplazo, $campo]) {
            $this->post(route('registro.guardar'), $this->datosRegistro($reemplazo))->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, Paciente::count());
        $this->assertSame(0, Usuario::count());
    }

    public function test_los_mensajes_salen_en_espanol_con_el_nombre_del_campo_del_formulario(): void
    {
        $this->post(route('registro.guardar'), $this->datosRegistro(['telefono' => '']));
        $this->assertSame('Ingrese su número de celular.', $this->mensajeDeError('telefono'));

        $this->post(route('registro.guardar'), $this->datosRegistro(['password' => '', 'password_confirmation' => '']));
        $this->assertSame('El campo contraseña es obligatorio.', $this->mensajeDeError('password'));
    }

    public function test_si_el_registro_falla_la_contraseña_no_vuelve_al_formulario(): void
    {
        $this->post(route('registro.guardar'), $this->datosRegistro(['primer_nombre' => '']));

        $this->get(route('registro.formulario'))
            ->assertDontSee(self::CLAVE_NUEVA)
            ->assertSee('value="López"', false);
    }

    public function test_no_deja_filas_a_medias_si_algo_falla(): void
    {
        // La persona se crea primero; si el CI ya existe la transacción se revierte entera.
        $this->crearPaciente(['ci' => '5556667']);
        $personasAntes = Persona::count();

        try {
            app(RegistroPacienteService::class)->registrar($this->datosRegistro(['ci' => '5556667']));
            $this->fail('Debió rechazar el CI repetido.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertStringContainsString('ya está registrado', $e->getMessage());
        }

        $this->assertSame($personasAntes, Persona::count());
        $this->assertSame(0, Usuario::count());
    }

    public function test_el_registro_publico_limita_las_solicitudes_por_minuto(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->post(route('registro.guardar'), []);
        }

        $this->post(route('registro.guardar'), $this->datosRegistro())->assertStatus(429);
        $this->assertSame(0, Paciente::count());
    }

    public function test_la_ficha_pendiente_aparece_para_que_la_recepcion_la_verifique(): void
    {
        $this->post(route('registro.guardar'), $this->datosRegistro(['ci' => '3334445', 'primer_nombre' => 'Rosa']));
        $this->post(route('logout'));

        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))
            ->get(route('pacientes.index', ['q' => '3334445']))
            ->assertSee('Rosa López')
            ->assertSee('Pendiente');

        $ficha = Paciente::firstOrFail();
        $this->post(route('pacientes.verificar', $ficha))->assertSessionHas('exito');
        $this->assertTrue($ficha->fresh()->verificado);
    }
}
