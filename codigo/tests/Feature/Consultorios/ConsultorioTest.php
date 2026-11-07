<?php

namespace Tests\Feature\Consultorios;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Bitacora;
use App\Models\Consultorio;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\ConsultorioService;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-20: gestionar consultorios (ADR-002).
 */
class ConsultorioTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private function administrador(): Usuario
    {
        return $this->crearCuenta('ADMINISTRADOR');
    }

    private function consultorio(string $nombre = 'Consultorio 3', bool $activo = true): Consultorio
    {
        return Consultorio::create([
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'nombre' => $nombre,
            'estado' => $activo,
        ])->refresh();
    }

    /** Bloque de atención de un médico nuevo que usa el consultorio. */
    private function bloqueEn(Consultorio $consultorio, int $dia = 1): HorarioAtencion
    {
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina general'], ['duracion_min' => 20]);
        $medico = $this->crearFichaMedico([], [], [$especialidad->id]);

        return HorarioAtencion::create([
            'medico_id' => $medico->id,
            'sucursal_id' => $consultorio->sucursal_id,
            'dia_semana' => $dia,
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
            'consultorio_id' => $consultorio->id,
        ]);
    }

    // --- Permisos -----------------------------------------------------------------------

    public function test_solo_el_administrador_gestiona_consultorios(): void
    {
        $consultorio = $this->consultorio();

        foreach (['RECEPCIONISTA', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->get(route('consultorios.index'))->assertForbidden();
            $this->get(route('consultorios.create'))->assertForbidden();
            $this->post(route('consultorios.store'), ['nombre' => 'Otro'])->assertForbidden();
            $this->get(route('consultorios.edit', $consultorio))->assertForbidden();
            $this->put(route('consultorios.update', $consultorio), ['nombre' => 'Otro'])->assertForbidden();
            $this->patch(route('consultorios.estado', $consultorio), ['activo' => 0])->assertForbidden();
        }

        $this->assertTrue($consultorio->fresh()->estado);
        $this->assertSame(1, Consultorio::count());

        $this->actingAs($this->administrador())->get(route('consultorios.index'))->assertOk();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('consultorios.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_del_administrador_enlaza_a_consultorios(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('inicio'))
            ->assertSee(route('consultorios.index'), false);
    }

    // --- Listado ------------------------------------------------------------------------

    public function test_el_listado_muestra_nombre_estado_y_cantidad_de_bloques(): void
    {
        $consultorio = $this->consultorio('Consultorio 3');
        $this->bloqueEn($consultorio, 1);
        $this->bloqueEn($consultorio, 2);

        $this->actingAs($this->administrador())
            ->get(route('consultorios.index'))
            ->assertOk()
            ->assertSeeInOrder(['Consultorio 3', '2', 'Activo']);
    }

    public function test_por_defecto_muestra_solo_los_activos_y_las_pestañas_cuentan_cada_estado(): void
    {
        $this->consultorio('Consultorio 1');
        $this->consultorio('Consultorio 2', false);
        $this->actingAs($this->administrador());

        $this->get(route('consultorios.index'))
            ->assertSee('Consultorio 1')
            ->assertDontSee('Consultorio 2');

        $this->get(route('consultorios.index', ['estado' => 'inactivos']))
            ->assertSee('Consultorio 2')
            ->assertDontSee('Consultorio 1');

        $this->get(route('consultorios.index', ['estado' => 'todos']))
            ->assertSee('Consultorio 1')
            ->assertSee('Consultorio 2');
    }

    public function test_sin_consultorios_el_listado_lo_avisa(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('consultorios.index', ['estado' => 'todos']))
            ->assertSee('Todavía no hay consultorios registrados.');
    }

    // --- Registrar ----------------------------------------------------------------------

    public function test_registra_un_consultorio_en_la_unica_sucursal_y_queda_en_la_bitacora(): void
    {
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->post(route('consultorios.store'), ['nombre' => 'Consultorio 3'])
            ->assertRedirect(route('consultorios.index'))
            ->assertSessionHas('exito', 'Consultorio registrado.');

        $consultorio = Consultorio::firstOrFail();
        $this->assertSame('Consultorio 3', $consultorio->nombre);
        $this->assertSame(Sucursal::firstOrFail()->id, $consultorio->sucursal_id, 'se asigna la sucursal única');
        $this->assertTrue($consultorio->estado);

        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'consultorio_creado')->firstOrFail();
        $this->assertSame('Consultorio 3', $registro->detalle);
    }

    public function test_el_nombre_se_guarda_sin_espacios_de_mas(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('consultorios.store'), ['nombre' => '  Consultorio    3  ']);

        $this->assertSame('Consultorio 3', Consultorio::firstOrFail()->nombre);
    }

    public function test_rechaza_un_nombre_repetido_sin_distinguir_mayusculas_ni_espacios(): void
    {
        $this->consultorio('Consultorio 3');

        $this->actingAs($this->administrador())
            ->post(route('consultorios.store'), ['nombre' => ' CONSULTORIO 3 '])
            ->assertSessionHasErrors(['nombre' => ConsultorioService::MENSAJE_NOMBRE_REPETIDO]);

        $this->assertSame(1, Consultorio::count());
    }

    public function test_valida_el_nombre(): void
    {
        $this->actingAs($this->administrador());

        $this->post(route('consultorios.store'), ['nombre' => ''])->assertSessionHasErrors('nombre');
        $this->post(route('consultorios.store'), ['nombre' => '   '])->assertSessionHasErrors('nombre');
        $this->post(route('consultorios.store'), ['nombre' => str_repeat('a', 51)])->assertSessionHasErrors('nombre');
        $this->post(route('consultorios.store'), ['nombre' => str_repeat('a', 50)])->assertSessionDoesntHaveErrors();

        $this->assertSame(1, Consultorio::count());
    }

    public function test_una_solicitud_simultanea_con_el_mismo_nombre_se_informa_como_regla_de_negocio(): void
    {
        $this->consultorio('Consultorio 3');

        // Se salta la validación del formulario: simula que otra solicitud ganó la carrera.
        try {
            app(ConsultorioService::class)->registrar(['nombre' => 'consultorio 3'], $this->administrador());
            $this->fail('Debió rechazar el nombre repetido.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertSame(ConsultorioService::MENSAJE_NOMBRE_REPETIDO, $e->getMessage());
        }

        $this->assertSame(1, Consultorio::count());
    }

    public function test_la_base_rechaza_un_nombre_repetido_aunque_se_salte_la_aplicacion(): void
    {
        $this->consultorio('Consultorio 3');

        $this->expectException(UniqueConstraintViolationException::class);
        $this->consultorio('  CONSULTORIO 3 ');
    }

    // --- Modificar ----------------------------------------------------------------------

    public function test_renombra_el_consultorio_y_la_bitacora_guarda_el_antes_y_el_despues(): void
    {
        $administrador = $this->administrador();
        $consultorio = $this->consultorio('Consultorio 3');
        $bloque = $this->bloqueEn($consultorio);

        $this->actingAs($administrador)
            ->put(route('consultorios.update', $consultorio), ['nombre' => 'Sala de pediatría'])
            ->assertRedirect(route('consultorios.index'));

        $this->assertSame('Sala de pediatría', $consultorio->fresh()->nombre);
        $this->assertSame($consultorio->id, $bloque->fresh()->consultorio_id, 'el bloque sigue enlazado');

        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'consultorio_modificado')->firstOrFail();
        $this->assertSame('Consultorio 3 → Sala de pediatría', $registro->detalle);
    }

    public function test_al_modificar_puede_conservar_su_nombre_pero_no_tomar_el_de_otro(): void
    {
        $this->consultorio('Consultorio 1');
        $consultorio = $this->consultorio('Consultorio 2');
        $this->actingAs($this->administrador());

        $this->put(route('consultorios.update', $consultorio), ['nombre' => 'consultorio 2'])->assertSessionDoesntHaveErrors();
        $this->put(route('consultorios.update', $consultorio), ['nombre' => 'Consultorio 1'])->assertSessionHasErrors('nombre');

        $this->assertSame('consultorio 2', $consultorio->fresh()->nombre);
    }

    public function test_el_formulario_de_edicion_trae_el_nombre_actual(): void
    {
        $consultorio = $this->consultorio('Consultorio 7');

        $this->actingAs($this->administrador())
            ->get(route('consultorios.edit', $consultorio))
            ->assertOk()
            ->assertSee('value="Consultorio 7"', false);
    }

    // --- Desactivar y reactivar ---------------------------------------------------------

    public function test_desactivar_un_consultorio_sin_bloques_no_lo_elimina_y_se_puede_reactivar(): void
    {
        $administrador = $this->administrador();
        $consultorio = $this->consultorio();
        $this->actingAs($administrador);

        $this->patch(route('consultorios.estado', $consultorio), ['activo' => 0])
            ->assertRedirect(route('consultorios.index'));
        $this->assertFalse($consultorio->fresh()->estado);

        $this->patch(route('consultorios.estado', $consultorio), ['activo' => 1]);
        $this->assertTrue($consultorio->fresh()->estado);

        $this->assertSame(1, Bitacora::where('accion', 'consultorio_desactivado')->count());
        $this->assertSame(1, Bitacora::where('accion', 'consultorio_reactivado')->count());
    }

    public function test_no_se_puede_desactivar_un_consultorio_que_usan_bloques_de_atencion(): void
    {
        $consultorio = $this->consultorio('Consultorio 3');
        $this->bloqueEn($consultorio);

        $this->actingAs($this->administrador())
            ->from(route('consultorios.index'))
            ->patch(route('consultorios.estado', $consultorio), ['activo' => 0])
            ->assertRedirect(route('consultorios.index'))
            ->assertSessionHas('error', 'No se puede desactivar el Consultorio 3: lo usan 1 bloque de atención. Asígneles otro consultorio antes.');

        $this->assertTrue($consultorio->fresh()->estado);
        $this->assertSame(0, Bitacora::where('accion', 'consultorio_desactivado')->count());
    }

    public function test_desactivar_vuelve_a_la_pestaña_desde_la_que_se_hizo(): void
    {
        $consultorio = $this->consultorio();

        $this->actingAs($this->administrador())
            ->patch(route('consultorios.estado', $consultorio), ['activo' => 0, 'vista' => 'todos'])
            ->assertRedirect(route('consultorios.index', ['estado' => 'todos']));
    }
}
