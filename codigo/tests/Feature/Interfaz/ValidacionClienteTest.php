<?php

namespace Tests\Feature\Interfaz;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\Sucursal;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * Validación en el cliente de las pantallas del Ciclo #2 (enunciado: validar en el cliente y en el
 * servidor). El comportamiento de public/js/validaciones.js se comprueba en el navegador (sección
 * 8.3.4); aquí se verifica que cada formulario traiga las marcas que ese script necesita y que el
 * servidor siga rechazando lo que el cliente adelanta.
 */
class ValidacionClienteTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private Cita $cita;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:10:00', 'America/La_Paz'));

        $especialidad = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 30])->refresh();
        $cuenta = $this->crearCuenta('MEDICO');
        $this->medico = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-1'])->refresh();
        $this->medico->especialidades()->sync([$especialidad->id]);
        foreach (range(1, 7) as $dia) {
            HorarioAtencion::create(['medico_id' => $this->medico->id, 'sucursal_id' => Sucursal::firstOrFail()->id, 'dia_semana' => $dia, 'hora_inicio' => '08:00', 'hora_fin' => '12:00']);
        }

        $this->cita = Cita::create([
            'paciente_id' => $this->crearPaciente()->id, 'medico_id' => $this->medico->id, 'especialidad_id' => $especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id, 'fecha' => '2026-11-16', 'hora_inicio' => '08:00', 'hora_fin' => '08:30',
        ])->refresh();
    }

    protected function tearDown(): void
    {
        Cita::flushEventListeners();

        parent::tearDown();
    }

    public function test_cancelar_valida_el_motivo_y_confirma_desde_el_script_no_con_onsubmit(): void
    {
        $html = $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->get(route('citas.comprobante', $this->cita))->assertOk()->getContent();

        $this->assertStringContainsString('data-no-vacio="Indique el motivo de la cancelación."', $html);
        $this->assertStringContainsString('data-confirmar="¿Cancelar la cita '.$this->cita->codigo, $html);
        $this->assertStringContainsString('class="needs-validation" novalidate', preg_replace('/\s+/', ' ', $html));
        $this->assertStringNotContainsString('onsubmit=', $html);
    }

    public function test_reprogramar_pide_la_fecha_y_el_horario_con_mensajes_propios(): void
    {
        $recepcion = $this->crearCuenta('RECEPCIONISTA');

        $paso1 = $this->actingAs($recepcion)->get(route('citas.reprogramar', $this->cita))->assertOk()->getContent();
        $this->assertStringContainsString('needs-validation', $paso1);
        $this->assertStringContainsString('Elija una fecha entre hoy y el', $paso1);

        $paso2 = $this->actingAs($recepcion)->get(route('citas.reprogramar', ['cita' => $this->cita, 'fecha' => '2026-11-17']))->assertOk()->getContent();
        $this->assertStringContainsString('data-al-menos-uno="Elija un horario disponible."', $paso2);
        $this->assertStringContainsString('data-mensaje-grupo', $paso2);
    }

    public function test_la_nota_clinica_avisa_si_todos_los_campos_estan_vacios_y_cuenta_caracteres(): void
    {
        $this->cita->update(['estado' => 'ATENDIDA']);
        $medico = $this->medico->persona->usuario;

        $html = $this->actingAs($medico)->get(route('notas.formulario', $this->cita))->assertOk()->getContent();

        $this->assertStringContainsString('data-mensaje-textos="Complete al menos un campo de la nota clínica."', $html);
        $this->assertSame(4, substr_count($html, 'data-grupo-texto'));
        $this->assertSame(4, substr_count($html, 'data-contador-de='));
        $this->assertStringContainsString('data-aviso-textos', $html);

        // El servidor sigue rechazando la nota vacía aunque el cliente falle.
        $this->actingAs($medico)->from('/volver')->put(route('notas.guardar', $this->cita), [])->assertSessionHasErrors('motivo_consulta');
    }

    public function test_reportes_marca_el_rango_de_fechas_y_el_servidor_corrige_el_rango_al_reves(): void
    {
        $admin = $this->crearCuenta('ADMINISTRADOR');

        $html = $this->actingAs($admin)->get(route('reportes.index'))->assertOk()->getContent();
        $this->assertStringContainsString('data-fecha-desde', $html);
        $this->assertStringContainsString('data-fecha-hasta', $html);

        $this->actingAs($admin)->get(route('reportes.index', ['desde' => '2026-11-20', 'hasta' => '2026-11-01']))
            ->assertOk()->assertSee('no puede ser posterior');
    }

    public function test_el_servidor_rechaza_un_motivo_de_cancelacion_en_blanco_aunque_el_cliente_falle(): void
    {
        $this->actingAs($this->crearCuenta('RECEPCIONISTA'))->from('/volver')
            ->patch(route('citas.cancelar', $this->cita), ['motivo_cancelacion' => '   '])
            ->assertSessionHasErrors('motivo_cancelacion');

        $this->assertSame('PROGRAMADA', $this->cita->refresh()->estado);
    }
}
