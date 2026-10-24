<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ListadoConPestanas;
use App\Http\Requests\RenovarAccesoRequest;
use App\Http\Requests\UsuarioRequest;
use App\Models\Medico;
use App\Models\Rol;
use App\Models\Usuario;
use App\Services\UsuarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * CU-03: gestionar usuarios y roles del personal. Las rutas exigen el permiso usuarios:gestionar
 * y solo alcanzan cuentas del personal (las de pacientes se gestionan en CU-02 y CU-04).
 */
class UsuarioController extends Controller
{
    use ListadoConPestanas;

    protected function etiquetasDePestanas(): array
    {
        return ['activas' => 'Activos', 'inactivas' => 'Inactivos', 'todas' => 'Todos'];
    }

    public function index(Request $request): View
    {
        $texto = trim((string) $request->query('q'));
        $vista = $this->vistaDelListado($request, $texto);

        $busqueda = Usuario::query()
            ->join('personas', 'personas.id', '=', 'usuarios.persona_id')
            ->join('roles', 'roles.id', '=', 'usuarios.rol_id')
            ->where('roles.codigo', '!=', 'PACIENTE')
            ->select('usuarios.*')
            ->with('persona', 'rol')
            ->when($texto !== '', function ($consulta) use ($texto) {
                // Busca por CI, nombre o apellido, correo o rol.
                $patron = addcslashes($texto, '%_\\');

                $consulta->where(function ($coincidencia) use ($patron) {
                    $coincidencia
                        ->where('personas.ci', 'ilike', $patron.'%')
                        ->orWhere('personas.nombre_completo', 'ilike', '%'.$patron.'%')
                        ->orWhere('personas.correo', 'ilike', '%'.$patron.'%')
                        ->orWhere('roles.nombre', 'ilike', $patron.'%');
                });
            });

        $activos = (clone $busqueda)->where('usuarios.estado', true)->count();
        $inactivos = (clone $busqueda)->where('usuarios.estado', false)->count();
        $cuentas = ['activas' => $activos, 'inactivas' => $inactivos, 'todas' => $activos + $inactivos];

        $usuarios = $busqueda
            ->when($vista === 'activas', fn ($consulta) => $consulta->where('usuarios.estado', true))
            ->when($vista === 'inactivas', fn ($consulta) => $consulta->where('usuarios.estado', false))
            ->orderBy('personas.nombre_completo')
            ->paginate(15)
            ->withQueryString();

        return view('usuarios.index', [
            'usuarios' => $usuarios,
            'texto' => $texto,
            'vista' => $vista,
            'pestanas' => $this->pestanas('usuarios.index', $cuentas, $texto),
        ]);
    }

    public function create(): View
    {
        return view('usuarios.create', [
            'roles' => $this->rolesDePersonal(),
            'medicos' => $this->medicosSinCuenta(),
        ]);
    }

    public function store(UsuarioRequest $request, UsuarioService $usuarios): RedirectResponse
    {
        [$cuenta, $emitida] = $usuarios->crear($request->validated(), $request->user());

        return $this->mostrarCredenciales($cuenta, $emitida);
    }

    public function edit(Usuario $usuario): View
    {
        $usuario->load('persona', 'rol');

        return view('usuarios.edit', [
            'usuario' => $usuario,
            'roles' => $this->rolesDePersonal()->whereIn('codigo', UsuarioService::ROLES_INTERCAMBIABLES),
        ]);
    }

    public function update(UsuarioRequest $request, Usuario $usuario, UsuarioService $usuarios): RedirectResponse
    {
        $usuarios->actualizar($usuario, $request->validated(), $request->user());

        return redirect()->route('usuarios.index')->with('exito', 'Usuario actualizado.');
    }

    /**
     * Desactiva o reactiva la cuenta; nunca se elimina. Vuelve a la pestaña del listado desde la
     * que se hizo el cambio, con la misma búsqueda.
     */
    public function estado(Request $request, Usuario $usuario, UsuarioService $usuarios): RedirectResponse
    {
        $activa = $request->boolean('activo');
        $usuarios->cambiarEstado($usuario, $activa, $request->user());

        $vista = $this->vista($request->input('vista'));
        $texto = trim((string) $request->input('q'));

        return redirect()
            ->route('usuarios.index', $this->parametrosDelListado($vista, $texto))
            ->with('exito', $activa
                ? 'Cuenta reactivada. Ya aparece en la pestaña Activos.'
                : 'Cuenta desactivada: ya no puede iniciar sesión. Puede verla en la pestaña Inactivos.');
    }

    /** CU-19: renovar la contraseña temporal, indicando cómo se verificó la identidad del titular. */
    public function renovar(RenovarAccesoRequest $request, Usuario $usuario, UsuarioService $usuarios): RedirectResponse
    {
        return $this->mostrarCredenciales(
            $usuario,
            $usuarios->renovarContrasena($usuario, $request->user(), $request->validated('verificacion'))
        );
    }

    /** La contraseña temporal se muestra una sola vez: viaja en un mensaje de sesión que se pierde al recargar. */
    public function credenciales(Usuario $usuario): View|RedirectResponse
    {
        $credenciales = session('credenciales');

        if (! is_array($credenciales) || ($credenciales['cuenta'] ?? null) !== $usuario->id) {
            return redirect()->route('usuarios.edit', $usuario)->with(
                'error',
                'La contraseña temporal se muestra una sola vez. Si se perdió, renueve la contraseña.'
            );
        }

        return view('usuarios.credenciales', ['usuario' => $usuario->load('persona', 'rol'), 'credenciales' => $credenciales]);
    }

    /** @param  array{contrasena: string, vence: \Carbon\CarbonImmutable, usuario: string}  $emitida */
    private function mostrarCredenciales(Usuario $cuenta, array $emitida): RedirectResponse
    {
        return redirect()->route('usuarios.credenciales', $cuenta)->with('credenciales', [
            'cuenta' => $cuenta->id,
            'usuario' => $emitida['usuario'],
            'contrasena' => $emitida['contrasena'],
            'vence' => $emitida['vence']->toIso8601String(),
        ]);
    }

    /** Roles que se pueden dar al personal. */
    private function rolesDePersonal()
    {
        return Rol::whereIn('codigo', UsuarioService::ROLES_DE_PERSONAL)->orderBy('nombre')->get();
    }

    /** Médicos activos que todavía no tienen cuenta. */
    private function medicosSinCuenta()
    {
        return Medico::query()
            ->join('personas', 'personas.id', '=', 'medicos.persona_id')
            ->select('medicos.*')
            ->where('medicos.estado', true)
            ->whereDoesntHave('persona.usuario')
            ->with('persona')
            ->orderBy('personas.nombre_completo')
            ->get();
    }
}
