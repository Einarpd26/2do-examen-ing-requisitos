<?php

namespace Tests\Feature\Consola;

use App\Models\Cita;
use App\Models\NotaClinica;
use Database\Seeders\DemoCitasSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Citas y notas de demostración: cubren todos los estados y respetan las restricciones de la base.
 */
class DemoCitasSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_citas_de_todos_los_estados_y_dos_notas(): void
    {
        $this->app['env'] = 'local';
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertSuccessful();

        $this->artisan('db:seed', ['--class' => DemoCitasSeeder::class])->assertSuccessful();

        $estados = Cita::pluck('estado')->unique()->sort()->values()->all();
        $this->assertSame(['ATENDIDA', 'CANCELADA', 'CONFIRMADA', 'INASISTENCIA', 'PROGRAMADA'], $estados);
        $this->assertSame(2, NotaClinica::count());
    }

    public function test_es_idempotente_y_no_corre_fuera_del_entorno_local(): void
    {
        $this->app['env'] = 'local';
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])->assertSuccessful();
        $this->artisan('db:seed', ['--class' => DemoCitasSeeder::class])->assertSuccessful();
        $total = Cita::count();

        $this->artisan('db:seed', ['--class' => DemoCitasSeeder::class])->assertSuccessful();

        $this->assertSame($total, Cita::count());

        $this->app['env'] = 'production';
        NotaClinica::query()->delete();
        Cita::query()->delete();
        $this->artisan('db:seed', ['--class' => DemoCitasSeeder::class, '--force' => true])->assertSuccessful();
        $this->assertSame(0, Cita::count());
    }
}
