<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Bill;
use App\Models\Refund;
use App\Models\RefundReasonCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        return [
            'bill_id'                  => Bill::factory(),
            'amount_cents'             => fake()->numberBetween(100, 10000),
            'reason_code_id'           => RefundReasonCode::factory(),
            'operator_user_id'         => User::factory(),
            'status'                   => RefundStatus::Pending,
            'idempotency_key_id'       => null,
            'reversal_ledger_entry_id' => null,
            'notes'                    => null,
            'approved_at'              => null,
            'completed_at'             => null,
            'created_at'               => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state([
            'status'      => RefundStatus::Pending,
            'approved_at' => null,
            'completed_at'=> null,
        ]);
    }

    public function approved(): static
    {
        return $this->state([
            'status'      => RefundStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state([
            'status' => RefundStatus::Rejected,
            'notes'  => 'Rejected by operator',
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status'       => RefundStatus::Completed,
            'approved_at'  => now()->subMinute(),
            'completed_at' => now(),
        ]);
    }
}