@extends('layouts.base')

@section('titulo', 'Paciente '.$paciente->codigo.' · Clínica Vida Salud')

@php
    $persona = $paciente->persona;
    $cuenta = $persona->usuario;
    $cuentaDePaciente = $cuenta?->rol->codigo === 'PACIENTE';
    $dato = fn ($valor) => filled($valor) ? $valor : '—';
@endphp

@section('contenido')
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h1 class="h3 fw-semibold mb-0">
            {{ $persona->nombre_completo }}
            <small class="text-secondary">{{ $paciente->codigo }}</small>
        </h1>
        <div class="d-flex flex-wrap gap-2">
            <a href="{{ route('pacientes.index') }}" class="btn btn-outline-secondary btn-sm">Volver al listado</a>
            <a href="{{ route('pacientes.edit', $paciente) }}" class="btn btn-clinica btn-sm">Editar</a>
            <form method="POST" action="{{ route('pacientes.estado', $paciente) }}"
                  onsubmit="return confirm('{{ $paciente->estado ? 'Se desactivará al paciente y su cuenta. El historial se conserva.' : 'Se reactivará al paciente y su cuenta.' }}')">
                @csrf
                @method('PATCH')
                <input type="hidden" name="activo" value="{{ $paciente->estado ? 0 : 1 }}">
                <button type="submit" class="btn btn-sm {{ $paciente->estado ? 'btn-outline-danger' : 'btn-outline-success' }}">
                    {{ $paciente->estado ? 'Desactivar' : 'Reactivar' }}
                </button>
            </form>
        </div>
    </div>

    <div class="mb-3">
        @if ($paciente->estado)
            <span class="badge text-bg-primary">Activo</span>
        @else
            <span class="badge text-bg-secondary">Inactivo</span>
        @endif
        @if ($paciente->verificado)
            <span class="badge text-bg-success">Ficha verificada</span>
        @else
            <span class="badge text-bg-warning">Ficha pendiente de verificación</span>
        @endif
    </div>

    @unless ($paciente->verificado)
        @can('pacientes:verificar')
            <div class="alert alert-warning">
                <p class="mb-2">
                    Esta ficha vino del registro propio del paciente. Compare su <strong>documento de identidad</strong>
                    con estos datos antes de verificarla.
                </p>
                <form method="POST" action="{{ route('pacientes.verificar', $paciente) }}" class="d-flex flex-wrap align-items-center gap-3">
                    @csrf
                    @if ($cuentaDePaciente && $paciente->estado)
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="renovar_acceso" value="1" id="renovar_acceso">
                            <label class="form-check-label" for="renovar_acceso">
                                Renovar también la contraseña (recomendado si hay dudas sobre quién creó la cuenta)
                            </label>
                        </div>
                    @endif
                    <button type="submit" class="btn btn-warning btn-sm">Verificar ficha</button>
                </form>
            </div>
        @endcan
    @endunless

    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">Datos personales</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">CI</dt><dd class="col-7">{{ $persona->ci }}</dd>
                        <dt class="col-5">Fecha de nacimiento</dt><dd class="col-7">{{ $dato($persona->fecha_nacimiento?->format('d/m/Y')) }}</dd>
                        <dt class="col-5">Sexo</dt><dd class="col-7">{{ ['M' => 'Masculino', 'F' => 'Femenino'][$persona->sexo] ?? '—' }}</dd>
                        <dt class="col-5">Teléfono</dt><dd class="col-7">{{ $dato($persona->telefono) }}</dd>
                        <dt class="col-5">Correo</dt><dd class="col-7">{{ $dato($persona->correo) }}</dd>
                        <dt class="col-5">Dirección</dt><dd class="col-7">{{ $dato($persona->direccion) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">Información médica</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">Tipo de sangre</dt><dd class="col-7">{{ $dato($paciente->tipo_sangre) }}</dd>
                        <dt class="col-5">Alergias</dt><dd class="col-7">{{ $dato($paciente->alergias) }}</dd>
                        <dt class="col-5">Enfermedades crónicas</dt><dd class="col-7">{{ $dato($paciente->enfermedades_cronicas) }}</dd>
                        <dt class="col-5">Medicamentos</dt><dd class="col-7">{{ $dato($paciente->medicamentos) }}</dd>
                        <dt class="col-5">Antecedentes</dt><dd class="col-7">{{ $dato($paciente->antecedentes) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">Contacto de emergencia</h2>
                    <dl class="row mb-0">
                        <dt class="col-5">Nombre</dt><dd class="col-7">{{ $dato($paciente->emergencia_nombre) }}</dd>
                        <dt class="col-5">Parentesco</dt><dd class="col-7">{{ $dato($paciente->emergencia_parentesco) }}</dd>
                        <dt class="col-5">Teléfono</dt><dd class="col-7">{{ $dato($paciente->emergencia_telefono) }}</dd>
                    </dl>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">Acceso al sistema</h2>

                    @if ($cuenta === null)
                        <p class="mb-3">El paciente todavía no tiene cuenta. Entraría con su CI.</p>
                        @can('pacientes:crear_acceso')
                            @if ($paciente->estado)
                                <form method="POST" action="{{ route('pacientes.acceso.crear', $paciente) }}">
                                    @csrf
                                    <button type="submit" class="btn btn-clinica btn-sm">Crear acceso</button>
                                </form>
                            @else
                                <p class="text-secondary small mb-0">Reactive al paciente para crear su acceso.</p>
                            @endif
                        @endcan
                    @elseif ($cuentaDePaciente)
                        <p class="mb-1">Entra con su CI: <strong>{{ $persona->ci }}</strong></p>
                        <p class="mb-3">Estado: <strong>{{ $cuenta->estadoAcceso() }}</strong></p>
                        @can('accesos:renovar')
                            @if ($paciente->estado)
                                <form method="POST" action="{{ route('pacientes.acceso.renovar', $paciente) }}"
                                      onsubmit="return confirm('Se generará una contraseña temporal nueva y la anterior dejará de servir.')">
                                    @csrf
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">Renovar contraseña temporal</button>
                                </form>
                            @endif
                        @endcan
                    @else
                        <p class="mb-0">
                            Esta persona ya tiene una cuenta con rol <strong>{{ $cuenta->rol->nombre }}</strong>.
                            Como paciente la atiende la recepción.
                        </p>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection
