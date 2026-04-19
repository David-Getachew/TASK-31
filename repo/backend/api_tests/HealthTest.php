<?php

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

test('GET /api/health returns 503 when queue is unavailable', function () {
    config(['queue.default' => 'invalid_driver']);

    $this->getJson('/api/health')
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.queue', 'error');
});
