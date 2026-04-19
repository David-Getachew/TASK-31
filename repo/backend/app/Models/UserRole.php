<?php

namespace App\Models;

use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Model;

/**
 * Convenience shim for tests: accepts `role` (RoleName enum or string) and
 * translates it to a `role_id` FK via the `roles` table, then inserts into
 * `role_assignments`. Scope type defaults to 'global' when null.
 */
class UserRole extends Model
{
    protected $table = 'role_assignments';

    protected $fillable = [
        'user_id',
        'role_id',
        'scope_type',
        'scope_id',
        'granted_by',
        'granted_at',
    ];

    public static function create(array $attributes = []): static
    {
        $roleValue = $attributes['role'] ?? null;
        $roleId = $attributes['role_id'] ?? null;

        if ($roleId === null) {
            if ($roleValue instanceof RoleName) {
                $roleValue = $roleValue->value;
            }
            if (! is_string($roleValue) || $roleValue === '') {
                throw new \InvalidArgumentException('UserRole::create requires role or role_id.');
            }

            $role = Role::firstOrCreate(
                ['name' => $roleValue],
                ['label' => ucfirst((string) $roleValue)],
            );

            $roleId = $role->id;
        }

        $model = new static();
        $model->fill([
            'user_id'    => $attributes['user_id'],
            'role_id'    => $roleId,
            'scope_type' => $attributes['scope_type'] ?? 'global',
            'scope_id'   => $attributes['scope_id'] ?? null,
            'granted_by' => null,
            'granted_at' => now(),
        ]);
        $model->save();

        return $model;
    }
}
