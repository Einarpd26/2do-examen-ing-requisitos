<?php

namespace Tests\Feature\Usuarios;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Bitacora;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Persona;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-03: gestionar usuarios y roles del personal (RF01, RNF18, CU-19).
 */
class UsuarioTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private function administrador(): Usuario
    {
        return $this->crearCuenta('ADMINISTRADOR');
    }

    /** Datos válidos del formulario para un usuario que no es médico, con CI y correo nuevos. */
    private function datos(array $reemplazo = []): array
    {
        $n = ++self::$secuenciaCi;

        return array_merge([
            'rol' => 'RECEPCIONISTA',
            'ci' => (string) $n,
            'primer_nombre' => 'Lucía',
            'segundo_nombre' => 'Elena',
            'apellido_paterno' => 'Rojas',
            'apellido_materno' => 'Vega',
            'telefono' => '70011122',
            'correo' => "lucia{$n}@clinica.test",
        ], $reemplazo);
    }

    private function medicoActivo(array $persona = [], array $ficha = []): Medico
    {
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina general'], ['duracion_min' => 20]);

        return $this->crearFichaMedico($persona, $ficha, [$especialidad->id]);
    }

    // --- Permisos -----------------------------------------------------------------------

    public function test_solo_el_administrador_gestiona_usuarios(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA');

        foreach (['RECEPCIONISTA', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->get(route('usuarios.index'))->assertForbidden();
            $this->get(route('usuarios.create'))->assertForbidden();
            $this->post(route('usuarios.store'), $this->datos())->assertForbidden();
            $this->get(route('usuarios.edit', $cuenta))->assertForbidden();
            $this->put(route('usuarios.update', $cuenta), $this->datos())->assertForbidden();
            $this->patch(route('usuarios.estado', $cuenta), ['activo' => 0])->assertForbidden();
            $this->post(route('usuarios.renovar', $cuenta), ['verificacion' => 'documento'])->assertForbidden();
            $this->get(route('usuarios.credenciales', $cuenta))->assertForbidden();
        }

        $this->assertTrue($cuenta->fresh()->estado);
        $this->actingAs($this->administrador())->get(route('usuarios.index'))->assertOk();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('usuarios.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_del_administrador_enlaza_a_usuarios(): void
    {
        $this->actingAs($this->administrador())->get(route('inicio'))->assertSee(route('usuarios.index'), false);
    }

    public function test_las_cuentas_de_pacientes_no_se_alcanzan_desde_aqui(): void
    {
        $paciente = $this->crearCuenta('PACIENTE');
        $this->actingAs($this->administrador());

        $this->get(route('usuarios.edit', $paciente))->assertNotFound();
        $this->put(route('usuarios.update', $paciente), $this->datos())->assertNotFound();
        $this->patch(route('usuarios.estado', $paciente), ['activo' => 0])->assertNotFound();
        $this->post(route('usuarios.renovar', $paciente), ['verificacion' => 'documento'])->assertNotFound();
        $this->get(route('usuarios.credenciales', $paciente))->assertNotFound();

        $this->assertTrue($paciente->fresh()->estado);
    }

    // --- Listado ------------------------------------------------------------------------

    public function test_el_listado_muestra_al_personal_con_su_rol_y_no_a_los_pacientes(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna']);
        $this->crearCuenta('MEDICO', ['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil']);
        $this->crearCuenta('PACIENTE', ['primer_nombre' => 'Ana', 'apellido_paterno' => 'Paz']);

        $this->actingAs($this->administrador())
            ->get(route('usuarios.index'))
            ->assertOk()
            ->assertSee('Rosa Luna')
            ->assertSee('Recepcionista')
            ->assertSee('Pedro Gil')
            ->assertSee('Médico')
            ->assertDontSee('Ana Paz');
    }

    public function test_marca_la_propia_cuenta_y_no_ofrece_desactivarla(): void
    {
        $administrador = $this->administrador();
        $this->crearCuenta('RECEPCIONISTA');

        $html = $this->actingAs($administrador)->get(route('usuarios.index'))->assertSee('Usted')->getContent();

        $this->assertSame(1, substr_count($html, 'Desactivar</button>'), 'solo la otra cuenta ofrece Desactivar');
    }

    public function test_busca_por_nombre_ci_correo_y_rol_e_incluye_a_los_inactivos(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['ci' => '5551111', 'primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna', 'correo' => 'rosa@clinica.test']);
        $this->crearCuenta('MEDICO', ['ci' => '6662222', 'primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil', 'correo' => 'pedro@clinica.test'], ['estado' => false]);
        $this->actingAs($this->administrador());

        $this->get(route('usuarios.index', ['q' => 'rosa']))->assertSee('Rosa Luna')->assertDontSee('Pedro Gil');
        $this->get(route('usuarios.index', ['q' => '6662']))->assertSee('Pedro Gil')->assertDontSee('Rosa Luna');
        $this->get(route('usuarios.index', ['q' => 'pedro@']))->assertSee('Pedro Gil');
        $this->get(route('usuarios.index', ['q' => 'recepcion']))->assertSee('Rosa Luna')->assertDontSee('Pedro Gil');

        $this->get(route('usuarios.index', ['q' => 'gil']))
            ->assertSee('Pedro Gil')
            ->assertSee('La búsqueda incluye a los usuarios inactivos');
    }

    public function test_los_comodines_de_la_busqueda_se_toman_como_texto(): void
    {
        $this->crearCuenta('RECEPCIONISTA');

        $this->actingAs($this->administrador())
            ->get(route('usuarios.index', ['q' => '%']))
            ->assertSee('No hay usuarios que coincidan');
    }

    private function dosActivosYUnInactivo(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna']);
        $this->crearCuenta('MEDICO', ['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil']);
        $this->crearCuenta('RECEPCIONISTA', ['primer_nombre' => 'Lucía', 'apellido_paterno' => 'Rojas'], ['estado' => false]);
    }

    public function test_las_pestañas_filtran_y_cuentan(): void
    {
        $administrador = $this->administrador();
        $this->dosActivosYUnInactivo();
        $this->actingAs($administrador);

        $this->get(route('usuarios.index'))
            ->assertSee('Rosa Luna')->assertSee('Pedro Gil')->assertDontSee('Lucía Rojas')
            ->assertSeeInOrder(['Activos', '3', 'Inactivos', '1', 'Todos', '4'])
            ->assertSee('nav nav-underline pestanas', false);

        $this->get(route('usuarios.index', ['estado' => 'inactivas']))
            ->assertSee('Lucía Rojas')->assertDontSee('Rosa Luna');

        $this->get(route('usuarios.index', ['estado' => 'todas']))
            ->assertSee('Lucía Rojas')->assertSee('Rosa Luna');

        $this->get(route('usuarios.index', ['estado' => 'cualquier-cosa']))
            ->assertSee('Rosa Luna')->assertDontSee('Lucía Rojas');
    }

    public function test_cada_fila_ofrece_solo_la_accion_que_corresponde_y_la_inactiva_se_atenua(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['primer_nombre' => 'Lucía', 'apellido_paterno' => 'Rojas'], ['estado' => false]);
        $this->actingAs($this->administrador());

        $html = $this->get(route('usuarios.index', ['estado' => 'inactivas']))
            ->assertSee('Reactivar')->assertDontSee('Desactivar')->getContent();

        $this->assertSame(1, substr_count($html, 'fila-inactiva'));
    }

    public function test_cada_pestaña_vacia_avisa(): void
    {
        $this->actingAs($this->administrador());

        $this->get(route('usuarios.index', ['estado' => 'inactivas']))->assertSee('No hay usuarios inactivos.');
    }

    // --- Crear --------------------------------------------------------------------------

    public function test_crea_una_cuenta_de_recepcionista_con_contrasena_temporal_de_72_horas(): void
    {
        $administrador = $this->administrador();
        $datos = $this->datos(['ci' => '4567890', 'correo' => 'lucia@clinica.test']);

        $this->actingAs($administrador)
            ->post(route('usuarios.store'), $datos)
            ->assertRedirect();

        $cuenta = Usuario::with('persona', 'rol')->where('persona_id', Persona::where('ci', '4567890')->value('id'))->firstOrFail();
        $this->assertSame('RECEPCIONISTA', $cuenta->rol->codigo);
        $this->assertSame('Lucía Elena Rojas Vega', $cuenta->persona->nombre_completo);
        $this->assertTrue($cuenta->estado);
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertEqualsWithDelta(72 * 3600, now()->diffInSeconds($cuenta->password_temporal_vence), 10);

        $credenciales = session('credenciales');
        $this->assertSame($cuenta->id, $credenciales['cuenta']);
        $this->assertSame('lucia@clinica.test', $credenciales['usuario']);
        $this->assertSame(14, strlen($credenciales['contrasena']));
        $this->assertTrue(Hash::check($credenciales['contrasena'], $cuenta->password_hash));

        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'usuario_creado')->firstOrFail();
        $this->assertSame('Rol: Recepcionista', $registro->detalle);
    }

    public function test_tambien_se_puede_crear_otro_administrador(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('usuarios.store'), $this->datos(['rol' => 'ADMINISTRADOR']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(2, Usuario::whereHas('rol', fn ($r) => $r->where('codigo', 'ADMINISTRADOR'))->count());
    }

    public function test_la_contrasena_temporal_se_muestra_una_sola_vez(): void
    {
        $this->actingAs($this->administrador())->post(route('usuarios.store'), $this->datos());
        $cuenta = Usuario::where('id', session('credenciales.cuenta'))->firstOrFail();
        $contrasena = session('credenciales.contrasena');

        $this->get(route('usuarios.credenciales', $cuenta))
            ->assertOk()
            ->assertSee($contrasena)
            ->assertSee('Usuario (correo)')
            ->assertSee('una sola vez');

        $this->get(route('usuarios.credenciales', $cuenta))
            ->assertRedirect(route('usuarios.edit', $cuenta))
            ->assertSessionHas('error');
    }

    public function test_las_credenciales_de_una_cuenta_no_se_ven_desde_otra(): void
    {
        $otra = $this->crearCuenta('RECEPCIONISTA');
        $this->actingAs($this->administrador())->post(route('usuarios.store'), $this->datos());

        $this->get(route('usuarios.credenciales', $otra))->assertRedirect(route('usuarios.edit', $otra));
    }

    public function test_el_empleado_entra_con_su_correo_y_la_contrasena_temporal_y_debe_cambiarla(): void
    {
        $this->actingAs($this->administrador())->post(route('usuarios.store'), $this->datos(['correo' => 'lucia@clinica.test']));
        $contrasena = session('credenciales.contrasena');

        $this->post(route('logout'));
        $this->post(route('login.autenticar'), $this->credenciales('lucia@clinica.test', $contrasena))
            ->assertRedirect(route('inicio'));

        $this->get(route('inicio'))->assertRedirect(route('password.cambiar'));
    }

    public function test_reutiliza_la_persona_que_existe_sin_cuenta_y_no_toca_sus_datos(): void
    {
        $paciente = $this->crearPaciente(['ci' => '8880001', 'primer_nombre' => 'Ana', 'correo' => 'ana@mail.com']);

        $this->actingAs($this->administrador())->post(route('usuarios.store'), $this->datos([
            'ci' => '8880001', 'primer_nombre' => 'Nombre distinto', 'correo' => 'otro@mail.com',
        ]))->assertSessionDoesntHaveErrors();

        $cuenta = Usuario::where('persona_id', $paciente->persona_id)->firstOrFail();
        $this->assertSame('RECEPCIONISTA', $cuenta->rol->codigo);
        $this->assertSame(1, Persona::where('ci', '8880001')->count());
        $this->assertSame('Ana Paz', $paciente->persona->fresh()->nombre_completo, 'los datos personales no se modifican');
        $this->assertSame('ana@mail.com', $paciente->persona->fresh()->correo, 'conserva su correo');
        $this->assertSame('ana@mail.com', session('credenciales.usuario'));
    }

    public function test_si_la_persona_existente_no_tenia_correo_se_usa_el_indicado(): void
    {
        $paciente = $this->crearPaciente(['ci' => '8880002', 'correo' => null]);

        $this->actingAs($this->administrador())->post(route('usuarios.store'), $this->datos([
            'ci' => '8880002', 'correo' => 'nuevo@clinica.test',
        ]))->assertSessionDoesntHaveErrors();

        $this->assertSame('nuevo@clinica.test', $paciente->persona->fresh()->correo);
        $this->assertSame('nuevo@clinica.test', session('credenciales.usuario'));
    }

    public function test_rechaza_un_ci_de_una_persona_que_ya_tiene_cuenta(): void
    {
        $existente = $this->crearCuenta('MEDICO', ['ci' => '9990001']);
        $this->actingAs($this->administrador());

        $this->post(route('usuarios.store'), $this->datos(['ci' => '9990001']))->assertSessionHasErrors('ci');

        $this->assertStringContainsString('rol Médico', $this->mensajeDeError('ci'));
        $this->assertSame(1, Usuario::where('persona_id', $existente->persona_id)->count());
    }

    public function test_rechaza_un_correo_repetido_sin_distinguir_mayusculas(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['correo' => 'ocupado@clinica.test']);

        $this->actingAs($this->administrador())
            ->post(route('usuarios.store'), $this->datos(['correo' => 'OCUPADO@clinica.test']))
            ->assertSessionHasErrors('correo');
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        $this->actingAs($this->administrador());

        $casos = [
            'sin rol' => [['rol' => ''], 'rol'],
            'rol de paciente' => [['rol' => 'PACIENTE'], 'rol'],
            'rol inexistente' => [['rol' => 'GERENTE'], 'rol'],
            'sin ci' => [['ci' => ''], 'ci'],
            'ci con letras' => [['ci' => 'ABC123'], 'ci'],
            'sin nombre' => [['primer_nombre' => ''], 'primer_nombre'],
            'sin apellido paterno' => [['apellido_paterno' => ''], 'apellido_paterno'],
            'sin correo' => [['correo' => ''], 'correo'],
            'correo mal escrito' => [['correo' => 'no-es-correo'], 'correo'],
            'telefono invalido' => [['telefono' => '12'], 'telefono'],
        ];

        foreach ($casos as [$reemplazo, $campo]) {
            $this->post(route('usuarios.store'), $this->datos($reemplazo))->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, Usuario::where('rol_id', '!=', $this->administrador()->rol_id)->count());
    }

    public function test_el_telefono_y_los_nombres_opcionales_pueden_ir_vacios(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('usuarios.store'), $this->datos(['telefono' => '', 'segundo_nombre' => '', 'apellido_materno' => '']))
            ->assertSessionDoesntHaveErrors();
    }

    public function test_los_mensajes_salen_en_espanol(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('usuarios.store'), $this->datos(['rol' => '']));

        $this->assertSame('Elija el rol de la cuenta.', $this->mensajeDeError('rol'));
    }

    public function test_el_formulario_de_alta_ofrece_los_tres_roles_del_personal_y_solo_medicos_activos_sin_cuenta(): void
    {
        $libre = $this->medicoActivo(['primer_nombre' => 'Libre', 'apellido_paterno' => 'Sinc']);
        $this->medicoActivo(['primer_nombre' => 'Inactivo', 'apellido_paterno' => 'Baja'], ['estado' => false]);
        $conCuenta = $this->crearCuenta('MEDICO', ['primer_nombre' => 'Conc', 'apellido_paterno' => 'Cuenta']);
        Medico::create(['persona_id' => $conCuenta->persona_id, 'nro_registro_profesional' => 'MP-CC']);

        $this->actingAs($this->administrador())
            ->get(route('usuarios.create'))
            ->assertOk()
            ->assertSee('Administrador')->assertSee('Recepcionista')->assertSee('Médico')
            ->assertDontSee('value="PACIENTE"', false)
            ->assertSee('Libre Sinc')
            ->assertDontSee('Inactivo Baja')
            ->assertDontSee('Conc Cuenta');

        $this->assertNotNull($libre);
    }

    public function test_si_no_hay_medicos_sin_cuenta_el_formulario_avisa(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('usuarios.create'))
            ->assertSee('No hay médicos activos sin cuenta');
    }

    // --- Crear cuenta de médico ---------------------------------------------------------

    public function test_vincula_la_cuenta_a_un_medico_registrado_sin_pedir_datos_personales(): void
    {
        $medico = $this->medicoActivo(['correo' => 'carlos@clinica.test']);

        $this->actingAs($this->administrador())
            ->post(route('usuarios.store'), ['rol' => 'MEDICO', 'medico_id' => $medico->id])
            ->assertSessionDoesntHaveErrors();

        $cuenta = Usuario::with('rol')->where('persona_id', $medico->persona_id)->firstOrFail();
        $this->assertSame('MEDICO', $cuenta->rol->codigo);
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertSame('carlos@clinica.test', session('credenciales.usuario'));
        $this->assertSame(1, Persona::where('ci', $medico->persona->ci)->count(), 'no se crea otra persona');
    }

    public function test_el_medico_entra_con_su_correo_y_la_contrasena_temporal(): void
    {
        $medico = $this->medicoActivo(['correo' => 'carlos@clinica.test']);
        $this->actingAs($this->administrador())->post(route('usuarios.store'), ['rol' => 'MEDICO', 'medico_id' => $medico->id]);
        $contrasena = session('credenciales.contrasena');

        $this->post(route('logout'));
        $this->post(route('login.autenticar'), $this->credenciales('carlos@clinica.test', $contrasena))
            ->assertRedirect(route('inicio'));

        $this->assertAuthenticated();
    }

    public function test_rechaza_vincular_a_un_medico_que_ya_tiene_cuenta(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');
        $medico = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-9'])->refresh();

        $this->actingAs($this->administrador())
            ->from(route('usuarios.create'))
            ->post(route('usuarios.store'), ['rol' => 'MEDICO', 'medico_id' => $medico->id])
            ->assertRedirect(route('usuarios.create'))
            ->assertSessionHas('error');

        $this->assertStringContainsString('ya tiene una cuenta', session('error'));
        $this->assertSame(1, Usuario::where('persona_id', $cuenta->persona_id)->count());
    }

    public function test_rechaza_vincular_a_un_medico_desactivado(): void
    {
        $medico = $this->medicoActivo([], ['estado' => false]);

        $this->actingAs($this->administrador())
            ->from(route('usuarios.create'))
            ->post(route('usuarios.store'), ['rol' => 'MEDICO', 'medico_id' => $medico->id])
            ->assertSessionHas('error');

        $this->assertStringContainsString('desactivado', session('error'));
        $this->assertSame(0, Usuario::where('persona_id', $medico->persona_id)->count());
    }

    public function test_el_rol_medico_exige_elegir_un_medico_que_exista(): void
    {
        $this->actingAs($this->administrador());

        $this->post(route('usuarios.store'), ['rol' => 'MEDICO'])->assertSessionHasErrors('medico_id');
        $this->post(route('usuarios.store'), ['rol' => 'MEDICO', 'medico_id' => 999999])->assertSessionHasErrors('medico_id');
    }

    public function test_una_solicitud_simultanea_con_el_mismo_correo_se_informa_como_regla_de_negocio(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['correo' => 'ocupado@clinica.test']);

        // Se salta la validación del formulario: simula que otra solicitud ganó la carrera.
        try {
            app(UsuarioService::class)->crear($this->datos(['correo' => 'ocupado@clinica.test']), $this->administrador());
            $this->fail('Debió rechazar el correo repetido.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertStringContainsString('ya pertenecen', $e->getMessage());
        }

        $this->assertSame(0, Persona::where('primer_nombre', 'Lucía')->count(), 'no queda una persona a medias');
    }

    // --- Modificar ----------------------------------------------------------------------

    public function test_modifica_los_datos_personales_y_queda_en_la_bitacora(): void
    {
        $administrador = $this->administrador();
        $cuenta = $this->crearCuenta('RECEPCIONISTA', ['ci' => '3330001']);

        $this->actingAs($administrador)
            ->put(route('usuarios.update', $cuenta), $this->datos(['ci' => '3330001', 'primer_nombre' => 'Marta', 'correo' => 'marta@clinica.test']))
            ->assertRedirect(route('usuarios.index'));

        $cuenta->refresh()->load('persona');
        $this->assertSame('Marta', $cuenta->persona->primer_nombre);
        $this->assertSame('marta@clinica.test', $cuenta->persona->correo);
        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'usuario_modificado')->firstOrFail();
        $this->assertSame('Datos personales', $registro->detalle);
    }

    public function test_cambia_el_rol_entre_recepcionista_y_administrador(): void
    {
        $administrador = $this->administrador();
        $cuenta = $this->crearCuenta('RECEPCIONISTA', ['ci' => '3330002']);

        $this->actingAs($administrador)
            ->put(route('usuarios.update', $cuenta), $this->datos(['ci' => '3330002', 'rol' => 'ADMINISTRADOR']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('ADMINISTRADOR', $cuenta->fresh()->rol->codigo);
        $registro = Bitacora::where('accion', 'usuario_modificado')->firstOrFail();
        $this->assertSame('Datos personales; rol: Recepcionista → Administrador', $registro->detalle);
    }

    public function test_al_modificar_no_se_puede_asignar_el_rol_de_medico_ni_de_paciente(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA', ['ci' => '3330003']);
        $this->actingAs($this->administrador());

        foreach (['MEDICO', 'PACIENTE'] as $rol) {
            $this->put(route('usuarios.update', $cuenta), $this->datos(['ci' => '3330003', 'rol' => $rol]))
                ->assertSessionHasErrors('rol');
        }

        $this->assertSame('RECEPCIONISTA', $cuenta->fresh()->rol->codigo);
    }

    public function test_al_modificar_puede_conservar_lo_propio_pero_no_tomar_lo_de_otro(): void
    {
        $this->crearCuenta('RECEPCIONISTA', ['ci' => '3330004', 'correo' => 'otro@clinica.test']);
        $cuenta = $this->crearCuenta('RECEPCIONISTA', ['ci' => '3330005', 'correo' => 'propio@clinica.test']);
        $this->actingAs($this->administrador());

        $propio = ['ci' => '3330005', 'correo' => 'propio@clinica.test'];
        $this->put(route('usuarios.update', $cuenta), $this->datos($propio))->assertSessionDoesntHaveErrors();

        $this->put(route('usuarios.update', $cuenta), $this->datos(array_merge($propio, ['ci' => '3330004'])))
            ->assertSessionHasErrors('ci');
        $this->put(route('usuarios.update', $cuenta), $this->datos(array_merge($propio, ['correo' => 'OTRO@clinica.test'])))
            ->assertSessionHasErrors('correo');
    }

    public function test_nadie_puede_cambiarse_su_propio_rol(): void
    {
        $administrador = $this->administrador();
        $this->crearCuenta('ADMINISTRADOR'); // hay otro administrador: aun así no puede quitarse el rol

        $this->actingAs($administrador)
            ->from(route('usuarios.edit', $administrador))
            ->put(route('usuarios.update', $administrador), $this->datos([
                'ci' => $administrador->persona->ci, 'correo' => $administrador->persona->correo, 'rol' => 'RECEPCIONISTA',
            ]))
            ->assertSessionHas('error');

        $this->assertStringContainsString('propio rol', session('error'));
        $this->assertSame('ADMINISTRADOR', $administrador->fresh()->rol->codigo);
    }

    public function test_la_edicion_de_una_cuenta_de_medico_se_hace_en_medicos(): void
    {
        $medico = $this->medicoActivo(['primer_nombre' => 'Carlos', 'apellido_paterno' => 'Mendoza']);
        $cuenta = Usuario::create([
            'persona_id' => $medico->persona_id,
            'rol_id' => \App\Models\Rol::where('codigo', 'MEDICO')->firstOrFail()->id,
            'password_hash' => self::CLAVE,
        ])->refresh();
        $this->actingAs($this->administrador());

        $this->get(route('usuarios.edit', $cuenta))
            ->assertOk()
            ->assertSee('Cuenta de médico')
            ->assertSee(route('medicos.edit', $medico), false)
            ->assertDontSee('Guardar cambios');

        $this->from(route('usuarios.edit', $cuenta))
            ->put(route('usuarios.update', $cuenta), $this->datos())
            ->assertSessionHas('error');

        $this->assertSame('Carlos', $medico->persona->fresh()->primer_nombre);
    }

    public function test_el_formulario_de_edicion_trae_los_datos_y_solo_los_roles_intercambiables(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA', ['ci' => '3330006', 'primer_nombre' => 'Rosa', 'correo' => 'rosa@clinica.test']);

        $this->actingAs($this->administrador())
            ->get(route('usuarios.edit', $cuenta))
            ->assertOk()
            ->assertSee('value="3330006"', false)
            ->assertSee('value="rosa@clinica.test"', false)
            ->assertSee('<option value="RECEPCIONISTA" selected>Recepcionista</option>', false)
            ->assertSee('value="ADMINISTRADOR"', false)
            ->assertDontSee('value="MEDICO"', false)
            ->assertSee('Guardar cambios');
    }

    public function test_en_la_propia_edicion_el_rol_no_se_puede_cambiar(): void
    {
        $administrador = $this->administrador();

        $this->actingAs($administrador)
            ->get(route('usuarios.edit', $administrador))
            ->assertSee('No puede cambiar su propio rol')
            ->assertSee('Usted');
    }

    // --- Desactivar y reactivar ---------------------------------------------------------

    public function test_desactivar_no_elimina_y_se_puede_reactivar(): void
    {
        $administrador = $this->administrador();
        $cuenta = $this->crearCuenta('RECEPCIONISTA');
        $this->actingAs($administrador);

        $this->patch(route('usuarios.estado', $cuenta), ['activo' => 0])->assertRedirect(route('usuarios.index'));
        $this->assertFalse($cuenta->fresh()->estado);
        $this->assertSame(1, Usuario::where('id', $cuenta->id)->count(), 'desactivar no elimina la cuenta');

        $this->patch(route('usuarios.estado', $cuenta), ['activo' => 1]);
        $this->assertTrue($cuenta->fresh()->estado);

        $acciones = Bitacora::where('usuario_id', $administrador->id)->orderBy('id')->pluck('accion')->all();
        $this->assertSame(['usuario_desactivado', 'usuario_reactivado'], $acciones);
    }

    public function test_una_cuenta_desactivada_ya_no_puede_entrar(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA');
        $this->actingAs($this->administrador())->patch(route('usuarios.estado', $cuenta), ['activo' => 0]);
        $this->post(route('logout'));

        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo))
            ->assertSessionHasErrors('identificador');

        $this->assertGuest();
    }

    public function test_desactivar_vuelve_a_la_pestaña_y_a_la_busqueda_de_origen(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA');
        $this->actingAs($this->administrador());

        $this->patch(route('usuarios.estado', $cuenta), ['activo' => 0])->assertRedirect(route('usuarios.index'));
        $this->assertStringContainsString('pestaña Inactivos', session('exito'));

        $this->patch(route('usuarios.estado', $cuenta), ['activo' => 1, 'vista' => 'inactivas'])
            ->assertRedirect(route('usuarios.index', ['estado' => 'inactivas']));

        $this->patch(route('usuarios.estado', $cuenta), ['activo' => 0, 'vista' => 'todas', 'q' => 'luna'])
            ->assertRedirect(route('usuarios.index', ['q' => 'luna', 'estado' => 'todas']));

        $this->patch(route('usuarios.estado', $cuenta), ['activo' => 1, 'vista' => 'https://otro.sitio'])
            ->assertRedirect(route('usuarios.index'));
    }

    public function test_nadie_puede_desactivar_su_propia_cuenta(): void
    {
        $administrador = $this->administrador();
        $this->crearCuenta('ADMINISTRADOR');

        $this->actingAs($administrador)
            ->from(route('usuarios.index'))
            ->patch(route('usuarios.estado', $administrador), ['activo' => 0])
            ->assertSessionHas('error', 'No puede desactivar su propia cuenta.');

        $this->assertTrue($administrador->fresh()->estado);
    }

    public function test_el_sistema_conserva_siempre_un_administrador_activo(): void
    {
        $unico = $this->administrador();
        $otroUsuario = $this->crearCuenta('RECEPCIONISTA');
        $servicio = app(UsuarioService::class);

        // Quien actúa no es el administrador: simula una operación que dejaría al sistema sin ninguno.
        try {
            $servicio->cambiarEstado($unico, false, $otroUsuario);
            $this->fail('Debió exigir un administrador activo.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertStringContainsString('al menos un Administrador activo', $e->getMessage());
        }

        try {
            $servicio->actualizar($unico, $this->datos([
                'ci' => $unico->persona->ci, 'correo' => $unico->persona->correo, 'rol' => 'RECEPCIONISTA',
            ]), $otroUsuario);
            $this->fail('Debió exigir un administrador activo.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertStringContainsString('al menos un Administrador activo', $e->getMessage());
        }

        $this->assertTrue($unico->fresh()->estado);
        $this->assertSame('ADMINISTRADOR', $unico->fresh()->rol->codigo);

        // Con otro administrador activo sí se puede.
        $this->crearCuenta('ADMINISTRADOR');
        $servicio->cambiarEstado($unico, false, $otroUsuario);
        $this->assertFalse($unico->fresh()->estado);
    }

    public function test_no_se_reactiva_la_cuenta_de_un_medico_mientras_el_medico_este_desactivado(): void
    {
        $medico = $this->medicoActivo([], ['estado' => false]);
        $cuenta = Usuario::create([
            'persona_id' => $medico->persona_id,
            'rol_id' => \App\Models\Rol::where('codigo', 'MEDICO')->firstOrFail()->id,
            'password_hash' => self::CLAVE,
            'estado' => false,
        ])->refresh();
        $this->actingAs($this->administrador());

        $this->from(route('usuarios.index'))
            ->patch(route('usuarios.estado', $cuenta), ['activo' => 1])
            ->assertSessionHas('error');

        $this->assertStringContainsString('reactívelo primero en Médicos', session('error'));
        $this->assertFalse($cuenta->fresh()->estado);

        $medico->update(['estado' => true]);
        $this->patch(route('usuarios.estado', $cuenta), ['activo' => 1])->assertSessionDoesntHaveErrors();
        $this->assertTrue($cuenta->fresh()->estado);
    }

    // --- Renovar la contraseña (CU-19) --------------------------------------------------

    public function test_renueva_la_contrasena_invalida_la_anterior_y_levanta_el_bloqueo(): void
    {
        $administrador = $this->administrador();
        $cuenta = $this->crearCuenta('RECEPCIONISTA', [], [
            'intentos_fallidos' => 3,
            'bloqueado_hasta' => now()->addMinutes(10),
            'debe_cambiar_password' => true,
            'password_temporal_vence' => now()->subHour(),
        ]);

        $this->actingAs($administrador)
            ->post(route('usuarios.renovar', $cuenta), ['verificacion' => 'documento'])
            ->assertRedirect(route('usuarios.credenciales', $cuenta));

        $nueva = session('credenciales.contrasena');
        $cuenta->refresh();

        $this->assertFalse(Hash::check(self::CLAVE, $cuenta->password_hash), 'la anterior deja de servir');
        $this->assertTrue(Hash::check($nueva, $cuenta->password_hash));
        $this->assertTrue($cuenta->debe_cambiar_password);
        $this->assertTrue($cuenta->password_temporal_vence->isFuture());
        $this->assertSame(0, $cuenta->intentos_fallidos);
        $this->assertNull($cuenta->bloqueado_hasta);

        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'acceso_renovado')->firstOrFail();
        $this->assertSame('Identidad verificada con: documento de identidad en persona', $registro->detalle);

        $this->post(route('logout'));
        $this->post(route('login.autenticar'), $this->credenciales($cuenta->persona->correo, $nueva));
        $this->assertAuthenticatedAs($cuenta);
    }

    public function test_renovar_exige_indicar_como_se_verifico_la_identidad(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA');
        $hashAntes = $cuenta->password_hash;
        $this->actingAs($this->administrador());

        $this->from(route('usuarios.edit', $cuenta))->post(route('usuarios.renovar', $cuenta))
            ->assertSessionHasErrors('verificacion');
        $this->post(route('usuarios.renovar', $cuenta), ['verificacion' => 'porque_si'])
            ->assertSessionHasErrors('verificacion');

        $this->assertSame($hashAntes, $cuenta->fresh()->password_hash);
        $this->assertSame(0, Bitacora::where('accion', 'acceso_renovado')->count());
    }

    public function test_no_se_renueva_la_propia_contrasena_ni_la_de_una_cuenta_desactivada(): void
    {
        $administrador = $this->administrador();
        $desactivada = $this->crearCuenta('RECEPCIONISTA', [], ['estado' => false]);
        $this->actingAs($administrador);

        $this->from(route('usuarios.edit', $administrador))
            ->post(route('usuarios.renovar', $administrador), ['verificacion' => 'documento'])
            ->assertSessionHas('error');
        $this->assertStringContainsString('Cambiar contraseña', session('error'));

        $this->from(route('usuarios.edit', $desactivada))
            ->post(route('usuarios.renovar', $desactivada), ['verificacion' => 'documento'])
            ->assertSessionHas('error');
        $this->assertStringContainsString('desactivada', session('error'));
    }

    public function test_la_edicion_ofrece_renovar_a_otros_y_avisa_en_la_propia_y_en_las_desactivadas(): void
    {
        $administrador = $this->administrador();
        $otra = $this->crearCuenta('RECEPCIONISTA');
        $desactivada = $this->crearCuenta('RECEPCIONISTA', [], ['estado' => false]);
        $this->actingAs($administrador);

        $this->get(route('usuarios.edit', $otra))
            ->assertSee('¿Cómo verificó la identidad del titular?')
            ->assertSee('Documento de identidad en persona')
            ->assertSee('nunca a otro número');

        $this->get(route('usuarios.edit', $administrador))
            ->assertSee('Para cambiar su propia contraseña')
            ->assertDontSee('¿Cómo verificó la identidad del titular?');

        $this->get(route('usuarios.edit', $desactivada))
            ->assertSee('Reactive la cuenta')
            ->assertDontSee('¿Cómo verificó la identidad del titular?');
    }

    // --- Cierre de sesión por desactivación ---------------------------------------------

    public function test_desactivar_una_cuenta_con_sesion_abierta_la_cierra_en_la_siguiente_solicitud(): void
    {
        $cuenta = $this->crearCuenta('RECEPCIONISTA');
        $this->actingAs($cuenta)->get(route('inicio'))->assertOk();

        $cuenta->update(['estado' => false]);

        $this->get(route('inicio'))->assertRedirect(route('login'));
        $this->assertGuest();
    }
}
