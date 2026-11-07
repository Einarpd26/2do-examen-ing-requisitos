<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Consultorio;
use App\Models\HorarioAtencion;
use App\Models\Medico;
use App\Models\NotaClinica;
use App\Models\Paciente;
use App\Models\Sucursal;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Citas y notas clínicas de demostración para recorrer el Ciclo #2 y tomar capturas de pantalla.
 *
 * Requiere haber corrido antes el DemoSeeder. Las fechas son relativas a hoy (hora de La Paz), así
 * que siempre hay citas pasadas, de hoy y próximas, y el recordatorio del Paciente tiene qué mostrar.
 * Solo corre con APP_ENV=local y no hace nada si ya existen citas. Se ejecuta con
 * `php artisan db:seed --class=DemoCitasSeeder`.
 */
class DemoCitasSeeder extends Seeder
{
    /** @var list<array{int, int, string, string, string|null, string|null}> días desde hoy, posición, CI, estado, motivo, motivo de cancelación */
    private const CITAS = [
        [-7, 0, '8000003', 'ATENDIDA', 'Control general', null],
        [-7, 1, '8000004', 'ATENDIDA', 'Dolor de cabeza frecuente', null],
        [-5, 0, '8000003', 'INASISTENCIA', 'Consulta por tos persistente', null],
        [-3, 0, '8000003', 'CANCELADA', 'Revisión de rutina', 'El paciente tuvo que viajar'],
        [-2, 0, '8000004', 'ATENDIDA', 'Control de presión arterial', null],
        [0, 2, '8000004', 'CONFIRMADA', 'Seguimiento de tratamiento', null],
        [1, 0, '8000003', 'CONFIRMADA', 'Resultados de laboratorio', null],
        [2, 0, '8000003', 'PROGRAMADA', 'Control de alergias', null],
        [2, 1, '8000004', 'PROGRAMADA', 'Chequeo general', null],
        [5, 0, '8000003', 'PROGRAMADA', 'Consulta de seguimiento', null],
        [9, 0, '8000003', 'PROGRAMADA', 'Control anual', null],
    ];

    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command->error('El DemoCitasSeeder solo corre con APP_ENV=local.');

            return;
        }

        $medico = Medico::whereHas('persona', fn ($q) => $q->where('ci', '8000002'))->first();
        if ($medico === null) {
            $this->command->error('Falta el médico de demostración: corra primero DemoSeeder.');

            return;
        }

        if (Cita::exists()) {
            $this->command->info('Ya existen citas: no se agrega nada.');

            return;
        }

        $creadas = DB::transaction(function () use ($medico) {
            $especialidad = $medico->especialidades()->firstOrFail();
            $sucursal = Sucursal::firstOrFail();
            $this->completarHorarios($medico, $sucursal);

            $hoy = CarbonImmutable::now(config('app.timezone'))->startOfDay();
            $pacientes = Paciente::whereHas('persona', fn ($q) => $q->whereIn('ci', ['8000003', '8000004']))
                ->with('persona')->get()->keyBy(fn ($p) => $p->persona->ci);
            $autor = $medico->persona->usuario;
            $atendidas = [];

            foreach (self::CITAS as $i => [$dias, $posicion, $ci, $estado, $motivo, $cancelacion]) {
                $fecha = $hoy->addDays($dias);
                $bloque = $medico->horarios()->where('dia_semana', $fecha->dayOfWeekIso)->orderBy('hora_inicio')->firstOrFail();
                $inicio = CarbonImmutable::parse($bloque->hora_inicio)->addMinutes($posicion * $especialidad->duracion_min);

                $cita = Cita::create([
                    'paciente_id' => $pacientes[$ci]->id,
                    'medico_id' => $medico->id,
                    'especialidad_id' => $especialidad->id,
                    'sucursal_id' => $sucursal->id,
                    'fecha' => $fecha->toDateString(),
                    'hora_inicio' => $inicio->format('H:i'),
                    'hora_fin' => $inicio->addMinutes($especialidad->duracion_min)->format('H:i'),
                    'modalidad' => $i % 4 === 3 ? 'VIRTUAL' : 'PRESENCIAL',
                    'estado' => $estado,
                    'motivo' => $motivo,
                    'motivo_cancelacion' => $cancelacion,
                ]);

                if ($estado === 'ATENDIDA') {
                    $atendidas[] = $cita;
                }
            }

            // La primera y la última cita atendida tienen nota; la del medio queda sin nota para
            // mostrar el enlace «Nota clínica» en la agenda.
            foreach ([$atendidas[0], $atendidas[count($atendidas) - 1]] as $cita) {
                NotaClinica::create([
                    'cita_id' => $cita->id,
                    'medico_id' => $medico->id,
                    'motivo_consulta' => $cita->motivo,
                    'diagnostico' => 'Sin hallazgos de gravedad en el examen físico.',
                    'tratamiento' => 'Reposo, hidratación y control en dos semanas.',
                    'observaciones' => 'Se indica volver antes si los síntomas empeoran.',
                    'modificado_por' => $autor?->id,
                ]);
            }

            return count(self::CITAS);
        });

        $this->command->info("Se crearon {$creadas} citas de demostración y 2 notas clínicas.");
    }

    /** Agrega un bloque de 08:00 a 12:00 en los días sin atención, para que haya horarios todos los días. */
    private function completarHorarios(Medico $medico, Sucursal $sucursal): void
    {
        $conAtencion = $medico->horarios()->pluck('dia_semana')->all();

        foreach (range(1, 7) as $dia) {
            if (in_array($dia, $conAtencion, true)) {
                continue;
            }

            HorarioAtencion::create([
                'medico_id' => $medico->id, 'sucursal_id' => $sucursal->id, 'dia_semana' => $dia,
                'hora_inicio' => '08:00', 'hora_fin' => '12:00', 'consultorio_id' => $this->consultorio($sucursal)->id,
            ]);
        }
    }

    /** El consultorio de demostración: se crea la primera vez y después se reutiliza (ADR-002). */
    private function consultorio(Sucursal $sucursal): Consultorio
    {
        return Consultorio::query()
            ->where('sucursal_id', $sucursal->id)
            ->whereRaw('lower(btrim(nombre)) = ?', ['consultorio 1'])
            ->first()
            ?? Consultorio::create(['sucursal_id' => $sucursal->id, 'nombre' => 'Consultorio 1']);
    }
}
