<?php

use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// Moderation queue — admin-only endpoint

it('admin can retrieve moderation queue', function () {
    $admin  = User::factory()->asAdmin()->create();
    Thread::factory()->count(3)->create(['state' => 'visible']);

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/moderation/queue');

    $response->assertOk()
        ->assertJsonStructure(['data' => ['data']]);
});

it('non-admin cannot access moderation queue', function () {
    $student = User::factory()->asStudent()->create();

    $this->actingAs($student)->getJson('/api/v1/admin/moderation/queue')
        ->assertForbidden();
});

it('admin can hide a visible thread', function () {
    $admin  = User::factory()->asAdmin()->create();
    $thread = Thread::factory()->create(['state' => 'visible']);

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/admin/threads/{$thread->id}/hide", ['reason' => 'Policy violation']);

    $response->assertOk()
        ->assertJsonPath('data.state', 'hidden');

    expect($thread->fresh()->state->value)->toBe('hidden');
});

it('admin can restore a hidden thread', function () {
    $admin  = User::factory()->asAdmin()->create();
    $thread = Thread::factory()->create(['state' => 'hidden']);

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/admin/threads/{$thread->id}/restore", []);

    $response->assertOk()
        ->assertJsonPath('data.state', 'visible');
});

it('admin can lock a thread', function () {
    $admin  = User::factory()->asAdmin()->create();
    $thread = Thread::factory()->create(['state' => 'visible']);

    $response = $this->actingAs($admin)
        ->postJson("/api/v1/admin/threads/{$thread->id}/lock", ['reason' => 'Off-topic']);

    $response->assertOk()
        ->assertJsonPath('data.state', 'locked');
});

it('cannot transition locked thread to hidden directly', function () {
    $admin  = User::factory()->asAdmin()->create();
    $thread = Thread::factory()->create(['state' => 'locked']);

    // Cannot hide a locked thread (invalid transition)
    $response = $this->actingAs($admin)
        ->postJson("/api/v1/admin/threads/{$thread->id}/hide", ['reason' => 'Test']);

    $response->assertStatus(422)
        ->assertJsonPath('error.code', 'INVALID_STATE_TRANSITION');
});
