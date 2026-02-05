<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    /**
     * Os atributos que podem ser preenchidos em massa.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Os atributos ocultos na serialização.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Conversão de tipos (Casting).
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Regra de acesso ao painel do Filament.
     * Retorne 'true' para liberar geral ou coloque lógica (ex: verificar e-mail).
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return true; 
    }
}