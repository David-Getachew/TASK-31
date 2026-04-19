<?php

namespace Database\Factories;

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\Role;
use App\Models\RoleAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name'                => fake()->name(),
            'email'               => fake()->unique()->safeEmail(),
            'password'            => Hash::make('password1234'),
            'locale'              => 'en',
            'status'              => AccountStatus::Active,
            'last_login_at'       => null,
            'password_changed_at' => null,
        ];
    }

    public function locked(): static
    {
        return $this->state(['status' => AccountStatus::Locked]);
    }

    public function disabled(): static
    {
        return $this->state(['status' => AccountStatus::Disabled]);
    }

    public function asAdmin(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::query()->firstOrCreate(
                ['name' => RoleName::Administrator->value],
                ['label' => 'Administrator'],
            );

            RoleAssignment::query()->create([
                'user_id'    => $user->id,
                'role_id'    => $role->id,
                'scope_type' => 'global',
                'scope_id'   => null,
                'granted_at' => now(),
            ]);
        });
    }

    public function asStudent(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::query()->firstOrCreate(
                ['name' => RoleName::Student->value],
                ['label' => 'Student'],
            );

            RoleAssignment::query()->create([
                'user_id'    => $user->id,
                'role_id'    => $role->id,
                'scope_type' => 'global',
                'scope_id'   => null,
                'granted_at' => now(),
            ]);
        });
    }

    public function asTeacher(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::query()->firstOrCreate(
                ['name' => RoleName::Teacher->value],
                ['label' => 'Teacher'],
            );

            RoleAssignment::query()->create([
                'user_id'    => $user->id,
                'role_id'    => $role->id,
                'scope_type' => 'global',
                'scope_id'   => null,
                'granted_at' => now(),
            ]);
        });
    }

    public function asRegistrar(): static
    {
        return $this->afterCreating(function (User $user): void {
            $role = Role::query()->firstOrCreate(
                ['name' => RoleName::Registrar->value],
                ['label' => 'Registrar'],
            );

            RoleAssignment::query()->create([
                'user_id'    => $user->id,
                'role_id'    => $role->id,
                'scope_type' => 'global',
                'scope_id'   => null,
                'granted_at' => now(),
            ]);
        });
    }
}
