@extends('layouts.base')

@section('titulo', ($propia ? 'Reservar cita' : 'Registrar cita').' · Clínica Vida Salud')

@section('contenido')
    <div class="mb-3">
        <h1 class="h3 fw-semibold mb-1">{{ $propia ? 'Reservar cita' : 'Registrar cita' }}</h1>
        <p class="text-secondary mb-0">
            Siga los pasos: elija la especialidad, el médico y la fecha, y después uno de los horarios disponibles.
        </p>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger" role="alert">
            <ul class="mb-0">
                @foreach ($errors->all() as $mensaje)
                    <li>{{ $mensaje }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Sin especialidades activas no hay con qué agendar: se avisa antes de pedir cualquier dato. --}}
    @if ($especialidades->isEmpty())
        <div class="alert alert-warning" role="alert">
            Todavía no hay especialidades disponibles, así que no se puede agendar una cita.
            @if ($propia)
                Comuníquese con la clínica: <strong>{{ config('clinica.celular') }}</strong>.
            @else
                Pida al Administrador que registre al menos una especialidad.
            @endif
        </div>
    @else
        {{-- Paso 1 (solo la recepción): el paciente. --}}
        @unless ($propia)
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">1. Paciente</h2>

                    @if ($paciente)
                        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                            <div>
                                <span class="fw-semibold">{{ $paciente->persona->nombre_completo }}</span>
                                <span class="text-secondary small ms-1">{{ $paciente->codigo }} · CI {{ $paciente->persona->ci }}</span>
                                @unless ($paciente->verificado)
                                    <span class="badge text-bg-warning ms-1">Ficha pendiente de verificación</span>
                                @endunless
                            </div>
                            <a href="{{ route($rutaNueva) }}" class="btn btn-outline-secondary btn-sm">Cambiar paciente</a>
                        </div>
                    @elseif (! $hayPacientes)
                        <div class="alert alert-info mb-0">
                            Todavía no hay pacientes activos. Registre al paciente antes de agendar su cita.
                            <a href="{{ route('pacientes.create') }}" class="alert-link">Registrar paciente</a>
                        </div>
                    @else
                        <form method="GET" action="{{ route($rutaNueva) }}" class="row g-2" role="search">
                            <div class="col-12 col-md-8 col-lg-6">
                                <label for="q" class="visually-hidden">Buscar paciente</label>
                                <input type="search" id="q" name="q" value="{{ $textoBusqueda }}" class="form-control"
                                       placeholder="Buscar por CI, nombre, apellido o código (PAC-000042)">
                            </div>
                            <div class="col-auto">
                                <button type="submit" class="btn btn-outline-secondary">Buscar</button>
                            </div>
                        </form>

                        @if ($textoBusqueda !== '')
                            @if ($candidatos->isEmpty())
                                <div class="alert alert-info mt-3 mb-0">
                                    No hay pacientes activos que coincidan con «{{ $textoBusqueda }}».
                                    Puede <a href="{{ route('pacientes.create') }}">registrar al paciente</a>.
                                </div>
                            @else
                                <div class="table-responsive mt-3">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead class="table-light">
                                            <tr><th>Código</th><th>CI</th><th>Nombre</th><th><span class="visually-hidden">Acciones</span></th></tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($candidatos as $candidato)
                                                <tr>
                                                    <td class="text-nowrap">{{ $candidato->codigo }}</td>
                                                    <td class="text-nowrap">{{ $candidato->persona->ci }}</td>
                                                    <td>{{ $candidato->persona->nombre_completo }}</td>
                                                    <td class="text-end">
                                                        <a href="{{ route($rutaNueva, ['paciente' => $candidato->id]) }}" class="btn btn-clinica btn-sm">Elegir</a>
                                                    </td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @endif
                        @endif
                    @endif
                </div>
            </div>
        @endunless

        {{-- Paso 2: especialidad, médico y fecha. Cada selección recarga la página con GET. --}}
        @if ($paciente)
            <div class="card shadow-sm mb-3">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">{{ $propia ? '1' : '2' }}. Especialidad, médico y fecha</h2>

                    <form method="GET" action="{{ route($rutaNueva) }}" class="row g-3" data-autoenvio>
                        @unless ($propia)
                            <input type="hidden" name="paciente" value="{{ $paciente->id }}">
                        @endunless

                        <div class="col-md-4">
                            <label for="especialidad" class="form-label">Especialidad</label>
                            <select id="especialidad" name="especialidad" class="form-select" data-autoenvio-campo>
                                <option value="">Elija la especialidad</option>
                                @foreach ($especialidades as $opcion)
                                    <option value="{{ $opcion->id }}" @selected($especialidad?->id === $opcion->id)>
                                        {{ $opcion->nombre }} ({{ $opcion->duracion_min }} min)
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        @if ($especialidad)
                            <div class="col-md-4">
                                <label for="medico" class="form-label">Médico</label>
                                <select id="medico" name="medico" class="form-select" data-autoenvio-campo>
                                    <option value="">Elija al médico</option>
                                    @foreach ($medicos as $opcion)
                                        <option value="{{ $opcion->id }}" @selected($medico?->id === $opcion->id)>
                                            {{ $opcion->persona->nombre_completo }}
                                        </option>
                                    @endforeach
                                </select>
                                @if ($medicos->isEmpty())
                                    <div class="form-text text-danger">No hay médicos activos con esta especialidad.</div>
                                @endif
                            </div>
                        @endif

                        @if ($medico && $medicoSinHorarios)
                            <div class="col-12">
                                <div class="alert alert-warning mb-0">
                                    {{ $medico->persona->nombre_completo }} no tiene horarios de atención registrados, así que no se
                                    puede agendar con este médico.
                                    Pida al Administrador que los registre o elija otro médico.
                                </div>
                            </div>
                        @elseif ($medico)
                            <div class="col-md-4">
                                <label for="fecha" class="form-label">Fecha</label>
                                <input type="date" id="fecha" name="fecha" value="{{ $fecha }}" class="form-control"
                                       min="{{ $primeraFecha }}" max="{{ $ultimaFecha }}" data-autoenvio-campo>
                                <div class="form-text">
                                    Hasta el {{ \Carbon\Carbon::parse($ultimaFecha)->format('d/m/Y') }}.
                                </div>
                            </div>
                        @endif

                        @unless ($medico && $medicoSinHorarios)
                            <div class="col-12">
                                <button type="submit" class="btn btn-clinica">
                                    {{ $medico ? 'Ver horarios disponibles' : 'Continuar' }}
                                </button>
                            </div>
                        @endunless
                    </form>
                </div>
            </div>
        @endif

        {{-- Paso 3: horario, modalidad y motivo; se guarda con POST. --}}
        @if ($paciente && $medico && $fecha !== '')
            <div class="card shadow-sm">
                <div class="card-body">
                    <h2 class="h6 text-uppercase text-secondary">{{ $propia ? '2' : '3' }}. Horario</h2>

                    @if ($aviso)
                        <div class="alert alert-warning mb-0">{{ $aviso }}</div>
                    @elseif (count($horarios) === 0)
                        <div class="alert alert-info mb-0">
                            {{ $medico->persona->nombre_completo }} no tiene horarios disponibles el
                            {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}. Pruebe con otra fecha.
                        </div>
                    @else
                        <form method="POST" action="{{ route($rutaGuardar) }}" class="needs-validation" novalidate>
                            @csrf
                            @unless ($propia)
                                <input type="hidden" name="paciente_id" value="{{ $paciente->id }}">
                            @endunless
                            <input type="hidden" name="especialidad_id" value="{{ $especialidad->id }}">
                            <input type="hidden" name="medico_id" value="{{ $medico->id }}">
                            <input type="hidden" name="fecha" value="{{ $fecha }}">

                            <fieldset class="mb-3">
                                <legend class="form-label fs-6">
                                    Horarios disponibles el {{ \Carbon\Carbon::parse($fecha)->format('d/m/Y') }}
                                    <span class="text-danger" aria-hidden="true">*</span>
                                </legend>
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach ($horarios as $horario)
                                        <div>
                                            <input type="radio" class="btn-check" name="hora_inicio" id="hora_{{ $loop->index }}"
                                                   value="{{ $horario['hora_inicio'] }}" required
                                                   @checked(old('hora_inicio') === $horario['hora_inicio'])>
                                            <label class="btn btn-outline-primary" for="hora_{{ $loop->index }}">
                                                {{ $horario['hora_inicio'] }} – {{ $horario['hora_fin'] }}
                                            </label>
                                        </div>
                                    @endforeach
                                </div>
                            </fieldset>

                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label for="modalidad" class="form-label">Modalidad</label>
                                    <select id="modalidad" name="modalidad" class="form-select">
                                        @foreach (['PRESENCIAL' => 'Presencial', 'VIRTUAL' => 'Virtual'] as $valor => $etiqueta)
                                            <option value="{{ $valor }}" @selected(old('modalidad', 'PRESENCIAL') === $valor)>{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <div class="col-md-8">
                                    <label for="motivo" class="form-label">Motivo de la consulta</label>
                                    <textarea id="motivo" name="motivo" rows="2" maxlength="500"
                                              class="form-control @error('motivo') is-invalid @enderror"
                                              placeholder="Opcional. Una breve descripción.">{{ old('motivo') }}</textarea>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-clinica mt-3">{{ $propia ? 'Reservar cita' : 'Registrar cita' }}</button>
                        </form>
                    @endif
                </div>
            </div>
        @endif
    @endif
@endsection

@push('scripts')
    <script src="{{ asset('js/citas.js') }}"></script>
@endpush
