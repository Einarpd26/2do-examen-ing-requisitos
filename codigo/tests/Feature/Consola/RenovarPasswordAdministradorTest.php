<?php

namespace Tests\Feature\Consola;

use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * Recuperación de emergencia (CU-19): un Administrador que olvidó su contraseña y no tiene
 * otro Administrador que se la renueve.
 */
class RenovarPasswordAdministradorTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    public function test_genera_una_contraseña_temporal_nueva_para_el_administrador(): void
    {
        $admin = $this->crearCuenta('ADMINISTRADOR', ['correo' => 'admin@clinica.test'], [
            'intentos_fallidos' => 3,
            'bloqueado_hasta' => now()->addMinutes(10),
        ]);

        $this->artisan('administrador:renovar-password', ['correo' => 'admin@clinica.test', '--force' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('Contraseña:')
            ->expectsOutputToContain('Debe cambiarla al entrar');

        $admin->refresh();
        $this->assertFalse(Hash::check(self::CLAVE, $admin->password_hash), 'la anterior deja de servir');
        $this->assertTrue($admin->debe_cambiar_password);
        $this->assertEqualsWithDelta(72 * 3600, now()->diffInSeconds($admin->password_temporal_vence), 10);
        $this->assertSame(0, $admin->intentos_fallidos);
        $this->assertNull($admin->bloqueado_hasta);
    }

    public function test_el_administrador_puede_entrar_con_la_contraseña_generada_y_debe_cambiarla(): void
    {
        $this->crearCuenta('ADMINISTRADOR', ['correo' => 'admin@clinica.test']);

        Artisan::call('administrador:renovar-password', ['correo' => 'admin@clinica.test', '--force' => true]);
        $this->assertSame(1, preg_match('/Contraseña:\s+(\S+)/', Artisan::output(), $coincidencia));

        $this->post(route('login.autenticar'), $this->credenciales('admin@clinica.test', $coincidencia[1]))
            ->assertRedirect(route('inicio'));
        $this->get(route('inicio'))->assertRedirect(route('password.cambiar'));
    }

    public function test_el_correo_no_distingue_mayusculas(): void
    {
        $this->crearCuenta('ADMINISTRADOR', ['correo' => 'admin@clinica.test']);

        $this->artisan('administrador:renovar-password', ['correo' => ' ADMIN@Clinica.TEST ', '--force' => true])
            ->assertSuccessful();
    }

    public function test_deja_constancia_en_la_bitacora_sin_usuario_actor(): void
    {
        $admin = $this->crearCuenta('ADMINISTRADOR', ['correo' => 'admin@clinica.test']);

        $this->artisan('administrador:renovar-password', ['correo' => 'admin@clinica.test', '--force' => true]);

        $registro = Bitacora::where('accion', 'acceso_renovado')->firstOrFail();
        $this->assertNull($registro->usuario_id);
        $this->assertSame($admin->id, $registro->entidad_id);
        $this->assertSame('Renovada desde la consola del servidor', $registro->detalle);
    }

    public function test_no_sirve_para_cuentas_que_no_son_de_administrador(): void
    {
        $medico = $this->crearCuenta('MEDICO', ['correo' => 'medico@clinica.test']);
        $hashAntes = $medico->password_hash;

        $this->artisan('administrador:renovar-password', ['correo' => 'medico@clinica.test', '--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('No existe un Administrador con ese correo.');

        $this->assertSame($hashAntes, $medico->fresh()->password_hash);
        $this->assertSame(0, Bitacora::where('accion', 'acceso_renovado')->count());
    }

    public function test_falla_si_no_existe_el_correo(): void
    {
        $this->artisan('administrador:renovar-password', ['correo' => 'nadie@clinica.test', '--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('No existe un Administrador con ese correo.');
    }

    public function test_no_renueva_una_cuenta_desactivada(): void
    {
        $admin = $this->crearCuenta('ADMINISTRADOR', ['correo' => 'admin@clinica.test'], ['estado' => false]);
        $hashAntes = $admin->password_hash;

        $this->artisan('administrador:renovar-password', ['correo' => 'admin@clinica.test', '--force' => true])
            ->assertFailed()
            ->expectsOutputToContain('desactivada');

        $this->assertSame($hashAntes, $admin->fresh()->password_hash);
    }

    public function test_pide_confirmacion_y_si_se_cancela_no_cambia_nada(): void
    {
        $admin = $this->crearCuenta('ADMINISTRADOR', ['correo' => 'admin@clinica.test']);
        $hashAntes = $admin->password_hash;

        $this->artisan('administrador:renovar-password', ['correo' => 'admin@clinica.test'])
            ->expectsConfirmation('¿Generar una contraseña temporal nueva para Prueba Cuenta?', 'no')
            ->expectsOutputToContain('Cancelado')
            ->assertSuccessful();

        $this->assertSame($hashAntes, $admin->fresh()->password_hash);
        $this->assertFalse($admin->fresh()->debe_cambiar_password);
    }
}
