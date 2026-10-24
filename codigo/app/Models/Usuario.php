<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Cuenta de acceso de una persona. Tiene un solo rol y los permisos salen de ese rol.
 */
class Usuario extends Authenticatable
{
    protected $table = 'usuarios';

    protected $fillable = [
        'persona_id',
        'password_hash',
        'rol_id',
        'estado',
        'intentos_fallidos',
        'bloqueado_hasta',
        'debe_cambiar_password',
        'password_temporal_vence',
    ];

    protected $hidden = ['password_hash'];

    protected function casts(): array
    {
        return [
            // Al asignar una contraseña en texto plano se guarda su hash.
            'password_hash' => 'hashed',
            'estado' => 'boolean',
            'bloqueado_hasta' => 'datetime',
            'debe_cambiar_password' => 'boolean',
            'password_temporal_vence' => 'datetime',
        ];
    }

    // La contraseña vive en la columna password_hash.
    public function getAuthPassword(): string
    {
        return $this->password_hash;
    }

    // No se usa "recordarme": la tabla no tiene columna remember_token.
    public function getRememberTokenName(): string
    {
        return '';
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(Persona::class);
    }

    public function rol(): BelongsTo
    {
        return $this->belongsTo(Rol::class);
    }

    public function bitacora(): HasMany
    {
        return $this->hasMany(Bitacora::class);
    }

    // ¿El rol de la cuenta concede este permiso (formato recurso:accion)?
    public function tienePermiso(string $codigo): bool
    {
        return $this->rol->permisos->contains('codigo', $codigo);
    }
}
