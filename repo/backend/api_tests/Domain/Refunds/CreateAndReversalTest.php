<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\BillStatus;
use App\Enums\RefundStatus;
use App\Models\Bill;
use App\Models\Refund;
use App\Models\RefundReasonCode;
use App\Models\User;
use App\Services\RefundService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff can create a refund request', function () {
    $staff   = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $student = User::factory()->create(['status' => AccountStatus::Active]);
    $bill    = Bill::factory()->for($student)->create([
        'total_cents'    => 10000,
        'paid_cents'     => 10000,
        'refunded_cents' => 0,
        'status'         => BillStatus::Paid,
    ]);

    RefundReasonCode::create(['code' => 'duplicate', 'label' => 'Duplicate payment']);

    $response = $this->actingAs($staff)->postJson("/api/v1/bills/{$bill->id}/refunds", [
        'amount_cents' => 5000,
        'reason_code'  => 'duplicate',
    ], ['Idempotency-Key' => 'refund-key-' . uniqid()]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', RefundStatus::Pending->value);
});

test('non-operator cannot create refund request', function () {
    $student = User::factory()->create(['status' => AccountStatus::Active]);
    $bill    = Bill::factory()->for($student)->create([
        'total_cents'    => 10000,
        'paid_cents'     => 10000,
        'refunded_cents' => 0,
        'status'         => BillStatus::Paid,
    ]);

    RefundReasonCode::create(['code' => 'duplicate2', 'label' => 'Duplicate payment']);

    $this->actingAs($student)->postJson("/api/v1/bills/{$bill->id}/refunds", [
        'amount_cents' => 5000,
        'reason_code'  => 'duplicate2',
    ], ['Idempotency-Key' => 'unauth-refund-' . uniqid()])
        ->assertStatus(403);
});

test('user can only see their own refunds in list', function () {
    $owner  = User::factory()->create(['status' => AccountStatus::Active]);
    $other  = User::factory()->create(['status' => AccountStatus::Active]);
    $bill   = Bill::factory()->for($owner)->create([
        'total_cents' => 10000, 'paid_cents' => 10000,
        'refunded_cents' => 0, 'status' => BillStatus::Paid,
    ]);
    $otherBill = Bill::factory()->for($other)->create([
        'total_cents' => 5000, 'paid_cents' => 5000,
        'refunded_cents' => 0, 'status' => BillStatus::Paid,
    ]);

    RefundReasonCode::firstOrCreate(['code' => 'dup3'], ['label' => 'Dup3']);
    $service = app(RefundService::class);
    $service->request($bill, 1000, 'dup3', $owner);
    $service->request($otherBill, 500, 'dup3', $other);

    $res = $this->actingAs($other)->getJson('/api/v1/refunds');
    $res->assertOk();
    $ids = collect($res->json('data.data'))->pluck('bill_id');
    expect($ids->contains($bill->id))->toBeFalse();
    expect($ids->contains($otherBill->id))->toBeTrue();
});

test('user cannot view refund for another user bill', function () {
    $owner  = User::factory()->create(['status' => AccountStatus::Active]);
    $other  = User::factory()->create(['status' => AccountStatus::Active]);
    $bill   = Bill::factory()->for($owner)->create([
        'total_cents' => 10000, 'paid_cents' => 10000,
        'refunded_cents' => 0, 'status' => BillStatus::Paid,
    ]);
    RefundReasonCode::firstOrCreate(['code' => 'dup4'], ['label' => 'Dup4']);
    $service = app(RefundService::class);
    $refund  = $service->request($bill, 1000, 'dup4', $owner);

    $this->actingAs($other)->getJson("/api/v1/refunds/{$refund->id}")
        ->assertStatus(403);
});

test('approve refund creates reversal ledger entry and reconciliation flag', function () {
    $user  = User::factory()->create(['status' => AccountStatus::Active]);
    $bill  = Bill::factory()->for($user)->create([
        'total_cents'    => 10000,
        'paid_cents'     => 10000,
        'refunded_cents' => 0,
        'status'         => BillStatus::Paid,
    ]);

    RefundReasonCode::create(['code' => 'admin_adjustment', 'label' => 'Admin adjustment']);

    $service = app(RefundService::class);
    $refund  = $service->request($bill, 3000, 'admin_adjustment', $user);
    $service->approve($refund, $user);

    $this->assertDatabaseHas('ledger_entries', ['entry_type' => 'reversal', 'bill_id' => $bill->id]);
    $this->assertDatabaseHas('reconciliation_flags', ['source_type' => 'refund', 'status' => 'open']);
});
