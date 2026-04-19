<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /api/health returns 200 with ok status', function () {
    $response = $this->getJson('/api/health');

    $response->assertStatus(200)
             ->assertJsonStructure(['status', 'service', 'checks'])
             ->assertJson(['service' => 'campuslearn']);
});

test('GET /api/health returns service name campuslearn', function () {
    $response = $this->getJson('/api/health');

    $response->assertJsonPath('service', 'campuslearn');
});

test('GET /api/v1/health/circuit requires authentication', function () {
    $response = $this->getJson('/api/v1/health/circuit');

    $response->assertStatus(401);
});

test('GET /api/v1/health/metrics requires authentication', function () {
    $response = $this->getJson('/api/v1/health/metrics');

    $response->assertStatus(401);
});

test('GET /api/v1/health/metrics denies non-admin authenticated users with 403', function () {
    $student = User::factory()->asStudent()->create();

    $this->actingAs($student)
        ->getJson('/api/v1/health/metrics')
        ->assertStatus(403);
});

test('GET /api/v1/health/metrics denies authenticated teacher with 403', function () {
    $teacher = User::factory()->asTeacher()->create();

    $this->actingAs($teacher)
        ->getJson('/api/v1/health/metrics')
        ->assertStatus(403);
});

test('GET /api/v1/health/metrics allows administrators', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->getJson('/api/v1/health/metrics')
        ->assertStatus(200);
});

test('GET /api/health returns 503 when queue is unavailable', function () {
    config(['queue.default' => 'invalid_driver']);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.queue', 'error');
});
