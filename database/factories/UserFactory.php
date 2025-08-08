<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'agent_id' => fake()->regexify('[A-Za-z0-9]{5,10}_agent'),
            'status' => true,
            'role_id' => 2, // Default to regular user role
            'last_login_at' => fake()->optional(0.7)->dateTimeBetween('-30 days', 'now'),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn(array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn(array $attributes) => [
            'status' => false,
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_id' => 1,
            'status' => true,
        ]);
    }

    /**
     * Indicate that the user is a moderator.
     */
    public function moderator(): static
    {
        return $this->state(fn(array $attributes) => [
            'role_id' => 3,
            'status' => true,
        ]);
    }

    /**
     * Indicate that the user has never logged in.
     */
    public function neverLoggedIn(): static
    {
        return $this->state(fn(array $attributes) => [
            'last_login_at' => null,
        ]);
    }
}
