<?php

namespace Tests\Feature\Acceso;

use App\Models\Bitacora;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * Cambio de contraseña, obligatorio en el primer ingreso con una contraseña temporal (RNF18).
 */
class CambioPasswordTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private const NUEVA = 'Nueva-Clave-456';

    private function conTemporal(): \App\Models\Usuario
    {
        return $this->crearCuenta('RECEPCIONISTA', [], [
            'debe_cambiar_password' => true,
            'password_temporal_vence' => now()->addHours(72),
            'intentos_fallidos' => 2,
        ]);
    }

    private function cambio(string $actual = self::CLAVE, string $nueva = self::NUEVA, ?string $confirmacion = null): array
    {
        return [
            'password_actual' => $actual,
            'password' => $nueva,
            'password_confirmation' => $confirmacion ?? $nueva,
        ];
    }

    public function test_cambiar_la_contrasena_limpia_las_dos_columnas_de_la_temporal(): void
    {
        $cuenta = $this->conTemporal();

        $this->actingAs($cuenta)
            ->put(route('password.actualizar'), $this->cambio())
            ->assertRedirect(route('inicio'));

        $cuenta->refresh();
        $this->assertFalse($cuenta->debe_cambiar_password);
        $this->assertNull($cuenta->password_temporal_vence);
        $this->assertSame(0, $cuenta->intentos_fallidos);
        $this->assertTrue(Hash::check(self::NUEVA, $cuenta->password_hash));
        $this->assertFalse(Hash::check(self::CLAVE, $cuenta->password_hash));
    }

    public function test_despues_de_cambiarla_ya_puede_usar_el_sistema_y_entrar_con_la_nueva(): void
    {
        $cuenta = $this->conTemporal();

        $this->actingAs($cuenta)->put(route('password.actualizar'), $this->cambio());
        $this->get(route('inicio'))->assertOk();

        $this->post(route('logout'));
        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo, self::NUEVA));
        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_mientras_no_la_cambie_no_puede_usar_otras_paginas(): void
    {
        $cuenta = $this->conTemporal();

        $this->actingAs($cuenta)->get(route('inicio'))->assertRedirect(route('password.cambiar'));
        $this->get(route('password.cambiar'))->assertOk();
    }

    public function test_rechaza_la_contrasena_actual_incorrecta(): void
    {
        $cuenta = $this->conTemporal();

        $this->actingAs($cuenta)
            ->put(route('password.actualizar'), $this->cambio(actual: 'Equivocada-1'))
            ->assertSessionHasErrors('password_actual');

        $this->assertTrue($cuenta->fresh()->debe_cambiar_password);
    }

    public function test_rechaza_una_contrasena_debil(): void
    {
        $cuenta = $this->conTemporal();

        foreach (['Corta1a', 'todaminuscula123', 'TODAMAYUSCULA123', 'SinNumeros-abc'] as $debil) {
            $this->actingAs($cuenta)
                ->put(route('password.actualizar'), $this->cambio(nueva: $debil))
                ->assertSessionHasErrors('password');
        }

        $this->assertTrue($cuenta->fresh()->debe_cambiar_password);
    }

    public function test_rechaza_una_confirmacion_distinta(): void
    {
        $this->actingAs($this->conTemporal())
            ->put(route('password.actualizar'), $this->cambio(confirmacion: 'Otra-Clave-789'))
            ->assertSessionHasErrors('password');
    }

    public function test_rechaza_repetir_la_contrasena_actual(): void
    {
        $this->actingAs($this->conTemporal())
            ->put(route('password.actualizar'), $this->cambio(nueva: self::CLAVE))
            ->assertSessionHasErrors('password');
    }

    public function test_el_cambio_queda_en_la_bitacora(): void
    {
        $cuenta = $this->conTemporal();

        $this->actingAs($cuenta)->put(route('password.actualizar'), $this->cambio());

        $this->assertSame(1, Bitacora::where('usuario_id', $cuenta->id)->where('accion', 'cambio_password')->count());
    }

    public function test_sin_sesion_no_se_puede_cambiar_la_contrasena(): void
    {
        $this->put(route('password.actualizar'), $this->cambio())->assertRedirect(route('login'));
    }
}
