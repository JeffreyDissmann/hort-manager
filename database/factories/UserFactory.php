<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccountingAccess;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
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
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /** An Erzieher:in (staff). */
    public function staff(): static
    {
        return $this->state(['role' => UserRole::Staff]);
    }

    /** An Elternteil (parent) — the factory default, made explicit for readability. */
    public function parent(): static
    {
        return $this->state(['role' => UserRole::Parent]);
    }

    /** Signed in via Slack — the only users who can receive a Slack DM. */
    public function slackLinked(): static
    {
        return $this->state(fn (): array => ['slack_id' => 'U'.Str::upper(Str::random(9))]);
    }

    /** Can manage users + switch their own role (independent of role). */
    public function admin(): static
    {
        return $this->state(['is_admin' => true]);
    }

    /** Read-only access to the Buchhaltung module. */
    public function accountingReader(): static
    {
        return $this->state(['accounting_access' => AccountingAccess::Read]);
    }

    /** Read + write access to the Buchhaltung module. */
    public function accountingWriter(): static
    {
        return $this->state(['accounting_access' => AccountingAccess::Write]);
    }
}
