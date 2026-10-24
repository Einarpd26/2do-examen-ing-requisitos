<?php

namespace App\Services;

use App\Exceptions\ReglaDeNegocioException;
use App\Models\Medico;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Usuario;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * CU-03: reglas de la gestión de cuentas del personal (Administrador, Recepcionista y Médico).
 * Las cuentas de pacientes se gestionan en CU-02 y CU-04. Una cuenta nunca se elimina: se
 * desactiva. Cada operación escribe en la bitácora.
 *
 * Protecciones: el sistema siempre conserva al menos un Administrador activo, y nadie puede
 * desactivarse, cambiarse el rol ni renovarse la contraseña a sí mismo desde aquí.
 */
class UsuarioService
{
    public const ROLES_DE_PERSONAL = ['ADMINISTRADOR', 'RECEPCIONISTA', 'MEDICO'];

    /** Roles que se pueden intercambiar al modificar una cuenta. */
    public const ROLES_INTERCAMBIABLES = ['ADMINISTRADOR', 'RECEPCIONISTA'];

    private const CAMPOS_PERSONA = [
        'ci', 'primer_nombre', 'segundo_nombre', 'apellido_paterno', 'apellido_materno', 'telefono', 'correo',
    ];

    public function __construct(
        private readonly ContrasenaTemporalService $contrasenas,
        private readonly BitacoraService $bitacora,
    ) {}

    /**
     * Crea la cuenta con una contraseña temporal. Para el rol Médico la cuenta se vincula a un
     * médico ya registrado (CU-05) y los datos personales son los suyos. Para los demás roles,
     * si el CI ya existe como persona sin cuenta, se reutiliza esa persona.
     *
     * @param  array<string, mixed>  $datos
     * @return array{0: Usuario, 1: array{contrasena: string, vence: \Carbon\CarbonImmutable, usuario: string}, 2: bool}
     *                                                                                                                  la cuenta, la contraseña temporal para mostrar una vez y si se reutilizó una persona existente
     */
    public function crear(array $datos, Usuario $actor): array
    {
        return $this->conReglasDeLaBase(fn () => DB::transaction(function () use ($datos, $actor) {
            $rol = Rol::where('codigo', $datos['rol'])->firstOrFail();

            if ($rol->codigo === 'MEDICO') {
                $medico = Medico::with('persona.usuario.rol')->findOrFail($datos['medico_id']);

                if (! $medico->estado) {
                    throw new ReglaDeNegocioException('El médico está desactivado: reactívelo para crearle una cuenta.');
                }

                $persona = $medico->persona;
                $reutilizada = true;
            } else {
                $persona = Persona::with('usuario.rol')->where('ci', $datos['ci'])->first();
                $reutilizada = $persona !== null;

                if ($persona === null) {
                    $persona = Persona::create(Arr::only($datos, self::CAMPOS_PERSONA));
                } elseif ($persona->correo === null && $persona->usuario === null) {
                    // El correo es el usuario del personal: si la persona no tenía, se usa el indicado.
                    $persona->update(['correo' => $datos['correo']]);
                }
            }

            if ($persona->usuario !== null) {
                throw new ReglaDeNegocioException("Esta persona ya tiene una cuenta con rol {$persona->usuario->rol->nombre}.");
            }

            if ($persona->correo === null) {
                throw new ReglaDeNegocioException('La persona no tiene correo: el personal entra al sistema con su correo.');
            }

            // La contraseña real la fija emitirPara(); esta es solo un valor de relleno.
            $cuenta = Usuario::create([
                'persona_id' => $persona->id,
                'rol_id' => $rol->id,
                'password_hash' => Str::random(64),
            ]);

            $emitida = $this->contrasenas->emitirPara($cuenta);
            $this->bitacora->registrar('usuario_creado', $actor, 'usuarios', $cuenta->id, "Rol: {$rol->nombre}");

            return [$cuenta, $emitida + ['usuario' => $persona->correo], $reutilizada];
        }));
    }

