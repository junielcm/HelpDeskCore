<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory para crear usuarios de prueba en tests y seeds.
 *
 * Genera usuarios con datos realistas y una contraseña común y conocida
 * ("password") para poder autenticarse durante las pruebas.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * La contraseña que se está usando en la factory (se reutiliza entre
     * instancias para no recalcular el hash en cada creación).
     */
    protected static ?string $password;

    /**
     * Define el estado por defecto del modelo.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indica que el correo del modelo no debe estar verificado.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
