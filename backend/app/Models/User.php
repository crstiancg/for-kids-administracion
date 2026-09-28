<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

#[Fillable(['name', 'username', 'email', 'password', 'active'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements OAuthenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /**
     * Normaliza el username al guardarlo, así la búsqueda del login puede
     * comparar en minúsculas sin depender del collation de la base.
     */
    protected function setUsernameAttribute(string $value): void
    {
        $this->attributes['username'] = mb_strtolower(trim($value));
    }

    protected function setEmailAttribute(?string $value): void
    {
        $this->attributes['email'] = $value === null ? null : mb_strtolower(trim($value));
    }

    /**
     * El password grant de Passport busca sólo por email por defecto; acá el
     * campo "Usuario" del login acepta username o email. Un usuario inactivo se
     * trata igual que uno inexistente para no revelar qué cuentas existen.
     */
    public function findForPassport(string $username): ?self
    {
        $login = mb_strtolower(trim($username));

        return $this->where(fn ($query) => $query->where('username', $login)->orWhere('email', $login))
            ->where('active', true)
            ->first();
    }
}
