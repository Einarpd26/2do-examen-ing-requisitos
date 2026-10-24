<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Nota clínica de una cita atendida (Ciclo #2). La escribe el médico de la cita; al
 * editarla se sobrescribe y se guarda quién y cuándo la modificó por última vez.
 */
class NotaClinica extends Model
{
    protected $table = 'notas_clinicas';

    protected $fillable = [
        'cita_id',
        'medico_id',
        'motivo_consulta',
        'diagnostico',
        'tratamiento',
        'observaciones',
        'modificado_por',
    ];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class);
    }

    public function medico(): BelongsTo
    {
        return $this->belongsTo(Medico::class);
    }

    public function modificador(): BelongsTo
    {
        return $this->belongsTo(Usuario::class, 'modificado_por');
    }
}
