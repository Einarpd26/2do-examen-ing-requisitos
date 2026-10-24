<?php

namespace Database\Seeders;

use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\ContrasenaTemporalService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Crea la primera cuenta con rol ADMINISTRADOR. Se asigna una contraseña temporal que se
 * muestra una sola vez en la consola y debe cambiarse en el primer ingreso.
 */
class AdministradorInicialSeeder extends Seeder
{
    public function run(ContrasenaTemporalService $contrasenas): void
    {
        $datos = config('clinica.admin');

        if (Persona::where('ci', $datos['ci'])->exists()) {
            $this->command->warn("Ya existe una persona con CI {$datos['ci']}; no se crea el administrador.");

            return;
        }

        $emitida = DB::transaction(function () use ($datos, $contrasenas) {
            $persona = Persona::create([
                'ci' => $datos['ci'],
                'primer_nombre' => $datos['primer_nombre'],
                'apellido_paterno' => $datos['apellido_paterno'],
                'correo' => $datos['correo'],
            ]);

            // La contraseña real la fija emitirPara(); esta es solo un valor de relleno.
            $usuario = Usuario::create([
                'persona_id' => $persona->id,
                'rol_id' => Rol::where('codigo', 'ADMINISTRADOR')->firstOrFail()->id,
                'password_hash' => Str::random(64),
            ]);

            return $contrasenas->emitirPara($usuario);
        });

        $this->command->info('Administrador inicial creado. Esta contraseña no se vuelve a mostrar:');
        $this->command->line("  Correo:     {$datos['correo']}");
        $this->command->line("  Contraseña: {$emitida['contrasena']}");
        $this->command->line("  Vence:      {$emitida['vence']->format('d/m/Y H:i')} (hora de Bolivia)");
    }
}
