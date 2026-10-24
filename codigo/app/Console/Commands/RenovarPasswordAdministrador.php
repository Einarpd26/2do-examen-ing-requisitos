<?php

namespace App\Console\Commands;

use App\Models\Usuario;
use App\Services\BitacoraService;
use App\Services\ContrasenaTemporalService;
use Illuminate\Console\Command;

/**
 * Recuperación de emergencia (CU-19): si un Administrador olvida su contraseña y no hay otro
 * Administrador que se la renueve, quien administra el servidor puede generarle una desde la
 * consola. Solo sirve para cuentas con rol ADMINISTRADOR; las demás las renueva la aplicación.
 */
class RenovarPasswordAdministrador extends Command
{
    protected $signature = 'administrador:renovar-password
                            {correo : Correo del Administrador}
                            {--force : No pedir confirmación}';

    protected $description = 'Genera una contraseña temporal nueva para un Administrador (recuperación de emergencia)';

    public function handle(ContrasenaTemporalService $contrasenas, BitacoraService $bitacora): int
    {
        $correo = strtolower(trim($this->argument('correo')));

        $cuenta = Usuario::query()
            ->with('persona', 'rol')
            ->whereHas('persona', fn ($persona) => $persona->whereRaw('lower(correo) = ?', [$correo]))
            ->first();

        if ($cuenta === null || $cuenta->rol->codigo !== 'ADMINISTRADOR') {
            $this->error('No existe un Administrador con ese correo.');

            return self::FAILURE;
        }

        if (! $cuenta->estado) {
            $this->error('La cuenta está desactivada. Otro Administrador debe activarla antes.');

            return self::FAILURE;
        }

        $nombre = $cuenta->persona->nombre_completo;

        if (! $this->option('force') && ! $this->confirm("¿Generar una contraseña temporal nueva para {$nombre}?")) {
            $this->line('Cancelado. No se cambió nada.');

            return self::SUCCESS;
        }

        $emitida = $contrasenas->emitirPara($cuenta);

        // Sin usuario: quien actúa es el administrador del servidor, no una cuenta del sistema.
        $bitacora->registrar('acceso_renovado', null, 'usuarios', $cuenta->id, 'Renovada desde la consola del servidor');

        $this->info("Contraseña temporal generada para {$nombre}. No se vuelve a mostrar:");
        $this->line("  Correo:     {$correo}");
        $this->line("  Contraseña: {$emitida['contrasena']}");
        $this->line("  Vence:      {$emitida['vence']->format('d/m/Y H:i')} (hora de Bolivia)");
        $this->line('Debe cambiarla al entrar.');

        return self::SUCCESS;
    }
}
