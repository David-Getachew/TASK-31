<?php

use App\Models\User;
use App\Models\Bill;
use App\Models\Refund;
use App\Models\RefundReasonCode;

// Refund approval and reconciliation flag resolution flows.

it('admin can approve a pending refund', function () {
    $admin       = User::factory()->asAdmin()->create();
    $bill        = Bill::factory()->paid()->create();
    $reasonCode  = RefundReasonCode::factory()->create();
    $refund      = Refund::factory()->for($bill)->pending()->create([
        'reason_code_id' => $reasonCode->id,
        'amount_cents'   => 500,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/refunds/{$refund->id}/approve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');
});

it('admin can reject a pending refund', function () {
    $admin      = User::factory()->asAdmin()->create();
    $bill       = Bill::factory()->paid()->create();
    $reasonCode = RefundReasonCode::factory()->create();
    $refund     = Refund::factory()->for($bill)->pending()->create([
        'reason_code_id' => $reasonCode->id,
        'amount_cents'   => 200,
    ]);

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/refunds/{$refund->id}/reject", ['reason' => 'Ineligible'])
        ->assertOk()
        ->assertJsonPath('data.status', 'rejected');
});

it('student cannot approve a refund', function () {
    $student    = User::factory()->asStudent()->create();
    $bill       = Bill::factory()->paid()->create();
    $reasonCode = RefundReasonCode::factory()->create();
    $refund     = Refund::factory()->for($bill)->pending()->create([
        'reason_code_id' => $reasonCode->id,
        'amount_cents'   => 100,
    ]);

    $this->actingAs($student)
        ->postJson("/api/v1/admin/refunds/{$refund->id}/approve", [])
        ->assertForbidden();
});

it('reconciliation flag can be resolved by admin', function () {
    $admin = User::factory()->asAdmin()->create();
    $flag  = \App\Models\ReconciliationFlag::factory()->open()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/reconciliation-flags/{$flag->id}/resolve", [])
        ->assertOk()
        ->assertJsonPath('data.status', 'resolved');

    expect($flag->fresh()->status->value)->toBe('resolved');
});

it('resolved flag cannot be resolved again', function () {
    $admin = User::factory()->asAdmin()->create();
    $flag  = \App\Models\ReconciliationFlag::factory()->resolved()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/reconciliation-flags/{$flag->id}/resolve", [])
        ->assertStatus(422);
});
