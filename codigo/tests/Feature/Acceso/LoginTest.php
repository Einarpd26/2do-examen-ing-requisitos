<?php

namespace Tests\Feature\Acceso;

use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-01: iniciar y cerrar sesión (RF01, RNF14, RNF15, RNF18).
 */
class LoginTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    public function test_una_pagina_interna_sin_sesion_redirige_al_login(): void
    {
        $this->get(route('inicio'))->assertRedirect(route('login'));
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_el_formulario_muestra_el_celular_de_la_clinica(): void
    {
        config(['clinica.celular' => '+591 71234567']);

        $this->get(route('login'))->assertOk()->assertSee('+591 71234567');
    }

    public function test_el_personal_entra_con_su_correo(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA', ['correo' => 'recepcion@clinica.test']);

        $this->post(route('login.autenticar'), $this->credenciales('recepcion@clinica.test'))
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_el_correo_no_distingue_mayusculas(): void
    {
        $cuenta = $this->crearCuenta('ADMINISTRADOR', ['correo' => 'admin@clinica.test']);

        $this->post(route('login.autenticar'), $this->credenciales('ADMIN@Clinica.TEST'));

        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_el_paciente_entra_con_su_ci(): void
    {
        $cuenta = $this->crearCuenta('PACIENTE', ['ci' => '7654321-1A']);

        $this->post(route('login.autenticar'), $this->credenciales('7654321-1a'))
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_el_paciente_no_entra_con_su_correo(): void
    {
        $this->crearCuenta('PACIENTE', ['correo' => 'paciente@mail.com']);

        $this->post(route('login.autenticar'), $this->credenciales('paciente@mail.com'))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    public function test_el_personal_no_entra_con_su_ci(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->ci))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    public function test_contrasena_incorrecta_y_cuenta_inexistente_dan_el_mismo_mensaje(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo, 'Equivocada-1'));
        $mensajeClaveMala = $this->mensajeDeError();

        $this->post(route('login.autenticar'), $this->credenciales('nadie@clinica.test'));
        $mensajeSinCuenta = $this->mensajeDeError();

        $this->assertNotSame('', $mensajeClaveMala);
        $this->assertSame($mensajeClaveMala, $mensajeSinCuenta);
        $this->assertGuest();
    }

    public function test_cada_fallo_suma_un_intento_y_entrar_bien_lo_reinicia(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');
        $correo = $cuenta->persona->correo;

        $this->post(route('login.autenticar'), $this->credenciales($correo, 'Mala-1'));
        $this->post(route('login.autenticar'), $this->credenciales($correo, 'Mala-2'));
        $this->assertSame(2, $cuenta->fresh()->intentos_fallidos);

        $this->post(route('login.autenticar'), $this->credenciales($correo));
        $this->assertSame(0, $cuenta->fresh()->intentos_fallidos);
        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_el_quinto_fallo_bloquea_la_cuenta_15_minutos(): void
    {
        $cuenta = $this->crearCuenta('MEDICO', [], ['intentos_fallidos' => 4]);

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo, 'Mala-5'));

        $cuenta->refresh();
        $this->assertNotNull($cuenta->bloqueado_hasta);
        $this->assertEqualsWithDelta(15 * 60, now()->diffInSeconds($cuenta->bloqueado_hasta), 5);
        $this->assertSame(0, $cuenta->intentos_fallidos);
        $this->assertStringContainsString('15 minutos', $this->mensajeDeError());
    }

    public function test_una_cuenta_bloqueada_no_entra_ni_con_la_clave_correcta(): void
    {
        $cuenta = $this->crearCuenta('MEDICO', [], ['bloqueado_hasta' => now()->addMinutes(10)]);

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
        $this->assertStringContainsString('bloqueada', $this->mensajeDeError());
    }

    public function test_pasado_el_bloqueo_la_cuenta_vuelve_a_entrar(): void
    {
        $cuenta = $this->crearCuenta('MEDICO', [], ['bloqueado_hasta' => now()->subMinute()]);

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo));

        $this->assertAuthenticatedAs($cuenta);
        $this->assertNull($cuenta->fresh()->bloqueado_hasta);
    }

    public function test_una_cuenta_desactivada_no_entra(): void
    {
        $cuenta = $this->crearCuenta('MEDICO', [], ['estado' => false]);

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
        $this->assertStringContainsString('desactivada', $this->mensajeDeError());
    }

    public function test_una_contrasena_temporal_vigente_entra_pero_obliga_a_cambiarla(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA', [], [
            'debe_cambiar_password' => true,
            'password_temporal_vence' => now()->addHours(72),
        ]);

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo));
        $this->assertAuthenticatedAs($cuenta);

        $this->get(route('inicio'))->assertRedirect(route('password.cambiar'));
        $this->get(route('password.cambiar'))->assertOk();
    }

    public function test_una_contrasena_temporal_vencida_no_entra(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA', [], [
            'debe_cambiar_password' => true,
            'password_temporal_vence' => now()->subMinute(),
        ]);

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
        $this->assertStringContainsString('venció', $this->mensajeDeError());
    }

    public function test_cerrar_sesion_la_destruye(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');

        $this->actingAs($cuenta)->post(route('logout'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->get(route('inicio'))->assertRedirect(route('login'));
    }

    public function test_quien_ya_inicio_sesion_no_vuelve_al_formulario(): void
    {
        $this->actingAs($this->crearCuenta('MEDICO'))
            ->get(route('login'))
            ->assertRedirect(route('inicio'));
    }

    public function test_una_cuenta_desactivada_con_la_sesion_abierta_se_cierra_en_la_siguiente_solicitud(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');
        $this->actingAs($cuenta);
        $this->get(route('inicio'))->assertOk();

        $cuenta->update(['estado' => false]);

        $this->get(route('inicio'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_el_menu_muestra_solo_lo_que_el_rol_permite(): void
    {
        $this->actingAs($this->crearCuenta('ADMINISTRADOR'))
            ->get(route('inicio'))
            ->assertOk()
            ->assertSee('Usuarios')
            ->assertSee('Especialidades')
            ->assertDontSee('Pacientes')
            ->assertDontSee('Mi perfil');

        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))
            ->get(route('inicio'))
            ->assertSee('Pacientes')
            ->assertDontSee('Usuarios')
            ->assertDontSee('Especialidades');

        $this->actingAs($this->crearCuenta('PACIENTE'))
            ->get(route('inicio'))
            ->assertSee('Mi perfil')
            ->assertDontSee('Pacientes')
            ->assertDontSee('Usuarios');
    }

    public function test_los_accesos_quedan_en_la_bitacora(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');
        $correo = $cuenta->persona->correo;

        $this->post(route('login.autenticar'), $this->credenciales($correo, 'Mala-1'));
        $this->post(route('login.autenticar'), $this->credenciales($correo));
        $this->post(route('logout'));

        $acciones = Bitacora::where('usuario_id', $cuenta->id)->orderBy('id')->pluck('accion')->all();

        $this->assertSame(['login_fallido', 'login', 'logout'], $acciones);
    }

    public function test_un_intento_con_una_cuenta_inexistente_se_registra_sin_usuario(): void
    {
        $this->post(route('login.autenticar'), $this->credenciales('nadie@clinica.test'));

        $this->assertSame(1, Bitacora::whereNull('usuario_id')->where('accion', 'login_fallido')->count());
    }

    public function test_el_formulario_exige_los_dos_campos(): void
    {
        $this->post(route('login.autenticar'), [])
            ->assertSessionHasErrors(['identificador', 'contrasena']);
    }
}
