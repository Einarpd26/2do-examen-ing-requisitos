<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Paciente;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CU-04: reglas de la gestión de pacientes. Cada operación escribe en la bitácora.
 */
class PacienteService
{
    private const CAMPOS_PERSONA = [
        'ci', 'primer_nombre', 'segundo_nombre', 'apellido_paterno', 'apellido_materno',
        'fecha_nacimiento', 'sexo', 'telefono', 'correo', 'direccion',
    ];

    private const CAMPOS_FICHA = [
        'tipo_sangre', 'alergias', 'enfermedades_cronicas', 'medicamentos', 'antecedentes',
        'emergencia_nombre', 'emergencia_parentesco', 'emergencia_telefono',
    ];

    public function __construct(
        private readonly ContrasenaTemporalService $contrasenas,
        private readonly BitacoraService $bitacora,
    ) {}

    /**
     * Registra un paciente. Si el CI ya existe como persona sin ficha de paciente (por ejemplo,
     * un médico), se reutiliza esa persona y no se tocan sus datos personales.
     * La ficha creada por la Recepcionista queda verificada de inmediato.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: Paciente, 1: bool} la ficha y si se reutilizó una persona existente
     */
    public function registrar(array $datos, Usuario $actor): array
    {
        return DB::transaction(function () use ($datos, $actor) {
            $persona = Persona::where('ci', $datos['ci'])->first();
            $reutilizada = $persona !== null;
            $persona ??= Persona::create(Arr::only($datos, self::CAMPOS_PERSONA));

            if ($persona->paciente()->exists()) {
                throw new ReglaDeNegocioException('Ya existe un paciente registrado con este CI.');
            }

            $paciente = Paciente::create(
                ['persona_id' => $persona->id, 'verificado' => true] + Arr::only($datos, self::CAMPOS_FICHA)
            )->refresh();

            $this->bitacora->registrar('paciente_creado', $actor, 'pacientes', $paciente->id);

            return [$paciente, $reutilizada];
        });
    }

    /** @param  array<string, mixed>  $datos */
    public function actualizar(Paciente $paciente, array $datos, Usuario $actor): void
    {
        DB::transaction(function () use ($paciente, $datos, $actor) {
            $paciente->persona->update(Arr::only($datos, self::CAMPOS_PERSONA));
            $paciente->update(Arr::only($datos, self::CAMPOS_FICHA));

            $this->bitacora->registrar('paciente_modificado', $actor, 'pacientes', $paciente->id);
        });
    }

    /**
     * Desactiva o reactiva al paciente sin borrar nada (RN11). Su cuenta de paciente, si la
     * tiene, sigue el mismo estado: un paciente desactivado no puede iniciar sesión.
     */
    public function cambiarEstado(Paciente $paciente, bool $activo, Usuario $actor): void
    {
        DB::transaction(function () use ($paciente, $activo, $actor) {
            $paciente->update(['estado' => $activo]);

            $cuenta = $paciente->persona->usuario;
            if ($cuenta !== null && $cuenta->rol->codigo === 'PACIENTE') {
                $cuenta->update(['estado' => $activo]);
            }

            $this->bitacora->registrar(
                $activo ? 'paciente_reactivado' : 'paciente_desactivado', $actor, 'pacientes', $paciente->id
            );
        });
    }

    /**
     * Marca como verificada una ficha de registro propio, después de comparar el documento.
     * Puede renovar la contraseña en el mismo acto, para sacar de la cuenta a quien se haya
     * registrado con un CI ajeno.
     *
     * @return array{contrasena: string, vence: \Carbon\CarbonImmutable, usuario: string}|null
     */
    public function verificar(Paciente $paciente, bool $renovarAcceso, Usuario $actor): ?array
    {
        if ($paciente->verificado) {
            throw new ReglaDeNegocioException('La ficha ya está verificada.');
        }

        return DB::transaction(function () use ($paciente, $renovarAcceso, $actor) {
            $paciente->update(['verificado' => true]);
            $this->bitacora->registrar('paciente_verificado', $actor, 'pacientes', $paciente->id);

            return $renovarAcceso ? $this->renovarAcceso($paciente, $actor) : null;
        });
    }

    /**
     * Crea la cuenta del paciente con una contraseña temporal. El paciente entra con su CI.
     *
     * @return array{contrasena: string, vence: \Carbon\CarbonImmutable, usuario: string}
     */
    public function crearAcceso(Paciente $paciente, Usuario $actor): array
    {
        if (! $paciente->estado) {
            throw new ReglaDeNegocioException('No se puede crear un acceso para un paciente desactivado.');
        }

        $persona = $paciente->persona;

        if ($persona->usuario !== null) {
            throw new ReglaDeNegocioException(
                $persona->usuario->rol->codigo === 'PACIENTE'
                    ? 'El paciente ya tiene una cuenta.'
                    : "Esta persona ya tiene una cuenta con rol {$persona->usuario->rol->nombre}. Como paciente la atiende la recepción."
            );
        }

        return DB::transaction(function () use ($paciente, $persona, $actor) {
            // La contraseña real la fija emitirPara(); esta es solo un valor de relleno.
            $cuenta = Usuario::create([
                'persona_id' => $persona->id,
                'rol_id' => Rol::where('codigo', 'PACIENTE')->firstOrFail()->id,
                'password_hash' => Str::random(64),
            ]);

            $emitida = $this->contrasenas->emitirPara($cuenta);
            $this->bitacora->registrar('acceso_creado', $actor, 'usuarios', $cuenta->id);

            return $emitida + ['usuario' => $persona->ci];
        });
    }

    /**
     * Genera una contraseña temporal nueva (venció u olvidada). La anterior deja de servir.
     *
     * @return array{contrasena: string, vence: \Carbon\CarbonImmutable, usuario: string}
     */
    public function renovarAcceso(Paciente $paciente, Usuario $actor): array
    {
        $cuenta = $paciente->persona->usuario;

        if ($cuenta === null) {
            throw new ReglaDeNegocioException('El paciente todavía no tiene una cuenta.');
        }

        if ($cuenta->rol->codigo !== 'PACIENTE') {
            throw new ReglaDeNegocioException('La cuenta de esta persona no es de paciente: la renueva el Administrador.');
        }

        if (! $paciente->estado) {
            throw new ReglaDeNegocioException('No se puede renovar el acceso de un paciente desactivado.');
        }

        return DB::transaction(function () use ($paciente, $cuenta, $actor) {
            $emitida = $this->contrasenas->emitirPara($cuenta);
            $this->bitacora->registrar('acceso_renovado', $actor, 'usuarios', $cuenta->id);

            return $emitida + ['usuario' => $paciente->persona->ci];
        });
    }
}
