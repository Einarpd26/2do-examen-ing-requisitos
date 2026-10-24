<?php

namespace App\Services;

use App\Models\Usuario;
use Carbon\CarbonImmutable;

/**
 * Contraseñas temporales (RNF18): aleatorias, con vencimiento de 72 horas, que el titular
 * debe cambiar en su primer ingreso. Solo se muestran una vez; en la base queda su hash.
 */
class ContrasenaTemporalService
{
    public const VIGENCIA_HORAS = 72;

    // Sin caracteres ambiguos (0/O, 1/l/I) para que sea fácil de dictar o copiar.
    private const MAYUSCULAS = 'ABCDEFGHJKLMNPQRSTUVWXYZ';

    private const MINUSCULAS = 'abcdefghijkmnpqrstuvwxyz';

    private const DIGITOS = '23456789';

    /**
     * Genera una contraseña con al menos una mayúscula, una minúscula y un dígito,
     * usando un generador criptográfico.
     */
    public function generar(int $longitud = 14): string
    {
        $todos = self::MAYUSCULAS.self::MINUSCULAS.self::DIGITOS;
        $caracteres = [
            $this->elegir(self::MAYUSCULAS),
            $this->elegir(self::MINUSCULAS),
            $this->elegir(self::DIGITOS),
        ];

        while (count($caracteres) < $longitud) {
            $caracteres[] = $this->elegir($todos);
        }

        // Fisher-Yates, para que los tres caracteres garantizados no queden siempre al inicio.
        for ($i = count($caracteres) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$caracteres[$i], $caracteres[$j]] = [$caracteres[$j], $caracteres[$i]];
        }

        return implode('', $caracteres);
    }

    public function vencimiento(): CarbonImmutable
    {
        return CarbonImmutable::now()->addHours(self::VIGENCIA_HORAS);
    }

    /**
     * Asigna a la cuenta una contraseña temporal nueva (alta o renovación): la anterior deja
     * de servir, se levanta el bloqueo y se obliga a cambiarla en el primer ingreso.
     *
     * @return array{contrasena: string, vence: CarbonImmutable} Para mostrar una sola vez.
     */
    public function emitirPara(Usuario $usuario): array
    {
        $contrasena = $this->generar();
        $vence = $this->vencimiento();

        $usuario->forceFill([
            'password_hash' => $contrasena,
            'debe_cambiar_password' => true,
            'password_temporal_vence' => $vence,
            'intentos_fallidos' => 0,
            'bloqueado_hasta' => null,
        ])->save();

        return ['contrasena' => $contrasena, 'vence' => $vence];
    }

    private function elegir(string $caracteres): string
    {
        return $caracteres[random_int(0, strlen($caracteres) - 1)];
    }
}
