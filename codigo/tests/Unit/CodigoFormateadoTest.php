<?php

namespace Tests\Unit;

use App\Models\Cita;
use App\Models\Medico;
use App\Models\Paciente;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CodigoFormateadoTest extends TestCase
{
    public function test_el_codigo_se_arma_con_prefijo_y_seis_digitos(): void
    {
        $paciente = new Paciente;
        $paciente->id = 42;
        $medico = new Medico;
        $medico->id = 7;
        $cita = new Cita;
        $cita->id = 123456;

        $this->assertSame('PAC-000042', $paciente->codigo);
        $this->assertSame('MED-000007', $medico->codigo);
        $this->assertSame('CM-123456', $cita->codigo);
    }

    public function test_pasado_el_999999_no_se_trunca(): void
    {
        $paciente = new Paciente;
        $paciente->id = 1000000;

        $this->assertSame('PAC-1000000', $paciente->codigo);
    }

    #[DataProvider('textosValidos')]
    public function test_convierte_un_codigo_o_un_numero_en_id(string $texto, int $esperado): void
    {
        $this->assertSame($esperado, Paciente::idDesdeCodigo($texto));
    }

    public static function textosValidos(): array
    {
        return [
            'codigo completo' => ['PAC-000042', 42],
            'minusculas' => ['pac-000042', 42],
            'sin ceros' => ['PAC-42', 42],
            'solo el numero' => ['42', 42],
            'con espacios' => ['  PAC-000042  ', 42],
            'mas de seis digitos' => ['PAC-1000000', 1000000],
        ];
    }

    #[DataProvider('textosInvalidos')]
    public function test_rechaza_lo_que_no_es_un_codigo(string $texto): void
    {
        $this->assertNull(Paciente::idDesdeCodigo($texto));
    }

    public static function textosInvalidos(): array
    {
        return [
            'prefijo de otra entidad' => ['MED-000042'],
            'texto libre' => ['Ana Paz'],
            'vacio' => [''],
            'con letras al final' => ['PAC-42x'],
            'negativo' => ['-5'],
        ];
    }
}
