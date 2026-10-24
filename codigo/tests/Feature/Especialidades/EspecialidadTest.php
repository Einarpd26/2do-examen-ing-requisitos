<?php

namespace Tests\Feature\Especialidades;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Persona;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\EspecialidadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-06: gestionar especialidades (RF04).
 */
class EspecialidadTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private function administrador(): Usuario
    {
        return $this->crearCuenta('ADMINISTRADOR');
    }

    private function datos(array $reemplazo = []): array
    {
        return array_merge([
            'nombre' => 'Pediatría',
            'descripcion' => 'Atención de niños y adolescentes',
            'duracion_min' => 30,
        ], $reemplazo);
    }

    private function crearEspecialidad(array $datos = []): Especialidad
    {
        return Especialidad::create($this->datos($datos))->refresh();
    }

    // --- Permisos -----------------------------------------------------------------------

    public function test_solo_el_administrador_gestiona_especialidades(): void
    {
        $especialidad = $this->crearEspecialidad();

        foreach (['RECEPCIONISTA', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->get(route('especialidades.index'))->assertForbidden();
            $this->get(route('especialidades.create'))->assertForbidden();
            $this->post(route('especialidades.store'), $this->datos(['nombre' => 'Otra']))->assertForbidden();
            $this->get(route('especialidades.edit', $especialidad))->assertForbidden();
            $this->put(route('especialidades.update', $especialidad), $this->datos())->assertForbidden();
            $this->patch(route('especialidades.estado', $especialidad), ['activa' => 0])->assertForbidden();
        }

        $this->assertTrue($especialidad->fresh()->estado);
        $this->assertSame(1, Especialidad::count());

        $this->actingAs($this->administrador())->get(route('especialidades.index'))->assertOk();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('especialidades.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_del_administrador_enlaza_a_especialidades(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('inicio'))
            ->assertSee(route('especialidades.index'), false);
    }

    // --- Listado --------------------------------------------------------------------------

    public function test_el_listado_muestra_nombre_duracion_estado_y_cantidad_de_medicos(): void
    {
        $general = $this->crearEspecialidad(['nombre' => 'Medicina general', 'duracion_min' => 20]);
        $this->crearEspecialidad(['nombre' => 'Odontología', 'duracion_min' => 45, 'estado' => false]);

        $medico = $this->crearMedico();
        $medico->especialidades()->attach($general->id);

        $this->actingAs($this->administrador())
            ->get(route('especialidades.index', ['estado' => 'todas']))
            ->assertOk()
            ->assertSeeInOrder(['Medicina general', '20 min', 'Activa'])
            ->assertSeeInOrder(['Odontología', '45 min', 'Inactiva']);

        $this->assertSame(1, Especialidad::withCount('medicos')->where('nombre', 'Medicina general')->firstOrFail()->medicos_count);
    }

    public function test_cada_pestaña_vacia_avisa_con_su_propio_mensaje(): void
    {
        $this->actingAs($this->administrador());

        $this->get(route('especialidades.index'))->assertSee('No hay especialidades activas.');
        $this->get(route('especialidades.index', ['estado' => 'inactivas']))->assertSee('No hay especialidades inactivas.');
        $this->get(route('especialidades.index', ['estado' => 'todas']))->assertSee('Todavía no hay especialidades registradas.');
    }

    // --- Pestañas Activas / Inactivas / Todas --------------------------------------------

    private function crearActivasEInactivas(): void
    {
        $this->crearEspecialidad(['nombre' => 'Medicina general']);
        $this->crearEspecialidad(['nombre' => 'Pediatría']);
        $this->crearEspecialidad(['nombre' => 'Odontología', 'estado' => false]);
    }

    public function test_por_defecto_muestra_solo_las_activas_y_avisa_cuantas_hay_ocultas(): void
    {
        $this->crearActivasEInactivas();

        $this->actingAs($this->administrador())
            ->get(route('especialidades.index'))
            ->assertOk()
            ->assertSee('Medicina general')
            ->assertSee('Pediatría')
            ->assertDontSee('Odontología')
            ->assertSeeInOrder(['Activas', '2', 'Inactivas', '1', 'Todas', '3'])
            ->assertSee('aria-current="page"', false);
    }

    public function test_las_pestañas_usan_el_componente_con_subrayado_y_contador(): void
    {
        $this->crearActivasEInactivas();

        $this->actingAs($this->administrador())
            ->get(route('especialidades.index'))
            ->assertSee('nav nav-underline pestanas', false)
            ->assertSee('class="pestanas-cuenta"', false)
            ->assertSee('aria-label="Estado de las especialidades"', false)
            ->assertDontSee('nav-pills', false);
    }

    public function test_solo_la_pestaña_seleccionada_se_marca_como_actual(): void
    {
        $this->crearActivasEInactivas();
        $this->actingAs($this->administrador());

        foreach (['activas' => 'Activas', 'inactivas' => 'Inactivas', 'todas' => 'Todas'] as $clave => $etiqueta) {
            $html = $this->get(route('especialidades.index', ['estado' => $clave]))->getContent();

            $this->assertSame(1, substr_count($html, 'aria-current="page"'), "pestaña {$clave}");
            $this->assertMatchesRegularExpression('/nav-link active"\s+aria-current="page"\s*>\s*'.$etiqueta.'/', $html);
        }
    }

    public function test_la_pestaña_inactivas_muestra_solo_las_inactivas(): void
    {
        $this->crearActivasEInactivas();

        $this->actingAs($this->administrador())
            ->get(route('especialidades.index', ['estado' => 'inactivas']))
            ->assertSee('Odontología')
            ->assertDontSee('Medicina general')
            ->assertDontSee('Pediatría');
    }

    public function test_la_pestaña_todas_muestra_activas_e_inactivas(): void
    {
        $this->crearActivasEInactivas();

        $this->actingAs($this->administrador())
            ->get(route('especialidades.index', ['estado' => 'todas']))
            ->assertSee('Medicina general')
            ->assertSee('Pediatría')
            ->assertSee('Odontología');
    }

    public function test_una_pestaña_desconocida_cae_en_activas(): void
    {
        $this->crearActivasEInactivas();

        $this->actingAs($this->administrador())
            ->get(route('especialidades.index', ['estado' => 'cualquier-cosa']))
            ->assertOk()
            ->assertSee('Medicina general')
            ->assertDontSee('Odontología');
    }

    public function test_cada_fila_ofrece_solo_la_accion_que_corresponde_a_su_estado(): void
    {
        $this->crearActivasEInactivas();
        $this->actingAs($this->administrador());

        $this->get(route('especialidades.index'))
            ->assertSee('Desactivar')
            ->assertDontSee('Reactivar');

        $this->get(route('especialidades.index', ['estado' => 'inactivas']))
            ->assertSee('Reactivar')
            ->assertDontSee('Desactivar');

        $this->get(route('especialidades.index', ['estado' => 'todas']))
            ->assertSee('Desactivar')
            ->assertSee('Reactivar');
    }

    public function test_desactivar_vuelve_a_la_pestaña_desde_la_que_se_hizo(): void
    {
        $especialidad = $this->crearEspecialidad();
        $this->actingAs($this->administrador());

        $this->patch(route('especialidades.estado', $especialidad), ['activa' => 0])
            ->assertRedirect(route('especialidades.index'))
            ->assertSessionHas('exito');
        $this->assertStringContainsString('pestaña Inactivas', session('exito'));

        $this->patch(route('especialidades.estado', $especialidad), ['activa' => 1, 'vista' => 'inactivas'])
            ->assertRedirect(route('especialidades.index', ['estado' => 'inactivas']));
        $this->assertStringContainsString('pestaña Activas', session('exito'));

        $this->patch(route('especialidades.estado', $especialidad), ['activa' => 0, 'vista' => 'todas'])
            ->assertRedirect(route('especialidades.index', ['estado' => 'todas']));
    }

    public function test_una_pestaña_alterada_en_el_formulario_cae_en_la_de_activas(): void
    {
        $especialidad = $this->crearEspecialidad();

        $this->actingAs($this->administrador())
            ->patch(route('especialidades.estado', $especialidad), ['activa' => 0, 'vista' => 'https://otro.sitio'])
            ->assertRedirect(route('especialidades.index'));
    }

    public function test_los_contadores_cambian_al_desactivar(): void
    {
        $especialidad = $this->crearEspecialidad();
        $this->actingAs($this->administrador());

        $this->get(route('especialidades.index'))->assertSeeInOrder(['Activas', '1', 'Inactivas', '0']);

        $this->patch(route('especialidades.estado', $especialidad), ['activa' => 0]);

        $this->get(route('especialidades.index'))
            ->assertSeeInOrder(['Activas', '0', 'Inactivas', '1'])
            ->assertSee('No hay especialidades activas.');
    }

    // --- Registrar -----------------------------------------------------------------------

    public function test_registra_una_especialidad_y_queda_en_la_bitacora(): void
    {
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->post(route('especialidades.store'), $this->datos(['duracion_min' => 45]))
            ->assertRedirect(route('especialidades.index'))
            ->assertSessionHas('exito');

        $especialidad = Especialidad::firstOrFail();
        $this->assertSame('Pediatría', $especialidad->nombre);
        $this->assertSame('Atención de niños y adolescentes', $especialidad->descripcion);
        $this->assertSame(45, $especialidad->duracion_min);
        $this->assertTrue($especialidad->estado);

        $this->assertSame(1, Bitacora::where('usuario_id', $administrador->id)->where('accion', 'especialidad_creada')->count());
    }

    public function test_la_descripcion_es_opcional(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('especialidades.store'), ['nombre' => 'Cardiología', 'duracion_min' => 30])
            ->assertSessionDoesntHaveErrors();

        $this->assertNull(Especialidad::firstOrFail()->descripcion);
    }

    public function test_el_nombre_se_guarda_sin_espacios_de_mas(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('especialidades.store'), $this->datos(['nombre' => '   Medicina    general  ']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Medicina general', Especialidad::firstOrFail()->nombre);
    }

    public function test_rechaza_un_nombre_repetido_sin_distinguir_mayusculas_ni_espacios(): void
    {
        $this->crearEspecialidad(['nombre' => 'Medicina general']);
        $this->actingAs($this->administrador());

        foreach (['medicina general', 'MEDICINA GENERAL', '  Medicina   General  '] as $repetido) {
            $this->post(route('especialidades.store'), $this->datos(['nombre' => $repetido]))
                ->assertSessionHasErrors('nombre');
            $this->assertSame(EspecialidadService::MENSAJE_NOMBRE_REPETIDO, $this->mensajeDeError('nombre'));
        }

        $this->assertSame(1, Especialidad::count());
    }

    public function test_el_nombre_repetido_tambien_se_detecta_con_acentos_en_mayusculas(): void
    {
        $this->crearEspecialidad(['nombre' => 'Pediatría']);

        $this->actingAs($this->administrador())
            ->post(route('especialidades.store'), $this->datos(['nombre' => 'PEDIATRÍA']))
            ->assertSessionHasErrors('nombre');
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        $this->actingAs($this->administrador());

        $casos = [
            'sin nombre' => [['nombre' => ''], 'nombre'],
            'nombre solo con espacios' => [['nombre' => '    '], 'nombre'],
            'nombre muy largo' => [['nombre' => str_repeat('a', 101)], 'nombre'],
            'descripcion muy larga' => [['descripcion' => str_repeat('a', 301)], 'descripcion'],
            'sin duracion' => [['duracion_min' => ''], 'duracion_min'],
            'duracion menor a 5' => [['duracion_min' => 4], 'duracion_min'],
            'duracion mayor a 240' => [['duracion_min' => 241], 'duracion_min'],
            'duracion con decimales' => [['duracion_min' => 30.5], 'duracion_min'],
            'duracion con texto' => [['duracion_min' => 'media hora'], 'duracion_min'],
            'duracion negativa' => [['duracion_min' => -30], 'duracion_min'],
        ];

        foreach ($casos as [$reemplazo, $campo]) {
            $this->post(route('especialidades.store'), $this->datos($reemplazo))->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, Especialidad::count());
    }

    public function test_acepta_los_limites_de_la_duracion(): void
    {
        $this->actingAs($this->administrador());

        $this->post(route('especialidades.store'), $this->datos(['nombre' => 'Mínima', 'duracion_min' => 5]))
            ->assertSessionDoesntHaveErrors();
        $this->post(route('especialidades.store'), $this->datos(['nombre' => 'Máxima', 'duracion_min' => 240]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Especialidad::count());
    }

    public function test_los_mensajes_salen_en_espanol(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('especialidades.store'), $this->datos(['duracion_min' => 3]));

        $this->assertSame('La duración de la cita debe estar entre 5 y 240 minutos.', $this->mensajeDeError('duracion_min'));
    }

    public function test_el_formulario_de_alta_propone_30_minutos(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('especialidades.create'))
            ->assertOk()
            ->assertSee('Registrar especialidad')
            ->assertSee('value="30"', false);
    }

    public function test_una_solicitud_simultanea_con_el_mismo_nombre_se_informa_como_regla_de_negocio(): void
    {
        $this->crearEspecialidad(['nombre' => 'Cardiología']);

        // Se salta la validación del formulario: simula que otra solicitud ganó la carrera.
        try {
            app(EspecialidadService::class)->registrar($this->datos(['nombre' => 'cardiología']), $this->administrador());
            $this->fail('Debió rechazar el nombre repetido.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertSame(EspecialidadService::MENSAJE_NOMBRE_REPETIDO, $e->getMessage());
        }

        $this->assertSame(1, Especialidad::count());
    }

    // --- Modificar -----------------------------------------------------------------------

    public function test_modifica_la_especialidad_y_queda_en_la_bitacora(): void
    {
        $administrador = $this->administrador();
        $especialidad = $this->crearEspecialidad(['nombre' => 'Pediatria', 'duracion_min' => 30]);

        $this->actingAs($administrador)
            ->put(route('especialidades.update', $especialidad), $this->datos([
                'nombre' => 'Pediatría', 'descripcion' => null, 'duracion_min' => 40,
            ]))
            ->assertRedirect(route('especialidades.index'));

        $especialidad->refresh();
        $this->assertSame('Pediatría', $especialidad->nombre);
        $this->assertNull($especialidad->descripcion);
        $this->assertSame(40, $especialidad->duracion_min);
        $this->assertSame(1, Bitacora::where('usuario_id', $administrador->id)->where('accion', 'especialidad_modificada')->count());
    }

    public function test_al_modificar_puede_conservar_su_nombre_pero_no_tomar_el_de_otra(): void
    {
        $this->crearEspecialidad(['nombre' => 'Ginecología']);
        $especialidad = $this->crearEspecialidad(['nombre' => 'Odontología']);
        $this->actingAs($this->administrador());

        $this->put(route('especialidades.update', $especialidad), $this->datos(['nombre' => 'Odontología', 'duracion_min' => 50]))
            ->assertSessionDoesntHaveErrors();

        $this->put(route('especialidades.update', $especialidad), $this->datos(['nombre' => 'ginecología']))
            ->assertSessionHasErrors('nombre');

        $this->assertSame('Odontología', $especialidad->fresh()->nombre);
    }

    public function test_cambiar_la_duracion_no_altera_las_citas_ya_agendadas(): void
    {
        $especialidad = $this->crearEspecialidad(['nombre' => 'Medicina general', 'duracion_min' => 30]);
        $medico = $this->crearMedico();
        $medico->especialidades()->attach($especialidad->id);

        $cita = Cita::create([
            'paciente_id' => $this->crearPaciente()->id,
            'medico_id' => $medico->id,
            'especialidad_id' => $especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => '2026-11-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '09:30',
        ])->refresh();

        $this->actingAs($this->administrador())
            ->put(route('especialidades.update', $especialidad), $this->datos([
                'nombre' => 'Medicina general', 'duracion_min' => 60,
            ]))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(60, $especialidad->fresh()->duracion_min);
        $cita->refresh();
        $this->assertSame('09:00:00', $cita->hora_inicio);
        $this->assertSame('09:30:00', $cita->hora_fin, 'la cita ya agendada conserva su horario');
    }

    public function test_el_formulario_de_edicion_trae_los_datos_actuales(): void
    {
        $especialidad = $this->crearEspecialidad(['nombre' => 'Odontología', 'duracion_min' => 45]);

        $this->actingAs($this->administrador())
            ->get(route('especialidades.edit', $especialidad))
            ->assertOk()
            ->assertSee('value="Odontología"', false)
            ->assertSee('value="45"', false)
            ->assertSee('Guardar cambios')
            ->assertSee('solo a las citas nuevas');
    }

    // --- Desactivar y reactivar ----------------------------------------------------------

    public function test_desactivar_no_elimina_y_se_puede_reactivar(): void
    {
        $administrador = $this->administrador();
        $especialidad = $this->crearEspecialidad();
        $this->actingAs($administrador);

        $this->patch(route('especialidades.estado', $especialidad), ['activa' => 0])
            ->assertRedirect(route('especialidades.index'));

        $this->assertFalse($especialidad->fresh()->estado);
        $this->assertSame(1, Especialidad::count(), 'desactivar no elimina la especialidad');

        $this->patch(route('especialidades.estado', $especialidad), ['activa' => 1]);
        $this->assertTrue($especialidad->fresh()->estado);

        $acciones = Bitacora::where('usuario_id', $administrador->id)->orderBy('id')->pluck('accion')->all();
        $this->assertSame(['especialidad_desactivada', 'especialidad_reactivada'], $acciones);
    }

    public function test_desactivar_una_especialidad_conserva_sus_medicos_y_citas(): void
    {
        $especialidad = $this->crearEspecialidad(['nombre' => 'Cardiología']);
        $medico = $this->crearMedico();
        $medico->especialidades()->attach($especialidad->id);

        $this->actingAs($this->administrador())
            ->patch(route('especialidades.estado', $especialidad), ['activa' => 0]);

        $this->assertSame(1, $medico->especialidades()->count(), 'el vínculo del médico se conserva');
    }

    // --- Base de datos -------------------------------------------------------------------

    public function test_la_base_rechaza_un_nombre_repetido_aunque_se_salte_la_aplicacion(): void
    {
        $this->crearEspecialidad(['nombre' => 'Cardiología']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Especialidad::create($this->datos(['nombre' => '  CARDIOLOGÍA ']));
    }

    /** Persona con ficha de médico, sin cuenta. */
    private function crearMedico(): Medico
    {
        $persona = Persona::create([
            'ci' => (string) random_int(2000000, 2999999),
            'primer_nombre' => 'Rosa',
            'apellido_paterno' => 'Luna',
        ]);

        return Medico::create([
            'persona_id' => $persona->id,
            'nro_registro_profesional' => 'R-'.random_int(1000, 9999),
        ])->refresh();
    }
}
