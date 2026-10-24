<?php

namespace Tests\Feature\Pacientes;

use App\Models\Bitacora;
use App\Models\Paciente;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-04: cuenta del paciente con contraseña temporal, renovación y verificación de la ficha
 * (RNF18, RN01).
 */
class AccesoPacienteTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private function recepcionista(): Usuario
    {
        return $this->crearCuenta('RECEPCIONISTA');
    }

    /** Crea la cuenta de paciente de la ficha, con la clave de prueba. */
    private function darCuenta(Paciente $paciente, array $cuenta = []): Usuario
    {
        return Usuario::create(array_merge([
            'persona_id' => $paciente->persona_id,
            'rol_id' => Rol::where('codigo', 'PACIENTE')->firstOrFail()->id,
            'password_hash' => self::CLAVE,
        ], $cuenta))->refresh();
    }

    // --- Crear acceso --------------------------------------------------------------------

    public function test_crea_la_cuenta_con_una_contraseña_temporal_de_72_horas(): void
    {
        $recepcionista = $this->recepcionista();
        $paciente = $this->crearPaciente(['ci' => '4440001']);

        $this->actingAs($recepcionista)
            ->post(route('pacientes.acceso.crear', $paciente))
            ->assertRedirect(route('pacientes.credenciales', $paciente));

        $cuenta = Usuario::with('rol')->where('persona_id', $paciente->persona_id)->firstOrFail();
        $this->assertSame('PACIENTE', $cuenta->rol->codigo);
        $this->assertTrue($cuenta->estado);
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertEqualsWithDelta(72 * 3600, now()->diffInSeconds($cuenta->password_temporal_vence), 10);

        $credenciales = session('credenciales');
        $this->assertSame('4440001', $credenciales['usuario']);
        $this->assertSame(14, strlen($credenciales['contrasena']));
        $this->assertTrue(Hash::check($credenciales['contrasena'], $cuenta->password_hash));
        $this->assertNotSame($credenciales['contrasena'], $cuenta->password_hash, 'solo se guarda el hash');

        $this->assertSame(1, Bitacora::where('usuario_id', $recepcionista->id)->where('accion', 'acceso_creado')->count());
    }

    public function test_la_contraseña_temporal_se_muestra_una_sola_vez(): void
    {
        $paciente = $this->crearPaciente();
        $this->actingAs($this->recepcionista())->post(route('pacientes.acceso.crear', $paciente));
        $contrasena = session('credenciales.contrasena');

        $this->get(route('pacientes.credenciales', $paciente))
            ->assertOk()
            ->assertSee($contrasena)
            ->assertSee('una sola vez');

        $this->get(route('pacientes.credenciales', $paciente))
            ->assertRedirect(route('pacientes.show', $paciente))
            ->assertSessionHas('error');
    }

    public function test_las_credenciales_de_un_paciente_no_se_ven_desde_la_ficha_de_otro(): void
    {
        $paciente = $this->crearPaciente();
        $otro = $this->crearPaciente();
        $this->actingAs($this->recepcionista())->post(route('pacientes.acceso.crear', $paciente));

        $this->get(route('pacientes.credenciales', $otro))->assertRedirect(route('pacientes.show', $otro));
    }

    public function test_el_paciente_entra_con_su_ci_y_la_contraseña_temporal_y_debe_cambiarla(): void
    {
        $paciente = $this->crearPaciente(['ci' => '4440002']);
        $this->actingAs($this->recepcionista())->post(route('pacientes.acceso.crear', $paciente));
        $contrasena = session('credenciales.contrasena');

        $this->post(route('logout'));
        $this->post(route('login.autenticar'), $this->credenciales('4440002', $contrasena))
            ->assertRedirect(route('inicio'));

        $this->get(route('inicio'))->assertRedirect(route('password.cambiar'));
    }

    public function test_no_se_crea_una_segunda_cuenta_para_el_mismo_paciente(): void
    {
        $paciente = $this->crearPaciente();
        $this->darCuenta($paciente);

        $this->actingAs($this->recepcionista())
            ->from(route('pacientes.show', $paciente))
            ->post(route('pacientes.acceso.crear', $paciente))
            ->assertRedirect(route('pacientes.show', $paciente))
            ->assertSessionHas('error', 'El paciente ya tiene una cuenta.');

        $this->assertSame(1, Usuario::where('persona_id', $paciente->persona_id)->count());
    }

    public function test_no_crea_cuenta_de_paciente_a_una_persona_que_ya_es_medico(): void
    {
        $medico = $this->crearCuenta('MEDICO');
        $paciente = Paciente::create(['persona_id' => $medico->persona_id, 'verificado' => true])->refresh();

        $this->actingAs($this->recepcionista())
            ->from(route('pacientes.show', $paciente))
            ->post(route('pacientes.acceso.crear', $paciente))
            ->assertSessionHas('error');

        $this->assertStringContainsString('rol Médico', session('error'));
        $this->assertSame(1, Usuario::where('persona_id', $medico->persona_id)->count());
    }

    public function test_no_crea_cuenta_a_un_paciente_desactivado(): void
    {
        $paciente = $this->crearPaciente([], ['estado' => false]);

        $this->actingAs($this->recepcionista())
            ->from(route('pacientes.show', $paciente))
            ->post(route('pacientes.acceso.crear', $paciente))
            ->assertSessionHas('error');

        $this->assertSame(0, Usuario::where('persona_id', $paciente->persona_id)->count());
    }

    // --- Renovar -------------------------------------------------------------------------

    public function test_renovar_invalida_la_contraseña_anterior_y_levanta_el_bloqueo(): void
    {
        $recepcionista = $this->recepcionista();
        $paciente = $this->crearPaciente(['ci' => '5550001']);
        $cuenta = $this->darCuenta($paciente, [
            'intentos_fallidos' => 3,
            'bloqueado_hasta' => now()->addMinutes(10),
            'debe_cambiar_password' => true,
            'password_temporal_vence' => now()->subHour(),
        ]);

        $this->actingAs($recepcionista)
            ->post(route('pacientes.acceso.renovar', $paciente), ['verificacion' => 'documento'])
            ->assertRedirect(route('pacientes.credenciales', $paciente));

        $nueva = session('credenciales.contrasena');
        $cuenta->refresh();

        $this->assertFalse(Hash::check(self::CLAVE, $cuenta->password_hash), 'la anterior deja de servir');
        $this->assertTrue(Hash::check($nueva, $cuenta->password_hash));
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertTrue($cuenta->password_temporal_vence->isFuture());
        $this->assertSame(0, $cuenta->intentos_fallidos);
        $this->assertNull($cuenta->bloqueado_hasta);
        $this->assertSame(1, Bitacora::where('usuario_id', $recepcionista->id)->where('accion', 'acceso_renovado')->count());

        $this->post(route('logout'));
        $this->post(route('login.autenticar'), $this->credenciales('5550001', $nueva));
        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_renovar_sirve_tambien_si_el_paciente_olvido_una_contraseña_ya_cambiada(): void
    {
        $paciente = $this->crearPaciente();
        $cuenta = $this->darCuenta($paciente); // cuenta normal, sin contraseña temporal

        $this->actingAs($this->recepcionista())
            ->post(route('pacientes.acceso.renovar', $paciente), ['verificacion' => 'celular']);

        $this->assertTrue($cuenta->fresh()->debe_cambiar_password);
    }

    public function test_no_renueva_si_no_hay_cuenta_ni_si_es_de_otro_rol_ni_si_esta_desactivado(): void
    {
        $recepcionista = $this->recepcionista();
        $this->actingAs($recepcionista);

        $sinCuenta = $this->crearPaciente();
        $this->from(route('pacientes.show', $sinCuenta))
            ->post(route('pacientes.acceso.renovar', $sinCuenta), ['verificacion' => 'documento'])
            ->assertSessionHas('error');

        $medico = $this->crearCuenta('MEDICO');
        $esMedico = Paciente::create(['persona_id' => $medico->persona_id, 'verificado' => true])->refresh();
        $this->from(route('pacientes.show', $esMedico))
            ->post(route('pacientes.acceso.renovar', $esMedico), ['verificacion' => 'documento'])
            ->assertSessionHas('error');
        $this->assertStringContainsString('Administrador', session('error'));

        $desactivado = $this->crearPaciente([], ['estado' => false]);
        $this->darCuenta($desactivado);
        $this->from(route('pacientes.show', $desactivado))
            ->post(route('pacientes.acceso.renovar', $desactivado), ['verificacion' => 'documento'])
            ->assertSessionHas('error');
    }

    // --- CU-19: comprobar la identidad antes de renovar -----------------------------------

    public function test_renovar_exige_indicar_como_se_verifico_la_identidad(): void
    {
        $paciente = $this->crearPaciente();
        $cuenta = $this->darCuenta($paciente);
        $hashAntes = $cuenta->password_hash;

        $this->actingAs($this->recepcionista())
            ->from(route('pacientes.show', $paciente))
            ->post(route('pacientes.acceso.renovar', $paciente))
            ->assertRedirect(route('pacientes.show', $paciente))
            ->assertSessionHasErrors('verificacion');

        $this->assertSame($hashAntes, $cuenta->fresh()->password_hash, 'sin verificar la identidad no se renueva nada');
        $this->assertSame(0, Bitacora::where('accion', 'acceso_renovado')->count());
        $this->assertNull(session('credenciales'));
    }

    public function test_rechaza_una_forma_de_verificar_que_no_existe(): void
    {
        $paciente = $this->crearPaciente();
        $this->darCuenta($paciente);

        $this->actingAs($this->recepcionista())
            ->post(route('pacientes.acceso.renovar', $paciente), ['verificacion' => 'porque_si'])
            ->assertSessionHasErrors('verificacion');

        $this->assertSame(0, Bitacora::where('accion', 'acceso_renovado')->count());
    }

    public function test_la_bitacora_guarda_como_se_verifico_la_identidad(): void
    {
        $recepcionista = $this->recepcionista();
        $paciente = $this->crearPaciente();
        $this->darCuenta($paciente);
        $this->actingAs($recepcionista);

        $this->post(route('pacientes.acceso.renovar', $paciente), ['verificacion' => 'documento']);
        $this->post(route('pacientes.acceso.renovar', $paciente), ['verificacion' => 'celular']);

        $detalles = Bitacora::where('usuario_id', $recepcionista->id)
            ->where('accion', 'acceso_renovado')->orderBy('id')->pluck('detalle')->all();

        $this->assertSame([
            'Identidad verificada con: documento de identidad en persona',
            'Identidad verificada con: mensaje al celular registrado en la ficha',
        ], $detalles);
    }

    public function test_verificar_con_renovacion_registra_el_documento_como_comprobacion(): void
    {
        $paciente = $this->crearPaciente([], ['verificado' => false]);
        $this->darCuenta($paciente);

        $this->actingAs($this->recepcionista())
            ->post(route('pacientes.verificar', $paciente), ['renovar_acceso' => 1]);

        $this->assertStringContainsString(
            'documento de identidad en persona',
            Bitacora::where('accion', 'acceso_renovado')->firstOrFail()->detalle
        );
    }

    public function test_la_ficha_ofrece_las_formas_de_verificar_la_identidad_al_renovar(): void
    {
        $paciente = $this->crearPaciente();
        $this->darCuenta($paciente);

        $this->actingAs($this->recepcionista())
            ->get(route('pacientes.show', $paciente))
            ->assertSee('¿Cómo verificó la identidad del titular?')
            ->assertSee('Documento de identidad en persona')
            ->assertSee('Mensaje al celular registrado en la ficha')
            ->assertSee('nunca a otro número');
    }

    // --- Verificar -----------------------------------------------------------------------

    public function test_verifica_una_ficha_pendiente(): void
    {
        $recepcionista = $this->recepcionista();
        $paciente = $this->crearPaciente([], ['verificado' => false]);

        $this->actingAs($recepcionista)
            ->post(route('pacientes.verificar', $paciente))
            ->assertRedirect(route('pacientes.show', $paciente))
            ->assertSessionHas('exito');

        $this->assertTrue($paciente->fresh()->verificado);
        $this->assertSame(1, Bitacora::where('usuario_id', $recepcionista->id)->where('accion', 'paciente_verificado')->count());
    }

    public function test_verificar_puede_renovar_la_contraseña_en_el_mismo_acto(): void
    {
        $paciente = $this->crearPaciente(['ci' => '6660001'], ['verificado' => false]);
        $cuenta = $this->darCuenta($paciente);

        $this->actingAs($this->recepcionista())
            ->post(route('pacientes.verificar', $paciente), ['renovar_acceso' => 1])
            ->assertRedirect(route('pacientes.credenciales', $paciente));

        $this->assertTrue($paciente->fresh()->verificado);
        $this->assertFalse(Hash::check(self::CLAVE, $cuenta->fresh()->password_hash));
        $this->assertTrue($cuenta->fresh()->debe_cambiar_password);
    }

    public function test_no_se_verifica_dos_veces(): void
    {
        $paciente = $this->crearPaciente(); // verificada

        $this->actingAs($this->recepcionista())
            ->from(route('pacientes.show', $paciente))
            ->post(route('pacientes.verificar', $paciente))
            ->assertSessionHas('error', 'La ficha ya está verificada.');
    }

    public function test_la_ficha_pendiente_muestra_el_aviso_para_comparar_el_documento(): void
    {
        $paciente = $this->crearPaciente([], ['verificado' => false]);

        $this->actingAs($this->recepcionista())
            ->get(route('pacientes.show', $paciente))
            ->assertSee('Verificar ficha')
            ->assertSee('documento de identidad');
    }

    // --- Permisos ------------------------------------------------------------------------

    public function test_ningun_otro_rol_puede_crear_renovar_ni_verificar(): void
    {
        $paciente = $this->crearPaciente([], ['verificado' => false]);
        $this->darCuenta($paciente);

        // El Administrador tiene accesos:renovar, pero no entra a las pantallas de pacientes.
        foreach (['ADMINISTRADOR', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->post(route('pacientes.acceso.crear', $paciente))->assertForbidden();
            $this->post(route('pacientes.acceso.renovar', $paciente))->assertForbidden();
            $this->post(route('pacientes.verificar', $paciente))->assertForbidden();
            $this->patch(route('pacientes.estado', $paciente), ['activo' => 0])->assertForbidden();
        }

        $this->assertFalse($paciente->fresh()->verificado);
        $this->assertTrue($paciente->fresh()->estado);
    }
}
