<?php

namespace Tests\Feature\Acceso;

use App\Models\Paciente;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * Tras cerrar sesión, el botón «Atrás» del navegador no debe mostrar páginas privadas desde su
 * caché (prueba manual PM-78): las respuestas con sesión se marcan como no almacenables.
 */
class CacheDeSesionTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    public function test_las_paginas_con_sesion_no_se_guardan_en_la_cache_del_navegador(): void
    {
        $paciente = $this->crearCuenta('PACIENTE');
        Paciente::create(['persona_id' => $paciente->persona_id, 'verificado' => true]);

        foreach (['inicio', 'perfil.mostrar'] as $ruta) {
            $respuesta = $this->actingAs($paciente)->get(route($ruta))->assertOk();

            $this->assertStringContainsString('no-store', $respuesta->headers->get('Cache-Control'), $ruta);
            $this->assertStringContainsString('private', $respuesta->headers->get('Cache-Control'), $ruta);
        }
    }

    public function test_tambien_las_paginas_del_personal_y_las_de_error(): void
    {
        $this->actingAs($this->crearCuenta('ADMINISTRADOR'));

        $this->assertStringContainsString('no-store', $this->get(route('usuarios.index'))->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $this->get(route('pacientes.index'))->assertForbidden()->headers->get('Cache-Control'));
    }

    public function test_las_paginas_publicas_no_se_marcan(): void
    {
        $this->assertStringNotContainsString('no-store', (string) $this->get(route('login'))->headers->get('Cache-Control'));
    }
}