    /**
     * Modifica los datos personales y el rol (entre Administrador y Recepcionista). Los datos de
     * una cuenta de médico se editan en Médicos (CU-05).
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizar(Usuario $cuenta, array $datos, Usuario $actor): void
    {
        $this->conReglasDeLaBase(function () use ($cuenta, $datos, $actor) {
            DB::transaction(function () use ($cuenta, $datos, $actor) {
                $cuenta->loadMissing('persona', 'rol');

                if ($cuenta->rol->codigo === 'MEDICO') {
                    throw new ReglaDeNegocioException('Los datos de una cuenta de médico se editan en Médicos.');
                }

                $cuenta->persona->update(Arr::only($datos, self::CAMPOS_PERSONA));

                $detalle = 'Datos personales';
                $nuevoRol = $datos['rol'];

                if ($nuevoRol !== $cuenta->rol->codigo) {
                    if ($cuenta->id === $actor->id) {
                        throw new ReglaDeNegocioException('No puede cambiar su propio rol.');
                    }

                    $this->exigirOtroAdministradorActivo($cuenta);

                    $rolNuevo = Rol::where('codigo', $nuevoRol)->firstOrFail();
                    $detalle .= "; rol: {$cuenta->rol->nombre} → {$rolNuevo->nombre}";
                    $cuenta->update(['rol_id' => $rolNuevo->id]);
                }

                $this->bitacora->registrar('usuario_modificado', $actor, 'usuarios', $cuenta->id, $detalle);
            });
        });
    }

    /**
     * Desactiva o reactiva la cuenta. Nadie se desactiva a sí mismo y el sistema conserva siempre
     * un Administrador activo. Una cuenta de médico no se reactiva mientras el médico esté dado
     * de baja (se reactiva primero en Médicos).
     */
    public function cambiarEstado(Usuario $cuenta, bool $activa, Usuario $actor): void
    {
        $cuenta->loadMissing('persona.medico', 'rol');

        if (! $activa) {
            if ($cuenta->id === $actor->id) {
                throw new ReglaDeNegocioException('No puede desactivar su propia cuenta.');
            }

            $this->exigirOtroAdministradorActivo($cuenta);
        } elseif ($cuenta->rol->codigo === 'MEDICO' && $cuenta->persona->medico !== null && ! $cuenta->persona->medico->estado) {
            throw new ReglaDeNegocioException('El médico está desactivado: reactívelo primero en Médicos.');
        }

        DB::transaction(function () use ($cuenta, $activa, $actor) {
            $cuenta->update(['estado' => $activa]);
            $this->bitacora->registrar($activa ? 'usuario_reactivado' : 'usuario_desactivado', $actor, 'usuarios', $cuenta->id);
        });
    }

    /**
     * CU-19: genera una contraseña temporal nueva (venció u olvidada). La anterior deja de servir.
     * Exige indicar cómo se verificó la identidad del titular, y eso queda en la bitácora.
     *
     * @param  string  $verificacion  una clave de PacienteService::VERIFICACION_IDENTIDAD
     * @return array{contrasena: string, vence: \Carbon\CarbonImmutable, usuario: string}
     */
    public function renovarContrasena(Usuario $cuenta, Usuario $actor, string $verificacion): array
    {
        $formaDeVerificar = PacienteService::VERIFICACION_IDENTIDAD[$verificacion]
            ?? throw new ReglaDeNegocioException('Indique cómo verificó la identidad del titular.');

        $cuenta->loadMissing('persona');

        if ($cuenta->id === $actor->id) {
            throw new ReglaDeNegocioException('Para cambiar su propia contraseña use «Cambiar contraseña».');
        }

        if (! $cuenta->estado) {
            throw new ReglaDeNegocioException('No se puede renovar la contraseña de una cuenta desactivada.');
        }

        return DB::transaction(function () use ($cuenta, $actor, $formaDeVerificar) {
            $emitida = $this->contrasenas->emitirPara($cuenta);
            $this->bitacora->registrar(
                'acceso_renovado', $actor, 'usuarios', $cuenta->id, "Identidad verificada con: {$formaDeVerificar}"
            );

            return $emitida + ['usuario' => $cuenta->persona->correo];
        });
    }

    /** El sistema siempre conserva al menos un Administrador activo. */
    private function exigirOtroAdministradorActivo(Usuario $cuenta): void
    {
        if ($cuenta->rol->codigo !== 'ADMINISTRADOR' || ! $cuenta->estado) {
            return;
        }

        $hayOtro = Usuario::query()
            ->where('id', '!=', $cuenta->id)
            ->where('estado', true)
            ->whereHas('rol', fn ($rol) => $rol->where('codigo', 'ADMINISTRADOR'))
            ->exists();

        if (! $hayOtro) {
            throw new ReglaDeNegocioException('Debe quedar al menos un Administrador activo.');
        }
    }

    /**
     * Si otra solicitud ganó la carrera entre la validación y el guardado, la base de datos lo
     * rechaza y se informa como regla de negocio en lugar de un error técnico.
     */
    private function conReglasDeLaBase(\Closure $operacion): mixed
    {
        try {
            return $operacion();
        } catch (UniqueConstraintViolationException) {
            throw new ReglaDeNegocioException('El CI o el correo ya pertenecen a otra persona.');
        }
    }
}
