<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\OrderStatus;
use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('scoped (term) registrar cannot view another user bill — fails cross-scope', function () {
    $scopedRegistrar = User::factory()->asScopedRegistrar('term', 1)->create(['status' => AccountStatus::Active]);
    $student         = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $bill            = Bill::factory()->for($student)->create(['status' => BillStatus::Open]);

    $this->actingAs($scopedRegistrar)
        ->getJson('/api/v1/bills/' . $bill->id)
        ->assertStatus(403);
});

test('global registrar can view any bill', function () {
    $registrar = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $student   = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $bill      = Bill::factory()->for($student)->create(['status' => BillStatus::Open]);

    $this->actingAs($registrar)
        ->getJson('/api/v1/bills/' . $bill->id)
        ->assertStatus(200);
});

test('scoped registrar cannot view another user order', function () {
    $scopedRegistrar = User::factory()->asScopedRegistrar('term', 2)->create(['status' => AccountStatus::Active]);
    $student         = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $order           = Order::factory()->for($student)->create([
        'status' => OrderStatus::PendingPayment,
    ]);

    $this->actingAs($scopedRegistrar)
        ->getJson('/api/v1/orders/' . $order->id)
        ->assertStatus(403);
});

test('scoped teacher cannot view moderation queue', function () {
    $scopedTeacher = User::factory()->asScopedTeacher('section', 1)->create(['status' => AccountStatus::Active]);

    $this->actingAs($scopedTeacher)
        ->getJson('/api/v1/admin/moderation/queue')
        ->assertStatus(403);
});

test('scoped registrar cannot view moderation queue', function () {
    $scopedRegistrar = User::factory()->asScopedRegistrar('term', 3)->create(['status' => AccountStatus::Active]);

    $this->actingAs($scopedRegistrar)
        ->getJson('/api/v1/admin/moderation/queue')
        ->assertStatus(403);
});
