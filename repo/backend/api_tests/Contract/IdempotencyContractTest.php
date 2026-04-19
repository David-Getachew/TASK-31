<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->authedUser = User::factory()->create();
});

test('replayed Idempotency-Key returns replay header and original body', function () {
    $key = 'replay-key-' . uniqid();
    $payload = ['k' => 'v'];

    $first = $this->actingAs($this->authedUser)->postJson('/api/v1/_contract/echo', $payload, ['Idempotency-Key' => $key]);
    $first->assertStatus(200);
    $firstBody = $first->json();

    $second = $this->actingAs($this->authedUser)->postJson('/api/v1/_contract/echo', $payload, ['Idempotency-Key' => $key]);
    $second->assertStatus(200);
    $second->assertHeader('X-Idempotent-Replay', 'true');
    expect($second->json())->toEqual($firstBody);
});

test('differing payload on reused Idempotency-Key returns 409 conflict envelope', function () {
    $key = 'conflict-key-' . uniqid();

    $this->actingAs($this->authedUser)
        ->postJson('/api/v1/_contract/echo', ['k' => 'one'], ['Idempotency-Key' => $key])
        ->assertStatus(200);

    $conflict = $this->actingAs($this->authedUser)
        ->postJson('/api/v1/_contract/echo', ['k' => 'two'], ['Idempotency-Key' => $key]);
    $conflict->assertStatus(409)
        ->assertJsonPath('error.code', 'IDEMPOTENCY_KEY_CONFLICT');
});

test('unauthenticated contract echo request is rejected', function () {
    $response = $this->postJson('/api/v1/_contract/echo', ['k' => 'v'], ['Idempotency-Key' => 'unauth-' . uniqid()]);
    $response->assertStatus(401);
});
