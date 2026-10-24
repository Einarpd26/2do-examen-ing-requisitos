<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Datos comunes de toda persona (paciente, médico o personal). El CI es único.
 * De ella cuelgan la cuenta (Usuario) y, según el caso, la ficha de paciente o de médico.
 */
class Persona extends Model
{
    protected $table = 'personas';

    // nombre_completo lo calcula la base de datos a partir de las partes y no se asigna.
    protected $fillable = [
        'ci',
        'primer_nombre',
        'segundo_nombre',
        'apellido_paterno',
        'apellido_materno',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'correo',
        'direccion',
    ];

    protected function casts(): array
    {
        return ['fecha_nacimiento' => 'date'];
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(Usuario::class);
    }

    public function paciente(): HasOne
    {
        return $this->hasOne(Paciente::class);
    }

    public function medico(): HasOne
    {
        return $this->hasOne(Medico::class);
    }
}
