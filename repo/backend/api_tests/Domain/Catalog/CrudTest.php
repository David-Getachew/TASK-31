<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('admin can create catalog item', function () {
    $admin    = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $category = FeeCategory::factory()->create(['is_taxable' => false]);

    $response = $this->actingAs($admin)->postJson('/api/v1/admin/catalog', [
        'fee_category_id'  => $category->id,
        'sku'              => 'SKU-TEST01',
        'name'             => 'Test Item',
        'description'      => 'A test catalog item',
        'unit_price_cents' => 2500,
        'is_active'        => true,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.sku', 'SKU-TEST01');
});

test('GET /catalog returns active items to authenticated users', function () {
    $user     = User::factory()->create(['status' => AccountStatus::Active]);
    $category = FeeCategory::factory()->create(['is_taxable' => false]);

    \App\Models\CatalogItem::factory()->for($category)->create(['is_active' => true]);
    \App\Models\CatalogItem::factory()->inactive()->for($category)->create();

    $response = $this->actingAs($user)->getJson('/api/v1/catalog');

    $response->assertStatus(200);
    $items = $response->json('data.data');
    foreach ($items as $item) {
        expect($item['is_active'])->toBeTrue();
    }
});

test('non-admin cannot create catalog item', function () {
    $user     = User::factory()->create(['status' => AccountStatus::Active]);
    $category = FeeCategory::factory()->create(['is_taxable' => false]);

    $this->actingAs($user)->postJson('/api/v1/admin/catalog', [
        'fee_category_id'  => $category->id,
        'sku'              => 'SKU-UNAUTH',
        'name'             => 'Unauthorized Item',
        'unit_price_cents' => 1000,
        'is_active'        => true,
    ])->assertStatus(403);
});
