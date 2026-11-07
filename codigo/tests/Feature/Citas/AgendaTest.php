<?php

namespace Tests\Feature\Citas;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Medico;
use App\Models\Paciente;
use App\Models\Sucursal;
use App\Models\Usuario;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreaCuentas;
use Tests\TestCase;

/**
 * CU-13: agenda médica por fecha (RF11, RN12). Prueba 8 del enunciado.
 */
class AgendaTest extends TestCase
{
    use CreaCuentas;
    use RefreshDatabase;

    private const DIA = '2026-11-16';

    private const OTRO_DIA = '2026-11-17';

    private Especialidad $especialidad;

    private Medico $medico;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::parse('2026-11-09 09:10:00', 'America/La_Paz'));

        $this->especialidad = Especialidad::create(['nombre' => 'Pediatría', 'duracion_min' => 30])->refresh();
        $this->medico = $this->crearFichaMedico(['primer_nombre' => 'Marta', 'apellido_paterno' => 'Rojas'], [], [$this->especialidad->id]);
    }

    protected function tearDown(): void
    {
        Cita::flushEventListeners();

        parent::tearDown();
    }

    private function recepcionista(): Usuario
    {
        return $this->crearCuenta('RECEPCIONISTA');
    }

    private function cita(Paciente $paciente, string $fecha, string $inicio, string $estado = 'PROGRAMADA', ?Medico $medico = null, ?string $motivo = null): Cita
    {
        $medico ??= $this->medico;

        return Cita::create([
            'paciente_id' => $paciente->id,
            'medico_id' => $medico->id,
            'especialidad_id' => $this->especialidad->id,
            'sucursal_id' => Sucursal::firstOrFail()->id,
            'fecha' => $fecha,
            'hora_inicio' => $inicio,
            'hora_fin' => CarbonImmutable::parse($inicio)->addMinutes(30)->format('H:i'),
            'estado' => $estado,
            'motivo' => $motivo,
        ])->refresh();
    }

    private function consultar(Usuario $actor, array $parametros)
    {
        return $this->actingAs($actor)->get(route('agenda.index', $parametros));
    }

    // --- Prueba 8 del enunciado ---------------------------------------------------------

    public function test_muestra_las_citas_del_medico_en_la_fecha_pedida(): void
    {
        $lucia = $this->crearPaciente(['primer_nombre' => 'Lucía', 'apellido_paterno' => 'Vaca']);
        $pedro = $this->crearPaciente(['primer_nombre' => 'Pedro', 'apellido_paterno' => 'Soria']);
        $tarde = $this->cita($pedro, self::DIA, '10:00', 'PROGRAMADA', null, 'Dolor de oído');
        $temprano = $this->cita($lucia, self::DIA, '08:00');
        $otroDia = $this->cita($lucia, self::OTRO_DIA, '08:00');

        $otroMedico = $this->crearFichaMedico([], [], [$this->especialidad->id]);
        $deOtro = $this->cita($pedro, self::DIA, '08:00', 'PROGRAMADA', $otroMedico);

        $this->consultar($this->recepcionista(), ['medico' => $this->medico->id, 'fecha' => self::DIA])
            ->assertOk()
            ->assertSee($temprano->codigo)
            ->assertSee($tarde->codigo)
            // Hora, paciente, especialidad, estado y motivo breve (RF11).
            ->assertSee('08:00–08:30')
            ->assertSee('Lucía')
            ->assertSee('Pedro')
            ->assertSee('Pediatría')
            ->assertSee('Programada')
            ->assertSee('Dolor de oído')
            // Solo las de ese médico y de esa fecha.
            ->assertDontSee($otroDia->codigo)
            ->assertDontSee($deOtro->codigo)
            // En orden de hora.
            ->assertSeeInOrder([$temprano->codigo, $tarde->codigo]);
    }

    public function test_una_fecha_sin_citas_muestra_un_aviso(): void
    {
        $this->consultar($this->recepcionista(), ['medico' => $this->medico->id, 'fecha' => self::DIA])
            ->assertOk()
            ->assertSee('No hay citas en esa fecha');
    }

    public function test_las_canceladas_aparecen_en_la_agenda_con_su_estado(): void
    {
        $cita = $this->cita($this->crearPaciente(), self::DIA, '08:00', 'CANCELADA');

        $this->consultar($this->recepcionista(), ['medico' => $this->medico->id, 'fecha' => self::DIA])
            ->assertSee($cita->codigo)
            ->assertSee('Cancelada');
    }

    // --- Filtros -------------------------------------------------------------------------

    public function test_filtra_por_estado(): void
    {
        $programada = $this->cita($this->crearPaciente(), self::DIA, '08:00');
        $atendida = $this->cita($this->crearPaciente(), self::DIA, '09:00', 'ATENDIDA');

        $this->consultar($this->recepcionista(), ['medico' => $this->medico->id, 'fecha' => self::DIA, 'estado' => 'ATENDIDA'])
            ->assertSee($atendida->codigo)
            ->assertDontSee($programada->codigo);
    }

    public function test_la_recepcionista_sin_elegir_medico_ve_las_citas_de_todos_en_la_fecha(): void
    {
        $paciente = $this->crearPaciente();
        $deMarta = $this->cita($paciente, self::DIA, '10:00');
        $otroMedico = $this->crearFichaMedico(['primer_nombre' => 'Jorge', 'apellido_paterno' => 'Vaca'], [], [$this->especialidad->id]);
        $deJorge = $this->cita($this->crearPaciente(), self::DIA, '08:00', 'CONFIRMADA', $otroMedico);
        $otroDia = $this->cita($paciente, self::OTRO_DIA, '08:00');

        $this->consultar($this->recepcionista(), ['fecha' => self::DIA])
            ->assertOk()
            ->assertSee('Todos los médicos')
            ->assertSee($deMarta->codigo)
            ->assertSee($deJorge->codigo)
            ->assertDontSee($otroDia->codigo)
            // Muestra de qué médico es cada cita y las ordena por hora.
            ->assertSee('Marta')
            ->assertSee('Jorge')
            ->assertSeeInOrder([$deJorge->codigo, $deMarta->codigo]);
    }

    public function test_sin_medico_tambien_filtra_por_estado_y_avisa_si_no_hay_citas(): void
    {
        $paciente = $this->crearPaciente();
        $confirmada = $this->cita($paciente, self::DIA, '08:00', 'CONFIRMADA');
        $atendida = $this->cita($this->crearPaciente(), self::DIA, '09:00', 'ATENDIDA');
        $recepcion = $this->recepcionista();

        $this->consultar($recepcion, ['fecha' => self::DIA, 'estado' => 'ATENDIDA'])
            ->assertSee($atendida->codigo)->assertDontSee($confirmada->codigo);

        $this->consultar($recepcion, ['fecha' => '2026-12-01'])->assertSee('No hay citas en esa fecha');
    }

    public function test_con_un_medico_elegido_solo_muestra_las_de_ese_medico_sin_la_columna_medico(): void
    {
        $propia = $this->cita($this->crearPaciente(), self::DIA, '08:00');
        $otroMedico = $this->crearFichaMedico([], [], [$this->especialidad->id]);
        $ajena = $this->cita($this->crearPaciente(), self::DIA, '09:00', 'PROGRAMADA', $otroMedico);

        $this->consultar($this->recepcionista(), ['medico' => $this->medico->id, 'fecha' => self::DIA])
            ->assertSee($propia->codigo)->assertDontSee($ajena->codigo)->assertDontSee('<th>Médico</th>', false);
    }

    public function test_los_parametros_manipulados_se_ignoran_sin_error(): void
    {
        $cita = $this->cita($this->crearPaciente(), CarbonImmutable::now('America/La_Paz')->toDateString(), '11:00');
        $recepcion = $this->recepcionista();

        // Fecha inválida: se muestra hoy con un aviso. Estado inventado: se ignora.
        $this->consultar($recepcion, ['medico' => $this->medico->id, 'fecha' => '2026-13-45', 'estado' => 'INVENTADO'])
            ->assertOk()
            ->assertSee('La fecha no es válida')
            ->assertSee($cita->codigo);

        // Arreglos en vez de texto y un médico inexistente no producen error 500.
        $this->actingAs($recepcion)->get(route('agenda.index').'?medico[]=1&fecha[]=x&estado[]=y')->assertOk();
        // Un médico inexistente se ignora y se muestran las citas de todos.
        $this->consultar($recepcion, ['medico' => 999999])->assertOk()->assertSee('Todos los médicos');
    }

    // --- El Médico ve solo su agenda -----------------------------------------------------

    public function test_el_medico_ve_su_propia_agenda_y_no_la_de_otro(): void
    {
        $cuenta = $this->crearCuenta('MEDICO');
        $suyo = Medico::create(['persona_id' => $cuenta->persona_id, 'nro_registro_profesional' => 'MP-PROPIO'])->refresh();
        $suyo->especialidades()->sync([$this->especialidad->id]);

        $paciente = $this->crearPaciente();
        $mia = $this->cita($paciente, self::DIA, '08:00', 'PROGRAMADA', $suyo);
        $ajena = $this->cita($paciente, self::DIA, '09:00');

        // Aunque pida otro médico en la URL, ve la suya.
        $this->consultar($cuenta, ['medico' => $this->medico->id, 'fecha' => self::DIA])
            ->assertOk()
            ->assertSee('Mi agenda')
            ->assertSee($mia->codigo)
            ->assertDontSee($ajena->codigo)
            ->assertDontSee('Todos los médicos');
    }

    public function test_una_cuenta_de_medico_sin_ficha_recibe_404(): void
    {
        $this->consultar($this->crearCuenta('MEDICO'), ['fecha' => self::DIA])->assertNotFound();
    }

    // --- Permisos -------------------------------------------------------------------------

    public function test_el_paciente_y_el_administrador_no_consultan_la_agenda(): void
    {
        foreach (['PACIENTE', 'ADMINISTRADOR'] as $rol) {
            $this->consultar($this->crearCuenta($rol), ['medico' => $this->medico->id])->assertForbidden();
        }
    }

    public function test_sin_sesion_redirige_al_login(): void
    {
        $this->get(route('agenda.index'))->assertRedirect(route('login'));
    }

    public function test_el_menu_ofrece_la_agenda_a_recepcion_y_medico(): void
    {
        $this->actingAs($this->recepcionista())->get(route('inicio'))->assertSee('Agenda');
        $this->actingAs($this->crearCuenta('MEDICO'))->get(route('inicio'))->assertSee('Agenda');
        $this->actingAs($this->crearCuenta('PACIENTE'))->get(route('inicio'))->assertDontSee('Agenda');
    }
}
