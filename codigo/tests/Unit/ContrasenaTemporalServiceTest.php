<?php

namespace Tests\Unit;

use App\Services\ContrasenaTemporalService;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class ContrasenaTemporalServiceTest extends TestCase
{
    public function test_genera_una_contrasena_con_mayuscula_minuscula_y_digito(): void
    {
        $servicio = new ContrasenaTemporalService;

        for ($i = 0; $i < 200; $i++) {
            $contrasena = $servicio->generar();

            $this->assertSame(14, strlen($contrasena));
            $this->assertMatchesRegularExpression('/[A-Z]/', $contrasena);
            $this->assertMatchesRegularExpression('/[a-z]/', $contrasena);
            $this->assertMatchesRegularExpression('/[0-9]/', $contrasena);
        }
    }

    public function test_no_usa_caracteres_ambiguos(): void
    {
        $servicio = new ContrasenaTemporalService;

        for ($i = 0; $i < 200; $i++) {
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $servicio->generar());
        }
    }

    public function test_dos_contrasenas_seguidas_son_distintas(): void
    {
        $servicio = new ContrasenaTemporalService;

        $this->assertNotSame($servicio->generar(), $servicio->generar());
    }

    public function test_respeta_la_longitud_pedida(): void
    {
        $this->assertSame(20, strlen((new ContrasenaTemporalService)->generar(20)));
    }

    public function test_vence_a_las_72_horas(): void
    {
        CarbonImmutable::setTestNow('2026-10-24 10:00:00');

        $vence = (new ContrasenaTemporalService)->vencimiento();

        $this->assertSame('2026-10-27 10:00:00', $vence->toDateTimeString());

        CarbonImmutable::setTestNow();
    }
}
