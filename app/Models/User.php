<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const PERFIL_GERENTE = 'gerente';
    public const PERFIL_ATENDENTE = 'atendente';
    public const PERFIL_PORTARIA = 'portaria';

    public const PERFIS = [
        self::PERFIL_GERENTE => 'Gerente do Cinema',
        self::PERFIL_ATENDENTE => 'Atendente de Bilheteria',
        self::PERFIL_PORTARIA => 'Operador de Portaria',
    ];

    protected $fillable = ['name', 'email', 'password', 'perfil', 'ativo'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ativo' => 'boolean',
        ];
    }

    public function temPerfil(string ...$perfis): bool
    {
        return in_array($this->perfil, $perfis, true);
    }

    public function isGerente(): bool
    {
        return $this->perfil === self::PERFIL_GERENTE;
    }

    public function getPerfilNomeAttribute(): string
    {
        return self::PERFIS[$this->perfil] ?? $this->perfil;
    }
}
