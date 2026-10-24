<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * La interfaz usa una sola paleta: el verde de la clínica es también el color primario de
 * Bootstrap, para que insignias, enlaces y campos no vuelvan al azul por defecto.
 */
class PaletaTest extends TestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $this->css = file_get_contents(__DIR__.'/../../public/css/clinica.css');
    }

    public function test_el_verde_de_la_clinica_es_el_color_primario_de_bootstrap(): void
    {
        $this->assertStringContainsString('--clinica: #0b6e6e;', $this->css);
        $this->assertStringContainsString('--bs-primary: #0b6e6e;', $this->css);
        $this->assertStringContainsString('--bs-primary-rgb: 11, 110, 110;', $this->css);
        $this->assertStringContainsString('--bs-link-color-rgb: 11, 110, 110;', $this->css);
    }

    public function test_el_rgb_del_primario_coincide_con_el_hexadecimal(): void
    {
        [$r, $g, $b] = sscanf('#0b6e6e', '#%02x%02x%02x');

        $this->assertSame("--bs-primary-rgb: {$r}, {$g}, {$b};", $this->extraer('/--bs-primary-rgb:[^;]+;/'));
    }

    public function test_no_quedan_azules_de_bootstrap_en_los_componentes_que_se_sobrescriben(): void
    {
        foreach (['#0d6efd', '#86b7fe', 'rgba(13, 110, 253'] as $azul) {
            $this->assertStringNotContainsString($azul, $this->css);
        }

        foreach (['.form-control:focus', '.form-check-input:checked', '.pagination'] as $componente) {
            $this->assertStringContainsString($componente, $this->css);
        }
    }

    private function extraer(string $patron): string
    {
        preg_match($patron, $this->css, $coincidencia);

        return $coincidencia[0] ?? '';
    }
}
