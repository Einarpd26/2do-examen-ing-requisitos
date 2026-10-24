<?php

namespace Tests\Feature\Perfil;

use App\Models\Bitacora;
use App\Models\Paciente;
use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-08: el paciente consulta su perfil y modifica su teléfono (RF14).
 */
class PerfilTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    /** Cuenta de paciente con su ficha. */
    private function paciente(array $persona = [], array $ficha = []): Usuario
    {
        $cuenta = $this->crearCuenta('PACIENTE', array_merge(['telefono' => '70011122'], $persona));
        Paciente::create(array_merge(['persona_id' => $cuenta->persona_id, 'verificado' => true], $ficha));

        return $cuenta;
    }

    public function test_solo_el_paciente_tiene_perfil(): void
    {
        foreach (['ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->get(route('perfil.mostrar'))->assertForbidden();
            $this->put(route('perfil.actualizar'), ['telefono' => '70099988'])->assertForbidden();
        }

        $this->actingAs($this->paciente())->get(route('perfil.mostrar'))->assertOk();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('perfil.mostrar'))->assertRedirect(route('login'));
    }

    public function test_muestra_los_datos_con_la_informacion_medica_si_la_ficha_esta_verificada(): void
    {
        $cuenta = $this->paciente(
            ['primer_nombre' => 'María', 'apellido_paterno' => 'López', 'correo' => 'maria@mail.com'],
            ['tipo_sangre' => 'O+', 'alergias' => 'Penicilina', 'emergencia_nombre' => 'Juan López'],
        );

        $this->actingAs($cuenta)->get(route('perfil.mostrar'))
            ->assertOk()
            ->assertSee('María López')
            ->assertSee('maria@mail.com')
            ->assertSee('Ficha verificada')
            ->assertSee('Penicilina')
            ->assertSee('Juan López')
            ->assertDontSee('pendiente de verificación');
    }

    public function test_con_la_ficha_pendiente_oculta_la_informacion_medica(): void
    {
        $cuenta = $this->paciente([], ['verificado' => false, 'alergias' => 'Penicilina', 'emergencia_nombre' => 'Juan López']);

        $this->actingAs($cuenta)->get(route('perfil.mostrar'))
            ->assertOk()
            ->assertSee('Ficha pendiente de verificación')
            ->assertDontSee('Información médica')
            ->assertDontSee('Contacto de emergencia')
            ->assertDontSee('Penicilina')
            ->assertDontSee('Juan López');
    }

    public function test_solo_el_telefono_es_editable_y_enlaza_al_cambio_de_contrasena(): void
    {
        $html = $this->actingAs($this->paciente())->get(route('perfil.mostrar'))
            ->assertSee(route('password.cambiar'), false)
            ->getContent();

        $this->assertSame(1, substr_count($html, '<input type="tel"'), 'un solo campo de texto editable');
        $this->assertSame(1, substr_count($html, 'name="telefono"'));
        $this->assertStringNotContainsString('name="primer_nombre"', $html);
        $this->assertStringNotContainsString('name="correo"', $html);
    }

    public function test_actualiza_el_telefono_y_queda_en_la_bitacora(): void
    {
        $cuenta = $this->paciente();

        $this->actingAs($cuenta)
            ->put(route('perfil.actualizar'), ['telefono' => '+591 700-99988'])
            ->assertRedirect(route('perfil.mostrar'))
            ->assertSessionHas('exito', 'Su teléfono fue actualizado.');

        $this->assertSame('+59170099988', $cuenta->persona->fresh()->telefono);
        $registro = Bitacora::where('usuario_id', $cuenta->id)->where('accion', 'perfil_modificado')->firstOrFail();
        $this->assertSame('Teléfono', $registro->detalle);
    }

    public function test_si_el_telefono_no_cambia_no_registra_nada(): void
    {
        $cuenta = $this->paciente();

        $this->actingAs($cuenta)->put(route('perfil.actualizar'), ['telefono' => '70011122'])->assertRedirect();

        $this->assertSame(0, Bitacora::where('accion', 'perfil_modificado')->count());
    }

    public function test_valida_el_telefono_en_el_servidor(): void
    {
        $cuenta = $this->paciente();
        $this->actingAs($cuenta);

        foreach (['', '12', 'abcdefghij', '1234567890123456'] as $invalido) {
            $this->put(route('perfil.actualizar'), ['telefono' => $invalido])->assertSessionHasErrors('telefono');
        }

        $this->assertSame('70011122', $cuenta->persona->fresh()->telefono);
    }

    public function test_el_mensaje_de_error_sale_en_espanol(): void
    {
        $this->actingAs($this->paciente())->put(route('perfil.actualizar'), ['telefono' => '12']);

        $this->assertSame('El teléfono debe tener de 7 a 15 dígitos, con un + inicial opcional.', $this->mensajeDeError('telefono'));
    }

    public function test_no_acepta_modificar_otros_datos_por_esta_via(): void
    {
        $cuenta = $this->paciente(['primer_nombre' => 'Ana', 'correo' => null], ['alergias' => 'Ninguna']);

        $this->actingAs($cuenta)->put(route('perfil.actualizar'), [
            'telefono' => '70099988',
            'primer_nombre' => 'Otro',
            'ci' => '1',
            'correo' => 'otro@mail.com',
            'alergias' => 'Polvo',
        ])->assertSessionDoesntHaveErrors();

        $persona = $cuenta->persona->fresh();
        $this->assertSame('Ana', $persona->primer_nombre);
        $this->assertNull($persona->correo);
        $this->assertNotSame('1', $persona->ci);
        $this->assertSame('Ninguna', $persona->paciente->alergias);
    }

    public function test_solo_modifica_su_propia_persona(): void
    {
        $otro = $this->paciente(['telefono' => '71111111']);
        $cuenta = $this->paciente();

        $this->actingAs($cuenta)->put(route('perfil.actualizar'), ['telefono' => '70099988', 'persona_id' => $otro->persona_id]);

        $this->assertSame('71111111', $otro->persona->fresh()->telefono);
    }

    public function test_una_cuenta_de_paciente_sin_ficha_no_tiene_perfil(): void
    {
        $this->actingAs($this->crearCuenta('PACIENTE'))->get(route('perfil.mostrar'))->assertNotFound();
    }

    public function test_el_menu_del_paciente_enlaza_a_su_perfil(): void
    {
        $this->actingAs($this->paciente())->get(route('inicio'))->assertSee(route('perfil.mostrar'), false);
    }
}
