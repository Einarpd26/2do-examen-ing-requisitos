<?php

namespace Tests\Feature\Horarios;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Bitacora;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Sucursal;
use App\Models\Usuario;
use App\Services\HorarioService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-07: gestionar horarios de atención (RF05).
 */
class HorarioTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private function administrador(): Usuario
    {
        return $this->crearCuenta('ADMINISTRADOR');
    }

    private function medico(array $persona = [], array $ficha = []): Medico
    {
        $especialidad = Especialidad::firstOrCreate(['nombre' => 'Medicina general'], ['duracion_min' => 20]);

        return $this->crearFichaMedico($persona, $ficha, [$especialidad->id]);
    }

    private function datos(array $reemplazo = []): array
    {
        return array_merge([
            'dia_semana' => 1,
            'hora_inicio' => '08:00',
            'hora_fin' => '12:00',
            'consultorio' => 'Consultorio 3',
        ], $reemplazo);
    }

    private function bloque(Medico $medico, int $dia, string $inicio, string $fin, ?string $consultorio = null): HorarioAtencion
    {
        return HorarioAtencion::create([
            'medico_id' => $medico->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'dia_semana' => $dia,
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'consultorio' => $consultorio,
        ])->refresh();
    }

    /** Un lunes futuro (al menos una semana adelante), para que la cita cuente como futura. */
    private function lunesFuturo(): string
    {
        return CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->addWeek()->format('Y-m-d');
    }

    private function cita(Medico $medico, string $inicio, string $fin, string $estado = 'PROGRAMADA', ?string $fecha = null): Cita
    {
        return Cita::create([
            'paciente_id' => $this->crearPaciente()->id,
            'medico_id' => $medico->id,
            'especialidad_id' => $medico->especialidades->first()->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha ?? $this->lunesFuturo(),
            'hora_inicio' => $inicio,
            'hora_fin' => $fin,
            'estado' => $estado,
        ])->refresh();
    }

    // --- Permisos -----------------------------------------------------------------------

    public function test_solo_el_administrador_gestiona_horarios(): void
    {
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');

        foreach (['RECEPCIONISTA', 'MEDICO', 'PACIENTE'] as $rol) {
            $this->actingAs($this->crearCuenta($rol));
            $this->get(route('horarios.index'))->assertForbidden();
            $this->get(route('horarios.medico', $medico))->assertForbidden();
            $this->post(route('horarios.store', $medico), $this->datos(['dia_semana' => 2]))->assertForbidden();
            $this->get(route('horarios.edit', $horario))->assertForbidden();
            $this->put(route('horarios.update', $horario), $this->datos())->assertForbidden();
            $this->delete(route('horarios.destroy', $horario))->assertForbidden();
        }

        $this->assertSame(1, HorarioAtencion::count());
        $this->actingAs($this->administrador())->get(route('horarios.index'))->assertOk();
    }

    public function test_sin_sesion_se_redirige_al_login(): void
    {
        $this->get(route('horarios.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_del_administrador_enlaza_a_horarios(): void
    {
        $this->actingAs($this->administrador())->get(route('inicio'))->assertSee(route('horarios.index'), false);
    }

    // --- Elegir médico ------------------------------------------------------------------

    public function test_el_indice_lista_solo_los_medicos_activos_con_el_resumen_de_su_semana(): void
    {
        $rosa = $this->medico(['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna']);
        $this->bloque($rosa, 1, '08:00', '12:00');
        $this->bloque($rosa, 1, '14:00', '18:00');
        $this->bloque($rosa, 3, '09:00', '13:00');
        $this->medico(['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Gil']);
        $this->medico(['primer_nombre' => 'Lucía', 'apellido_paterno' => 'Rojas'], ['estado' => false]);

        $this->actingAs($this->administrador())
            ->get(route('horarios.index'))
            ->assertOk()
            ->assertSee('Rosa Luna')
            ->assertSee('Lun')
            ->assertSee('08:00–12:00, 14:00–18:00')
            ->assertSee('Mié')
            ->assertSee('Pedro Gil')
            ->assertSee('Sin horarios')
            ->assertDontSee('Lucía Rojas');
    }

    public function test_el_indice_vacio_avisa(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('horarios.index'))
            ->assertSee('No hay médicos activos');
    }

    public function test_la_pagina_del_medico_muestra_su_semana_el_consultorio_y_la_duracion_de_sus_especialidades(): void
    {
        $medico = $this->medico(['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna']);
        $this->bloque($medico, 1, '08:00', '12:00', 'Consultorio 3');

        $this->actingAs($this->administrador())
            ->get(route('horarios.medico', $medico))
            ->assertOk()
            ->assertSee('Rosa Luna')
            ->assertSeeInOrder(['Lunes', '08:00 – 12:00', 'Consultorio 3'])
            ->assertSee('Medicina general: ')
            ->assertSee('20 min')
            ->assertSee('Agregar un bloque');
    }

    public function test_un_medico_sin_horarios_avisa_que_no_se_le_podran_agendar_citas(): void
    {
        $this->actingAs($this->administrador())
            ->get(route('horarios.medico', $this->medico()))
            ->assertSee('no se le podrán agendar citas');
    }

    public function test_un_medico_inactivo_se_ve_pero_no_se_puede_modificar(): void
    {
        $medico = $this->medico([], ['estado' => false]);
        $this->bloque($medico, 1, '08:00', '12:00');

        $this->actingAs($this->administrador())
            ->get(route('horarios.medico', $medico))
            ->assertOk()
            ->assertSee('está desactivado')
            ->assertDontSee('Agregar un bloque')
            ->assertDontSee('Eliminar');
    }

    // --- Registrar ----------------------------------------------------------------------

    public function test_registra_un_bloque_con_la_unica_sucursal_y_queda_en_la_bitacora(): void
    {
        $administrador = $this->administrador();
        $medico = $this->medico();

        $this->actingAs($administrador)
            ->post(route('horarios.store', $medico), $this->datos())
            ->assertRedirect(route('horarios.medico', $medico))
            ->assertSessionHas('exito', 'Horario registrado: Lunes 08:00–12:00.');

        $horario = HorarioAtencion::firstOrFail();
        $this->assertSame($medico->id, $horario->medico_id);
        $this->assertSame(Sucursal::firstOrFail()->id, $horario->sucursal_id, 'se asigna la sucursal única');
        $this->assertSame(1, $horario->dia_semana);
        $this->assertSame('08:00', $horario->desde());
        $this->assertSame('12:00', $horario->hasta());
        $this->assertSame('Consultorio 3', $horario->consultorio);

        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'horario_creado')->firstOrFail();
        $this->assertSame('Lunes 08:00–12:00', $registro->detalle);
    }

    public function test_el_consultorio_es_opcional(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('horarios.store', $this->medico()), $this->datos(['consultorio' => '']))
            ->assertSessionDoesntHaveErrors();

        $this->assertNull(HorarioAtencion::firstOrFail()->consultorio);
    }

    public function test_permite_bloques_pegados_el_mismo_dia_y_el_mismo_horario_otro_dia_u_otro_medico(): void
    {
        $medico = $this->medico();
        $this->actingAs($this->administrador());

        $this->post(route('horarios.store', $medico), $this->datos())->assertSessionDoesntHaveErrors();
        $this->post(route('horarios.store', $medico), $this->datos(['hora_inicio' => '12:00', 'hora_fin' => '14:00']))
            ->assertSessionDoesntHaveErrors();
        $this->post(route('horarios.store', $medico), $this->datos(['dia_semana' => 2]))->assertSessionDoesntHaveErrors();
        $this->post(route('horarios.store', $this->medico()), $this->datos())->assertSessionDoesntHaveErrors();

        $this->assertSame(4, HorarioAtencion::count());
    }

    public function test_rechaza_un_bloque_que_se_superpone_con_otro_del_mismo_medico_y_dia(): void
    {
        $medico = $this->medico();
        $this->bloque($medico, 1, '08:00', '12:00');
        $this->actingAs($this->administrador());

        $choques = [
            'igual' => ['08:00', '12:00'],
            'contenido' => ['09:00', '11:00'],
            'contenedor' => ['07:00', '13:00'],
            'por el inicio' => ['07:00', '09:00'],
            'por el final' => ['11:00', '13:00'],
        ];

        foreach ($choques as $nombre => [$inicio, $fin]) {
            $this->post(route('horarios.store', $medico), $this->datos(['hora_inicio' => $inicio, 'hora_fin' => $fin]))
                ->assertSessionHasErrors('hora_fin');
            $this->assertStringContainsString('Lunes 08:00–12:00', $this->mensajeDeError('hora_fin'), $nombre);
        }

        $this->assertSame(1, HorarioAtencion::count());
    }

    public function test_valida_los_datos_del_formulario(): void
    {
        $medico = $this->medico();
        $this->actingAs($this->administrador());

        $casos = [
            'sin dia' => [['dia_semana' => ''], 'dia_semana'],
            'dia cero' => [['dia_semana' => 0], 'dia_semana'],
            'dia ocho' => [['dia_semana' => 8], 'dia_semana'],
            'dia con texto' => [['dia_semana' => 'lunes'], 'dia_semana'],
            'sin hora inicial' => [['hora_inicio' => ''], 'hora_inicio'],
            'hora inicial invalida' => [['hora_inicio' => '25:00'], 'hora_inicio'],
            'hora inicial con texto' => [['hora_inicio' => 'temprano'], 'hora_inicio'],
            'sin hora final' => [['hora_fin' => ''], 'hora_fin'],
            'hora final invalida' => [['hora_fin' => '12:75'], 'hora_fin'],
            'hora final igual a la inicial' => [['hora_fin' => '08:00'], 'hora_fin'],
            'hora final anterior a la inicial' => [['hora_fin' => '07:00'], 'hora_fin'],
            'consultorio muy largo' => [['consultorio' => str_repeat('a', 51)], 'consultorio'],
        ];

        foreach ($casos as [$reemplazo, $campo]) {
            $this->post(route('horarios.store', $medico), $this->datos($reemplazo))->assertSessionHasErrors($campo);
        }

        $this->assertSame(0, HorarioAtencion::count());
    }

    public function test_acepta_todos_los_dias_de_la_semana_y_los_extremos_del_dia(): void
    {
        $medico = $this->medico();
        $this->actingAs($this->administrador());

        foreach (range(1, 7) as $dia) {
            $this->post(route('horarios.store', $medico), $this->datos(['dia_semana' => $dia, 'hora_inicio' => '00:00', 'hora_fin' => '23:59']))
                ->assertSessionDoesntHaveErrors();
        }

        $this->assertSame(7, HorarioAtencion::count());
    }

    public function test_los_mensajes_salen_en_espanol(): void
    {
        $this->actingAs($this->administrador())
            ->post(route('horarios.store', $this->medico()), $this->datos(['hora_fin' => '07:00']));

        $this->assertSame('La hora final debe ser posterior a la hora inicial.', $this->mensajeDeError('hora_fin'));
    }

    public function test_no_registra_horarios_a_un_medico_desactivado(): void
    {
        $medico = $this->medico([], ['estado' => false]);

        $this->actingAs($this->administrador())
            ->from(route('horarios.medico', $medico))
            ->post(route('horarios.store', $medico), $this->datos())
            ->assertSessionHas('error');

        $this->assertStringContainsString('desactivado', session('error'));
        $this->assertSame(0, HorarioAtencion::count());
    }

    public function test_si_no_hay_sucursal_activa_avisa_en_lugar_de_fallar(): void
    {
        Sucursal::query()->update(['estado' => false]);
        $medico = $this->medico();

        $this->actingAs($this->administrador())
            ->from(route('horarios.medico', $medico))
            ->post(route('horarios.store', $medico), $this->datos())
            ->assertSessionHas('error');

        $this->assertStringContainsString('sucursal activa', session('error'));
        $this->assertSame(0, HorarioAtencion::count());
    }

    public function test_una_solicitud_simultanea_que_se_superpone_se_informa_como_regla_de_negocio(): void
    {
        $medico = $this->medico();
        $this->bloque($medico, 1, '08:00', '12:00');

        // Se salta la validación del formulario: simula que otra solicitud ganó la carrera.
        try {
            app(HorarioService::class)->registrar($medico, $this->datos(['hora_inicio' => '10:00', 'hora_fin' => '13:00']), $this->administrador());
            $this->fail('Debió rechazar el bloque superpuesto.');
        } catch (ReglaDeNegocioException $e) {
            $this->assertStringContainsString('se superpone', $e->getMessage());
        }

        $this->assertSame(1, HorarioAtencion::count());
    }

    // --- Modificar ----------------------------------------------------------------------

    public function test_modifica_el_bloque_y_la_bitacora_guarda_el_antes_y_el_despues(): void
    {
        $administrador = $this->administrador();
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00', 'Consultorio 3');

        $this->actingAs($administrador)
            ->put(route('horarios.update', $horario), $this->datos([
                'dia_semana' => 2, 'hora_inicio' => '09:00', 'hora_fin' => '13:30', 'consultorio' => 'Consultorio 5',
            ]))
            ->assertRedirect(route('horarios.medico', $medico));

        $horario->refresh();
        $this->assertSame(2, $horario->dia_semana);
        $this->assertSame('09:00', $horario->desde());
        $this->assertSame('13:30', $horario->hasta());
        $this->assertSame('Consultorio 5', $horario->consultorio);

        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'horario_modificado')->firstOrFail();
        $this->assertSame('Lunes 08:00–12:00 → Martes 09:00–13:30', $registro->detalle);
    }

    public function test_un_bloque_no_choca_consigo_mismo_al_modificarlo(): void
    {
        $horario = $this->bloque($this->medico(), 1, '08:00', '12:00');

        $this->actingAs($this->administrador())
            ->put(route('horarios.update', $horario), $this->datos(['hora_inicio' => '08:30', 'hora_fin' => '11:30']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('08:30', $horario->fresh()->desde());
    }

    public function test_al_modificar_no_se_puede_superponer_con_otro_bloque_del_medico(): void
    {
        $medico = $this->medico();
        $this->bloque($medico, 1, '08:00', '12:00');
        $otro = $this->bloque($medico, 1, '14:00', '18:00');

        $this->actingAs($this->administrador())
            ->put(route('horarios.update', $otro), $this->datos(['hora_inicio' => '11:00', 'hora_fin' => '15:00']))
            ->assertSessionHasErrors('hora_fin');

        $this->assertSame('14:00', $otro->fresh()->desde());
    }

    public function test_el_formulario_de_edicion_trae_los_datos_actuales(): void
    {
        $horario = $this->bloque($this->medico(['primer_nombre' => 'Rosa', 'apellido_paterno' => 'Luna']), 3, '09:15', '13:45', 'Consultorio 7');

        $this->actingAs($this->administrador())
            ->get(route('horarios.edit', $horario))
            ->assertOk()
            ->assertSee('Rosa Luna')
            ->assertSee('value="09:15"', false)
            ->assertSee('value="13:45"', false)
            ->assertSee('value="Consultorio 7"', false)
            ->assertSee('<option value="3" selected>Miércoles</option>', false)
            ->assertSee('Guardar cambios');
    }

    public function test_no_modifica_el_horario_de_un_medico_desactivado(): void
    {
        $medico = $this->medico([], ['estado' => false]);
        $horario = $this->bloque($medico, 1, '08:00', '12:00');

        $this->actingAs($this->administrador())
            ->from(route('horarios.edit', $horario))
            ->put(route('horarios.update', $horario), $this->datos(['hora_fin' => '11:00']))
            ->assertSessionHas('error');

        $this->assertSame('12:00', $horario->fresh()->hasta());
    }

    // --- Eliminar -----------------------------------------------------------------------

    public function test_elimina_un_bloque_y_queda_en_la_bitacora(): void
    {
        $administrador = $this->administrador();
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');

        $this->actingAs($administrador)
            ->delete(route('horarios.destroy', $horario))
            ->assertRedirect(route('horarios.medico', $medico))
            ->assertSessionHas('exito', 'Horario eliminado: Lunes 08:00–12:00.');

        $this->assertSame(0, HorarioAtencion::count());
        $registro = Bitacora::where('usuario_id', $administrador->id)->where('accion', 'horario_eliminado')->firstOrFail();
        $this->assertSame('Lunes 08:00–12:00', $registro->detalle);
    }

    public function test_no_elimina_el_horario_de_un_medico_desactivado(): void
    {
        $medico = $this->medico([], ['estado' => false]);
        $horario = $this->bloque($medico, 1, '08:00', '12:00');

        $this->actingAs($this->administrador())
            ->from(route('horarios.medico', $medico))
            ->delete(route('horarios.destroy', $horario))
            ->assertSessionHas('error');

        $this->assertSame(1, HorarioAtencion::count());
    }

    // --- Protección de las citas ya agendadas -------------------------------------------

    public function test_no_elimina_un_bloque_que_cubre_una_cita_futura_agendada(): void
    {
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $cita = $this->cita($medico, '09:00', '09:20');

        $this->actingAs($this->administrador())
            ->from(route('horarios.medico', $medico))
            ->delete(route('horarios.destroy', $horario))
            ->assertSessionHas('error');

        $this->assertStringContainsString($cita->codigo, session('error'));
        $this->assertStringContainsString('Reprográmelas o cancélelas', session('error'));
        $this->assertSame(1, HorarioAtencion::count(), 'el bloque se conserva');
    }

    public function test_si_la_cita_esta_cancelada_o_ya_paso_o_la_cubre_otro_bloque_si_se_puede_eliminar(): void
    {
        $medico = $this->medico();
        $this->actingAs($this->administrador());

        // Cita cancelada: no cuenta.
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $this->cita($medico, '09:00', '09:20', 'CANCELADA');
        $this->delete(route('horarios.destroy', $horario))->assertSessionDoesntHaveErrors();
        $this->assertSame(0, HorarioAtencion::count());

        // Cita pasada: no cuenta.
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $this->cita($medico, '09:00', '09:20', 'PROGRAMADA', CarbonImmutable::now()->previous(CarbonImmutable::MONDAY)->format('Y-m-d'));
        $this->delete(route('horarios.destroy', $horario))->assertSessionDoesntHaveErrors();
        $this->assertSame(0, HorarioAtencion::count());

        // Cita cubierta por otro bloque: se puede quitar el que no la cubre.
        $primero = $this->bloque($medico, 1, '08:00', '12:00');
        $this->bloque($medico, 1, '12:00', '18:00');
        $this->cita($medico, '14:00', '14:20');
        $this->delete(route('horarios.destroy', $primero))->assertSessionDoesntHaveErrors();
        $this->assertSame(1, HorarioAtencion::count());
    }

    public function test_no_acorta_un_bloque_si_deja_una_cita_agendada_fuera_de_la_atencion(): void
    {
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $this->cita($medico, '11:00', '11:20');

        $this->actingAs($this->administrador())
            ->from(route('horarios.edit', $horario))
            ->put(route('horarios.update', $horario), $this->datos(['hora_inicio' => '08:00', 'hora_fin' => '10:00']))
            ->assertSessionHas('error');

        $this->assertSame('12:00', $horario->fresh()->hasta(), 'no se aplicó el cambio');
        $this->assertSame(0, Bitacora::where('accion', 'horario_modificado')->count());
    }

    public function test_si_la_cita_sigue_dentro_del_bloque_si_se_puede_acortar_o_mover_el_consultorio(): void
    {
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $this->cita($medico, '09:00', '09:20');
        $this->actingAs($this->administrador());

        $this->put(route('horarios.update', $horario), $this->datos(['hora_inicio' => '08:30', 'hora_fin' => '10:00']))
            ->assertSessionDoesntHaveErrors();
        $this->put(route('horarios.update', $horario), $this->datos(['hora_inicio' => '08:30', 'hora_fin' => '10:00', 'consultorio' => 'Consultorio 9']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('10:00', $horario->fresh()->hasta());
    }

    public function test_mover_un_bloque_de_dia_con_citas_agendadas_tambien_se_rechaza(): void
    {
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $this->cita($medico, '09:00', '09:20');

        $this->actingAs($this->administrador())
            ->from(route('horarios.edit', $horario))
            ->put(route('horarios.update', $horario), $this->datos(['dia_semana' => 2]))
            ->assertSessionHas('error');

        $this->assertSame(1, $horario->fresh()->dia_semana);
    }

    public function test_las_citas_que_ya_estaban_fuera_del_horario_no_bloquean_otros_cambios(): void
    {
        $medico = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $this->cita($medico, '15:00', '15:20'); // ya estaba fuera del horario

        $this->actingAs($this->administrador())
            ->put(route('horarios.update', $horario), $this->datos(['hora_inicio' => '08:30', 'hora_fin' => '11:30']))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('11:30', $horario->fresh()->hasta());
    }

    public function test_las_citas_de_otro_medico_no_bloquean(): void
    {
        $medico = $this->medico();
        $otro = $this->medico();
        $horario = $this->bloque($medico, 1, '08:00', '12:00');
        $this->bloque($otro, 1, '08:00', '12:00');
        $this->cita($otro, '09:00', '09:20');

        $this->actingAs($this->administrador())
            ->delete(route('horarios.destroy', $horario))
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(1, HorarioAtencion::count());
    }

    // --- Base de datos ------------------------------------------------------------------

    public function test_la_base_rechaza_bloques_superpuestos_aunque_se_salte_la_aplicacion(): void
    {
        $medico = $this->medico();
        $this->bloque($medico, 1, '08:00', '12:00');

        $this->expectException(\Illuminate\Database\QueryException::class);
        $this->bloque($medico, 1, '11:00', '13:00');
    }

    public function test_la_base_acepta_bloques_pegados(): void
    {
        $medico = $this->medico();
        $this->bloque($medico, 1, '08:00', '12:00');
        $this->bloque($medico, 1, '12:00', '14:00');

        $this->assertSame(2, HorarioAtencion::count());
    }
}
