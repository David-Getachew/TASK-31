<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;

uses(RefreshDatabase::class);

test('authenticated user can logout', function () {
    $user  = User::factory()->create(['status' => AccountStatus::Active]);
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)->postJson('/api/v1/auth/logout');

    $response->assertStatus(200)
        ->assertJsonPath('data.message', 'Logged out successfully.');
});

test('logout revokes the token so it cannot be reused', function () {
    $user  = User::factory()->create(['status' => AccountStatus::Active]);
    $token = $user->createToken('test')->plainTextToken;

    $tokenId = (int) explode('|', $token)[0];

    $this->withToken($token)->postJson('/api/v1/auth/logout')->assertStatus(200);

    expect(PersonalAccessToken::query()->whereKey($tokenId)->exists())->toBeFalse();
});

test('unauthenticated logout returns 401', function () {
    $response = $this->postJson('/api/v1/auth/logout');

    $response->assertStatus(401)
        ->assertJsonPath('error.code', 'UNAUTHENTICATED');
});
