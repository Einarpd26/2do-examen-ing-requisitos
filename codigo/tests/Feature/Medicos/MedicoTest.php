<?php

namespace Tests\Feature\Medicos;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Persona;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\MedicoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-05: gestionar médicos (RF03, RN02, RN04, RN11).
 */
class MedicoTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private function administrador(): Usuario
    {
        return $this->crearCuenta('ADMINISTRADOR');
    }

    private function especialidad(string $nombre = 'Pediatría', bool $activa = true): Especialidad
    {
        return Especialidad::create(['nombre' => $nombre, 'duracion_min' => 30, 'estado' => $activa])->refresh();
    }

    /** Datos válidos del formulario de médico, con CI, correo y registro nuevos en cada llamada. */
    private function datos(array $especialidades, array $reemplazo = []): array
    {
        $n = ++self::$secuenciaCi;

        return array_merge([
            'ci' => (string) $n,
            'primer_nombre' => 'Rosa',
            'segundo_nombre' => 'María',
            'apellido_paterno' => 'Luna',
            'apellido_materno' => 'Vega',
            'telefono' => '70011122',
            'correo' => "rosa{$n}@clinica.test",
            'nro_registro_profesional' => "RP-{$n}",
            'especialidades' => $especialidades,
        ], $reemplazo);
    }

    // --- Permisos -----------------------------------------------------------------------

    public function test_solo_el_administrador_gestiona_medicos(): void
    {
        $especialidad = $this->especialidad();
        $medico = $this->crearFichaMedico([], [], [$especialidad->id]);

        foreach (['RECEPCIONISTA', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->get(route('medicos.index'))->assertForbidden();
            $this->get(route('medicos.create'))->assertForbidden();
            $this->post(route('medicos.store'), $this->datos([$especialidad->id]))->assertForbidden();
            $this->get(route('medicos.edit', $medico))->assertForbidden();
            $this->put(route('medicos.update', $medico), $this->datos([$especialidad->id]))->assertForbidden();
            $this->patch(route('medicos.estado', $medico), ['activo' => 0])->assertForbidden();
        }

        $this->assertTrue($medico->fresh()->estado);
        $this->assertSame(1, Medico::count());

        $this->actingAs($this->administrador())->get(route('medicos.index'))->assertOk();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('medicos.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_del_administrador_enlaza_a_medicos(): void
    {
        $this->actingAs($this->administrador())->get(route('inicio'))->assertSee(route('medicos.index'), false);
    }

    // --- Listado ------------------------------------------------------------------------

    public function test_el_listado_muestra_codigo_nombre_registro_especialidades_y_estado(): void
    {
        $pediatria = $this->especialidad('Pediatría');
        $general = $this->especialidad('Medicina general');
        $medico = $this->crearFichaMedico(
            ['ci' => '5551111', 'primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna'],
            ['nro_registro_profesional' => 'MP-100'],
            [$pediatria->id, $general->id]
        );

        $this->actingAs($this->administrador())
            ->get(route('medicos.index'))
            ->assertOk()
            ->assertSee($medico->codigo)
            ->assertSee('Rosa Luna')
            ->assertSee('CI 5551111')
            ->assertSee('MP-100')
            ->assertSee('Pediatría')
            ->assertSee('Medicina general')
            ->assertSee('Activo')
            ->assertSee('Sin cuenta');
    }

    public function test_el_listado_vacio_avisa_en_cada_pestaña(): void
    {
        $this->actingAs($this->administrador());

        $this->get(route('medicos.index'))->assertSee('No hay médicos activos.');
        $this->get(route('medicos.index', ['estado' => 'inactivas']))->assertSee('No hay médicos inactivos.');
        $this->get(route('medicos.index', ['estado' => 'todas']))->assertSee('Todavía no hay médicos registrados.');
    }

    // --- Búsqueda -----------------------------------------------------------------------

    public function test_busca_por_nombre_apellido_especialidad_ci_registro_y_codigo(): void
    {
        $pediatria = $this->especialidad('Pediatría');
        $odontologia = $this->especialidad('Odontología');
        $rosa = $this->crearFichaMedico(
            ['ci' => '5551111', 'primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna'],
            ['nro_registro_profesional' => 'MP-100'], [$pediatria->id]
        );
        $this->crearFichaMedico(
            ['ci' => '6662222', 'primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil'],
            ['nro_registro_profesional' => 'OD-200'], [$odontologia->id]
        );
        $this->actingAs($this->administrador());

        $consultas = [
            'rosa' => ['Rosa Luna', 'Pedro Gil'],
            'gil' => ['Pedro Gil', 'Rosa Luna'],
            'odontología' => ['Pedro Gil', 'Rosa Luna'],
            'pediatr' => ['Rosa Luna', 'Pedro Gil'],
            '6662' => ['Pedro Gil', 'Rosa Luna'],
            'mp-1' => ['Rosa Luna', 'Pedro Gil'],
            $rosa->codigo => ['Rosa Luna', 'Pedro Gil'],
        ];

        foreach ($consultas as $texto => [$debeVerse, $noDebeVerse]) {
            $this->get(route('medicos.index', ['q' => $texto]))
                ->assertSee($debeVerse)
                ->assertDontSee($noDebeVerse);
        }
    }

    public function test_los_comodines_de_la_busqueda_se_toman_como_texto(): void
    {
        $this->crearFichaMedico([], [], [$this->especialidad()->id]);

        $this->actingAs($this->administrador())
            ->get(route('medicos.index', ['q' => '%']))
            ->assertOk()
            ->assertSee('No hay médicos que coincidan');
    }

    public function test_sin_busqueda_se_ven_los_activos_y_con_busqueda_tambien_los_inactivos(): void
    {
        $especialidad = $this->especialidad();
        $this->crearFichaMedico(['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna'], [], [$especialidad->id]);
        $this->crearFichaMedico(['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil'], ['estado' => false], [$especialidad->id]);
        $this->actingAs($this->administrador());

        $this->get(route('medicos.index'))->assertSee('Rosa Luna')->assertDontSee('Pedro Gil');

        $this->get(route('medicos.index', ['q' => 'gil']))
            ->assertSee('Pedro Gil')
            ->assertSee('La búsqueda incluye a los médicos inactivos');
    }

    public function test_con_busqueda_se_puede_volver_a_filtrar_por_pestaña(): void
    {
        $especialidad = $this->especialidad();
        $this->crearFichaMedico(['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil'], [], [$especialidad->id]);
        $this->crearFichaMedico(['primer_nombre' => 'Pablo', 'apellido_paterno' => 'Gil'], ['estado' => false], [$especialidad->id]);
        $this->actingAs($this->administrador());

        $this->get(route('medicos.index', ['q' => 'gil', 'estado' => 'activas']))
            ->assertSee('Pedro Gil')->assertDontSee('Pablo Gil');

        $this->get(route('medicos.index', ['q' => 'gil', 'estado' => 'inactivas']))
            ->assertSee('Pablo Gil')->assertDontSee('Pedro Gil');
    }

    // --- Pestañas Activos / Inactivos / Todos -------------------------------------------

    private function dosActivosYUnInactivo(): void
    {
        $e = $this->especialidad();
        $this->crearFichaMedico(['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna'], [], [$e->id]);
        $this->crearFichaMedico(['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil'], [], [$e->id]);
        $this->crearFichaMedico(['primer_nombre' => 'Lucía', 'apellido_paterno' => 'Rojas'], ['estado' => false], [$e->id]);
    }

    public function test_por_defecto_muestra_solo_los_activos_y_avisa_cuantos_hay_ocultos(): void
    {
        $this->dosActivosYUnInactivo();

        $this->actingAs($this->administrador())
            ->get(route('medicos.index'))
            ->assertSee('Rosa Luna')
            ->assertSee('Pedro Gil')
            ->assertDontSee('Lucía Rojas')
            ->assertSeeInOrder(['Activos', '2', 'Inactivos', '1', 'Todos', '3'])
            ->assertSee('nav nav-underline pestanas', false);
    }

    public function test_las_pestañas_inactivos_y_todos_filtran_bien(): void
    {
        $this->dosActivosYUnInactivo();
        $this->actingAs($this->administrador());

        $this->get(route('medicos.index', ['estado' => 'inactivas']))
            ->assertSee('Lucía Rojas')->assertDontSee('Rosa Luna')->assertDontSee('Pedro Gil');

        $this->get(route('medicos.index', ['estado' => 'todas']))
            ->assertSee('Lucía Rojas')->assertSee('Rosa Luna')->assertSee('Pedro Gil');
    }

    public function test_una_pestaña_desconocida_cae_en_activos(): void
    {
        $this->dosActivosYUnInactivo();

        $this->actingAs($this->administrador())
            ->get(route('medicos.index', ['estado' => 'cualquier-cosa']))
            ->assertSee('Rosa Luna')
            ->assertDontSee('Lucía Rojas');
    }

    public function test_cada_fila_ofrece_solo_la_accion_que_corresponde_y_la_inactiva_se_atenua(): void
    {
        $this->dosActivosYUnInactivo();
        $this->actingAs($this->administrador());

        $this->get(route('medicos.index'))->assertSee('Desactivar')->assertDontSee('Reactivar');

        $html = $this->get(route('medicos.index', ['estado' => 'inactivas']))
            ->assertSee('Reactivar')->assertDontSee('Desactivar')->getContent();
        $this->assertSame(1, substr_count($html, 'fila-inactiva'));
    }

    public function test_los_contadores_reflejan_la_busqueda(): void
    {
        $this->dosActivosYUnInactivo();

        $this->actingAs($this->administrador())
            ->get(route('medicos.index', ['q' => 'gil']))
            ->assertSeeInOrder(['Activos', '1', 'Inactivos', '0', 'Todos', '1']);
    }

    // --- Registrar ----------------------------------------------------------------------

    public function test_registra_el_medico_con_su_persona_y_sus_especialidades(): void
    {
        $administrador = $this->administrador();
        $pediatria = $this->especialidad('Pediatría');
        $general = $this->especialidad('Medicina general');

        $this->actingAs($administrador)
            ->post(route('medicos.store'), $this->datos([$pediatria->id, $general->id], ['ci' => '4567890', 'nro_registro_profesional' => 'MP-777']))
            ->assertRedirect(route('medicos.index'))
            ->assertSessionHas('exito', 'Médico registrado.');

        $medico = Medico::with('persona', 'especialidades')->firstOrFail();
        $this->assertSame('4567890', $medico->persona->ci);
        $this->assertSame('Rosa María Luna Vega', $medico->persona->nombre_completo);
        $this->assertSame('MP-777', $medico->nro_registro_profesional);
        $this->assertTrue($medico->estado);
        $this->assertEqualsCanonicalizing(['Pediatría', 'Medicina general'], $medico->especialidades->pluck('nombre')->all());
        $this->assertNull($medico->persona->usuario);

        $this->assertSame(1, Bitacora::where('usuario_id', $administrador->id)->where('accion', 'medico_creado')->count());
    }

    public function test_el_segundo_nombre_y_el_apellido_materno_son_opcionales(): void
    {
        $e = $this->especialidad();

        $this->actingAs($this->administrador())
            ->post(route('medicos.store'), $this->datos([$e->id], ['segundo_nombre' => '', 'apellido_materno' => '']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Rosa Luna', Medico::firstOrFail()->persona->nombre_completo);
    }

    public function test_normaliza_el_ci_el_correo_el_telefono_y_el_registro_profesional(): void
    {
        $e = $this->especialidad();

        $this->actingAs($this->administrador())->post(route('medicos.store'), $this->datos([$e->id], [
            'ci' => ' 7654321-1a ',
            'correo' => '  ROSA@Clinica.COM ',
            'telefono' => '700 111-22',
            'nro_registro_profesional' => '  mp   1234 ',
        ]))->assertSessionDoesntHaveErrors();

        $medico = Medico::with('persona')->firstOrFail();
        $this->assertSame('7654321-1A', $medico->persona->ci);
        $this->assertSame('rosa@clinica.com', $medico->persona->correo);
        $this->assertSame('70011122', $medico->persona->telefono);
        $this->assertSame('MP 1234', $medico->nro_registro_profesional);
    }

    public function test_rechaza_un_ci_que_ya_tiene_ficha_de_medico(): void
    {
        $e = $this->especialidad();
        $this->crearFichaMedico(['ci' => '9990001'], [], [$e->id]);

        $this->actingAs($this->administrador())
            ->post(route('medicos.store'), $this->datos([$e->id], ['ci' => '9990001']))
            ->assertSessionHasErrors('ci');

        $this->assertSame(1, Medico::count());
    }

    public function test_rechaza_un_registro_profesional_repetido_aunque_cambien_mayusculas_y_espacios(): void
    {
        $e = $this->especialidad();
        $this->crearFichaMedico([], ['nro_registro_profesional' => 'MP 100'], [$e->id]);
        $this->actingAs($this->administrador());

        foreach (['MP 100', 'mp 100', '  mp   100  '] as $repetido) {
            $this->post(route('medicos.store'), $this->datos([$e->id], ['nro_registro_profesional' => $repetido]))
                ->assertSessionHasErrors('nro_registro_profesional');
        }

        $this->assertSame(1, Medico::count());
    }

    public function test_reutiliza_la_persona_que_existe_sin_ficha_de_medico_y_no_toca_sus_datos(): void
    {
        $e = $this->especialidad();
        $paciente = $this->crearPaciente(['ci' => '8880001', 'primer_nombre' => 'Ana', 'correo' => 'ana@mail.com']);

        $respuesta = $this->actingAs($this->administrador())->post(route('medicos.store'), $this->datos([$e->id], [
            'ci' => '8880001',
            'primer_nombre' => 'Nombre distinto',
            'correo' => 'ana@mail.com',
        ]));

        $respuesta->assertRedirect(route('medicos.index'))->assertSessionHas('exito');

        $medico = Medico::firstOrFail();
        $this->assertSame($paciente->persona_id, $medico->persona_id);
        $this->assertSame(1, Persona::where('ci', '8880001')->count());
        $this->assertSame('Ana Paz', $medico->persona->fresh()->nombre_completo, 'los datos personales no se modifican');
        $this->assertSame(1, \App\Models\Paciente::count(), 'la ficha de paciente sigue intacta');
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        $e = $this->especialidad();
        $inactiva = $this->especialidad('Cardiología', false);
        $this->crearCuenta('RECEPCIONISTA', ['correo' => 'ocupado@clinica.test']);
        $this->actingAs($this->administrador());

        $casos = [
            'sin nombre' => [['primer_nombre' => ''], 'primer_nombre'],
            'sin apellido paterno' => [['apellido_paterno' => ''], 'apellido_paterno'],
            'sin ci' => [['ci' => ''], 'ci'],
            'ci con letras' => [['ci' => 'ABC123'], 'ci'],
            'ci muy corto' => [['ci' => '123'], 'ci'],
            'sin telefono' => [['telefono' => ''], 'telefono'],
            'telefono invalido' => [['telefono' => '12'], 'telefono'],
            'sin correo' => [['correo' => ''], 'correo'],
            'correo mal escrito' => [['correo' => 'no-es-correo'], 'correo'],
            'correo de otra persona, con otras mayusculas' => [['correo' => 'OCUPADO@clinica.test'], 'correo'],
            'sin registro profesional' => [['nro_registro_profesional' => ''], 'nro_registro_profesional'],
            'registro profesional muy largo' => [['nro_registro_profesional' => str_repeat('A', 31)], 'nro_registro_profesional'],
            'sin especialidades' => [['especialidades' => []], 'especialidades'],
            'especialidad inexistente' => [['especialidades' => [999999]], 'especialidades.0'],
            'especialidad repetida' => [['especialidades' => [$e->id, $e->id]], 'especialidades.0'],
            'especialidad desactivada' => [['especialidades' => [$inactiva->id]], 'especialidades.0'],
        ];

        foreach ($casos as $nombre => [$reemplazo, $campo]) {
            $datos = $this->datos([$e->id], $reemplazo);
            if (array_key_exists('especialidades', $reemplazo) && $reemplazo['especialidades'] === []) {
                unset($datos['especialidades']);
            }

            $this->post(route('medicos.store'), $datos)->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, Medico::count());
    }

    public function test_los_mensajes_salen_en_espanol(): void
    {
        $e = $this->especialidad();
        $this->actingAs($this->administrador());

        $this->post(route('medicos.store'), $this->datos([$e->id], ['primer_nombre' => '']));
        $this->assertSame('El campo primer nombre es obligatorio.', $this->mensajeDeError('primer_nombre'));

        $this->post(route('medicos.store'), $this->datos([], []));
        $this->assertSame('Elija al menos una especialidad.', $this->mensajeDeError('especialidades'));
    }

    public function test_el_formulario_de_alta_ofrece_solo_las_especialidades_activas(): void
    {
        $this->especialidad('Pediatría');
        $this->especialidad('Cardiología', false);

        $this->actingAs($this->administrador())
            ->get(route('medicos.create'))
            ->assertOk()
            ->assertSee('Pediatría')
            ->assertDontSee('Cardiología');
    }

    public function test_si_no_hay_especialidades_activas_el_formulario_avisa(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('medicos.create'))
            ->assertSee('No hay especialidades activas');
    }

    public function test_una_solicitud_simultanea_con_el_mismo_registro_se_informa_como_regla_de_negocio(): void
    {
        $e = $this->especialidad();
        $this->crearFichaMedico([], ['nro_registro_profesional' => 'MP-1'], [$e->id]);

        // Se salta la validación del formulario: simula que otra solicitud ganó la carrera.
        try {
            app(MedicoService::class)->registrar($this->datos([$e->id], ['nro_registro_profesional' => 'MP-1']), $this->administrador());
            $this->fail('Debió rechazar el registro repetido.');
        } catch (ReglaDeNegocioException $excepcion) {
            $this->assertStringContainsString('registro profesional', $excepcion->getMessage());
        }

        $this->assertSame(1, Medico::count());
        $this->assertSame(1, Persona::whereHas('medico')->count(), 'no quedó una persona nueva a medias');
    }

    // --- Modificar ----------------------------------------------------------------------

    public function test_modifica_la_persona_el_registro_y_las_especialidades(): void
    {
        $administrador = $this->administrador();
        $pediatria = $this->especialidad('Pediatría');
        $general = $this->especialidad('Medicina general');
        $medico = $this->crearFichaMedico(['ci' => '3330001'], [], [$pediatria->id]);

        $this->actingAs($administrador)
            ->put(route('medicos.update', $medico), $this->datos([$general->id], [
                'ci' => '3330001', 'primer_nombre' => 'Lucía', 'nro_registro_profesional' => 'MP-999',
            ]))
            ->assertRedirect(route('medicos.index'));

        $medico->refresh()->load('persona', 'especialidades');
        $this->assertSame('Lucía', $medico->persona->primer_nombre);
        $this->assertSame('MP-999', $medico->nro_registro_profesional);
        $this->assertSame(['Medicina general'], $medico->especialidades->pluck('nombre')->all());
        $this->assertSame(1, Bitacora::where('usuario_id', $administrador->id)->where('accion', 'medico_modificado')->count());
    }

    public function test_al_modificar_puede_conservar_lo_propio_pero_no_tomar_lo_de_otro(): void
    {
        $e = $this->especialidad();
        $this->crearFichaMedico(['ci' => '3330001', 'correo' => 'otro@clinica.test'], ['nro_registro_profesional' => 'MP-OTRO'], [$e->id]);
        $medico = $this->crearFichaMedico(['ci' => '3330002', 'correo' => 'propio@clinica.test'], ['nro_registro_profesional' => 'MP-PROPIO'], [$e->id]);
        $this->actingAs($this->administrador());

        $propio = ['ci' => '3330002', 'correo' => 'propio@clinica.test', 'nro_registro_profesional' => 'MP-PROPIO'];
        $this->put(route('medicos.update', $medico), $this->datos([$e->id], $propio))->assertSessionDoesntHaveErrors();

        $this->put(route('medicos.update', $medico), $this->datos([$e->id], array_merge($propio, ['ci' => '3330001'])))
            ->assertSessionHasErrors('ci');
        $this->put(route('medicos.update', $medico), $this->datos([$e->id], array_merge($propio, ['correo' => 'OTRO@clinica.test'])))
            ->assertSessionHasErrors('correo');
        $this->put(route('medicos.update', $medico), $this->datos([$e->id], array_merge($propio, ['nro_registro_profesional' => 'mp-otro'])))
            ->assertSessionHasErrors('nro_registro_profesional');
    }

    public function test_puede_conservar_una_especialidad_desactivada_pero_no_agregar_otra(): void
    {
        $activa = $this->especialidad('Pediatría');
        $desactivada = $this->especialidad('Odontología', false);
        $otraDesactivada = $this->especialidad('Cardiología', false);
        $medico = $this->crearFichaMedico(['ci' => '3330003'], [], [$activa->id, $desactivada->id]);
        $this->actingAs($this->administrador());

        $this->put(route('medicos.update', $medico), $this->datos([$activa->id, $desactivada->id], ['ci' => '3330003']))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(2, $medico->fresh()->especialidades()->count());

        $this->put(route('medicos.update', $medico), $this->datos([$activa->id, $desactivada->id, $otraDesactivada->id], ['ci' => '3330003']))
            ->assertSessionHasErrors('especialidades.2');
    }

    public function test_el_formulario_de_edicion_marca_las_especialidades_del_medico_y_muestra_la_desactivada_que_ya_tiene(): void
    {
        $activa = $this->especialidad('Pediatría');
        $this->especialidad('Medicina general');
        $desactivada = $this->especialidad('Odontología', false);
        $this->especialidad('Cardiología', false);
        $medico = $this->crearFichaMedico(['ci' => '3330004'], ['nro_registro_profesional' => 'MP-1234'], [$activa->id, $desactivada->id]);

        $html = $this->actingAs($this->administrador())
            ->get(route('medicos.edit', $medico))
            ->assertOk()
            ->assertSee('value="3330004"', false)
            ->assertSee('value="MP-1234"', false)
            ->assertSee('Odontología')
            ->assertDontSee('Cardiología')
            ->assertSee('Guardar cambios')
            ->getContent();

        $this->assertSame(2, preg_match_all('/name="especialidades\[\]"[^>]*checked/', $html));
    }

    public function test_no_se_puede_quitar_una_especialidad_con_citas_y_no_cambia_nada(): void
    {
        $pediatria = $this->especialidad('Pediatría');
        $general = $this->especialidad('Medicina general');
        $medico = $this->crearFichaMedico(['ci' => '3330005', 'primer_nombre' => 'Rosa'], [], [$pediatria->id, $general->id]);

        Cita::create([
            'paciente_id' => $this->crearPaciente()->id,
            'medico_id' => $medico->id,
            'especialidad_id' => $pediatria->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => '2026-11-15',
            'hora_inicio' => '09:00',
            'hora_fin' => '09:30',
        ]);
        $this->actingAs($this->administrador());

        $this->from(route('medicos.edit', $medico))
            ->put(route('medicos.update', $medico), $this->datos([$general->id], ['ci' => '3330005', 'primer_nombre' => 'Lucía']))
            ->assertRedirect(route('medicos.edit', $medico))
            ->assertSessionHas('error');

        $this->assertStringContainsString('Pediatría', session('error'));
        $this->assertSame(2, $medico->fresh()->especialidades()->count(), 'la especialidad con citas se conserva');
        $this->assertSame('Rosa', $medico->persona->fresh()->primer_nombre, 'no se aplicó ningún cambio a medias');

        // Sin citas con Medicina general sí se puede quitar.
        $this->put(route('medicos.update', $medico), $this->datos([$pediatria->id], ['ci' => '3330005']))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(['Pediatría'], $medico->fresh()->especialidades()->pluck('nombre')->all());
    }

    // --- Desactivar y reactivar ---------------------------------------------------------

    public function test_desactivar_no_elimina_conserva_sus_especialidades_y_se_puede_reactivar(): void
    {
        $administrador = $this->administrador();
        $e = $this->especialidad();
        $medico = $this->crearFichaMedico([], [], [$e->id]);
        $this->actingAs($administrador);

        $this->patch(route('medicos.estado', $medico), ['activo' => 0])->assertRedirect(route('medicos.index'));

        $this->assertFalse($medico->fresh()->estado);
        $this->assertSame(1, Medico::count(), 'desactivar no elimina al médico');
        $this->assertSame(1, $medico->fresh()->especialidades()->count());

        $this->patch(route('medicos.estado', $medico), ['activo' => 1]);
        $this->assertTrue($medico->fresh()->estado);

        $acciones = Bitacora::where('usuario_id', $administrador->id)->orderBy('id')->pluck('accion')->all();
        $this->assertSame(['medico_desactivado', 'medico_reactivado'], $acciones);
    }

    public function test_desactivar_baja_la_cuenta_de_medico_pero_reactivar_no_la_reactiva(): void
    {
        $e = $this->especialidad();
        $cuenta = $this->crearCuenta('MEDICO');
        $medico = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-5'])->refresh();
        $medico->especialidades()->sync([$e->id]);
        $this->actingAs($this->administrador());

        $this->patch(route('medicos.estado', $medico), ['activo' => 0]);
        $this->assertFalse($cuenta->fresh()->estado, 'un médico de baja no puede iniciar sesión');

        $this->patch(route('medicos.estado', $medico), ['activo' => 1]);
        $this->assertTrue($medico->fresh()->estado);
        $this->assertFalse($cuenta->fresh()->estado, 'la cuenta la reactiva el Administrador en CU-03');
    }

    public function test_reactivar_al_medico_no_reactiva_una_cuenta_desactivada_a_proposito(): void
    {
        $e = $this->especialidad();
        $cuenta = $this->crearCuenta('MEDICO', [], ['estado' => false]);
        $medico = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-7', 'estado' => false])->refresh();
        $medico->especialidades()->sync([$e->id]);

        $this->actingAs($this->administrador())->patch(route('medicos.estado', $medico), ['activo' => 1]);

        $this->assertTrue($medico->fresh()->estado);
        $this->assertFalse($cuenta->fresh()->estado);
    }

    public function test_desactivar_al_medico_no_toca_una_cuenta_de_otro_rol_de_la_misma_persona(): void
    {
        $e = $this->especialidad();
        $cuenta = $this->crearCuenta('PACIENTE');
        $medico = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-6'])->refresh();
        $medico->especialidades()->sync([$e->id]);

        $this->actingAs($this->administrador())->patch(route('medicos.estado', $medico), ['activo' => 0]);

        $this->assertFalse($medico->fresh()->estado);
        $this->assertTrue($cuenta->fresh()->estado, 'la cuenta de paciente sigue activa');
    }

    public function test_desactivar_vuelve_a_la_pestaña_y_a_la_busqueda_de_origen(): void
    {
        $e = $this->especialidad();
        $medico = $this->crearFichaMedico([], [], [$e->id]);
        $this->actingAs($this->administrador());

        $this->patch(route('medicos.estado', $medico), ['activo' => 0])
            ->assertRedirect(route('medicos.index'));
        $this->assertStringContainsString('pestaña Inactivos', session('exito'));

        $this->patch(route('medicos.estado', $medico), ['activo' => 1, 'vista' => 'inactivas'])
            ->assertRedirect(route('medicos.index', ['estado' => 'inactivas']));
        $this->assertStringContainsString('pestaña Activos', session('exito'));

        $this->patch(route('medicos.estado', $medico), ['activo' => 0, 'vista' => 'todas', 'q' => 'luna'])
            ->assertRedirect(route('medicos.index', ['q' => 'luna', 'estado' => 'todas']));

        // Con búsqueda el estado va siempre en la URL, incluso en la pestaña de activos.
        $this->patch(route('medicos.estado', $medico), ['activo' => 1, 'vista' => 'activas', 'q' => 'luna'])
            ->assertRedirect(route('medicos.index', ['q' => 'luna', 'estado' => 'activas']));
    }

    public function test_una_pestaña_alterada_en_el_formulario_cae_en_la_de_activos(): void
    {
        $medico = $this->crearFichaMedico([], [], [$this->especialidad()->id]);

        $this->actingAs($this->administrador())
            ->patch(route('medicos.estado', $medico), ['activo' => 0, 'vista' => 'https://otro.sitio'])
            ->assertRedirect(route('medicos.index'));
    }

    public function test_un_medico_inactivo_deja_de_aparecer_entre_los_activos_y_aparece_entre_los_inactivos(): void
    {
        $e = $this->especialidad();
        $medico = $this->crearFichaMedico(['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna'], [], [$e->id]);
        $this->actingAs($this->administrador());

        $this->patch(route('medicos.estado', $medico), ['activo' => 0]);

        $this->get(route('medicos.index'))->assertDontSee('Rosa Luna');
        $this->get(route('medicos.index', ['estado' => 'inactivas']))->assertSee('Rosa Luna')->assertSee('Reactivar');
    }

    // --- Base de datos ------------------------------------------------------------------

    public function test_la_base_rechaza_un_registro_profesional_repetido_aunque_se_salte_la_aplicacion(): void
    {
        $this->crearFichaMedico([], ['nro_registro_profesional' => 'MP-UNICO']);

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        $this->crearFichaMedico([], ['nro_registro_profesional' => 'MP-UNICO']);
    }

    public function test_la_base_rechaza_dos_fichas_de_medico_para_la_misma_persona(): void
    {
        $medico = $this->crearFichaMedico();

        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        Medico::create(['persona_id' => $medico->persona_id, 'nro_registro_profesional' => 'MP-OTRO']);
    }
}
