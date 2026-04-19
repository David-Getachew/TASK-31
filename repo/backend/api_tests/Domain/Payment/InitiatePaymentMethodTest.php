<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\PaymentMethod;
use App\Models\CatalogItem;
use App\Models\FeeCategory;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function paymentMethodTestOrder(User $user): int
{
    $category = FeeCategory::factory()->create(['is_taxable' => false]);
    $item     = CatalogItem::factory()->for($category)->create(['unit_price_cents' => 1000, 'is_active' => true]);
    $order    = app(OrderService::class)->create($user, [['catalog_item_id' => $item->id, 'quantity' => 1]]);
    return $order->id;
}

test('POST /payments/initiate accepts each PaymentMethod enum value', function (string $method) {
    $user     = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $orderId  = paymentMethodTestOrder($user);

    $response = $this->actingAs($user)
        ->withHeaders(['Idempotency-Key' => 'init-' . $method])
        ->postJson("/api/v1/orders/{$orderId}/payment", [
            'method' => $method,
        ]);

    $response->assertStatus(201);
    expect($response->json('data.method'))->toBe($method);
})->with(array_map(fn (PaymentMethod $m) => $m->value, PaymentMethod::cases()));

test('POST /orders/{id}/payment rejects invalid payment method', function () {
    $user    = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $orderId = paymentMethodTestOrder($user);

    $this->actingAs($user)
        ->withHeaders(['Idempotency-Key' => 'init-bad'])
        ->postJson("/api/v1/orders/{$orderId}/payment", [
            'method' => 'bank_transfer',
        ])
        ->assertStatus(422);

    $this->actingAs($user)
        ->withHeaders(['Idempotency-Key' => 'init-bad-2'])
        ->postJson("/api/v1/orders/{$orderId}/payment", [
            'method' => 'card',
        ])
        ->assertStatus(422);
});
