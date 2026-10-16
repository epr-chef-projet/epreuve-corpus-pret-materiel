<?php

declare(strict_types=1);

namespace App\Modules\IdentityAccess\Database\Factories;

use App\Modules\IdentityAccess\Accounts\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid7(),
            'workshop_id' => (string) Str::uuid7(),
            'login' => fake()->unique()->userName(),
            'display_name' => fake()->name(),
            'password' => Hash::make(Str::random(64)),
            'role' => 'borrower',
        ];
    }
}
