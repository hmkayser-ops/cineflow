<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        $id = Str::lower(Str::random(8));

        return [
            'name' => 'Usuário '.$id,
            'email' => "{$id}@cineflow.test",
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('cineflow123'),
            'perfil' => User::PERFIL_ATENDENTE,
            'ativo' => true,
            'remember_token' => Str::random(10),
        ];
    }

    public function gerente(): static
    {
        return $this->state(fn () => ['perfil' => User::PERFIL_GERENTE]);
    }

    public function portaria(): static
    {
        return $this->state(fn () => ['perfil' => User::PERFIL_PORTARIA]);
    }

    public function inativo(): static
    {
        return $this->state(fn () => ['ativo' => false]);
    }
}
