<?php

namespace Database\Factories;

use App\Enums\ReconciliationSourceType;
use App\Enums\ReconciliationStatus;
use App\Models\ReconciliationFlag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReconciliationFlag>
 */
class ReconciliationFlagFactory extends Factory
{
    protected $model = ReconciliationFlag::class;

    public function definition(): array
    {
        return [
            'source_type' => ReconciliationSourceType::Refund,
            'source_id'   => fake()->numberBetween(1, 100000),
            'status'      => ReconciliationStatus::Open,
            'opened_at'   => now(),
            'resolved_by' => null,
            'resolved_at' => null,
            'notes'       => null,
        ];
    }

    public function open(): static
    {
        return $this->state([
            'status'      => ReconciliationStatus::Open,
            'resolved_by' => null,
            'resolved_at' => null,
        ]);
    }

    public function resolved(): static
    {
        return $this->state([
            'status'      => ReconciliationStatus::Resolved,
            'resolved_by' => User::factory(),
            'resolved_at' => now(),
            'notes'       => 'Resolved during review',
        ]);
    }
}