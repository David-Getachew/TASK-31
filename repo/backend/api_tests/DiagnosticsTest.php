<?php

declare(strict_types=1);

use App\Enums\DiagnosticExportStatus;
use App\Models\DiagnosticExport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// POST /api/v1/admin/diagnostics/export
test('admin can trigger a diagnostic export', function () {
    $admin = User::factory()->asAdmin()->create();

    $response = $this->actingAs($admin)
        ->withHeaders(['Idempotency-Key' => 'diag-test-001'])
        ->postJson('/api/v1/admin/diagnostics/export');

    $response->assertStatus(201)
        ->assertJsonPath('data.status', DiagnosticExportStatus::Completed->value)
        ->assertJsonPath('data.initiated_by', $admin->id);

    $this->assertDatabaseHas('diagnostic_exports', [
        'initiated_by' => $admin->id,
        'status'       => DiagnosticExportStatus::Completed->value,
    ]);
});

test('non-admin cannot trigger diagnostic export', function () {
    $student = User::factory()->asStudent()->create();

    $this->actingAs($student)
        ->withHeaders(['Idempotency-Key' => 'diag-test-002'])
        ->postJson('/api/v1/admin/diagnostics/export')
        ->assertForbidden();
});

test('unauthenticated request is rejected', function () {
    $this->withHeaders(['Idempotency-Key' => 'diag-test-003'])
        ->postJson('/api/v1/admin/diagnostics/export')
        ->assertUnauthorized();
});

// GET /api/v1/admin/diagnostics/exports
test('admin can list diagnostic exports', function () {
    $admin = User::factory()->asAdmin()->create();

    DiagnosticExport::create([
        'initiated_by'     => $admin->id,
        'status'           => DiagnosticExportStatus::Completed,
        'file_path'        => '/var/diagnostics/test.enc',
        'file_size_bytes'  => 1024,
        'checksum_sha256'  => str_repeat('a', 64),
        'encryption_key_id' => 'v1',
        'completed_at'     => now(),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/diagnostics/exports');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [['id', 'status', 'initiated_by']],
            ],
        ]);
});

test('export list is admin-only', function () {
    $teacher = User::factory()->asTeacher()->create();

    $this->actingAs($teacher)
        ->getJson('/api/v1/admin/diagnostics/exports')
        ->assertForbidden();
});

test('diagnostic export creates audit log entry', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->withHeaders(['Idempotency-Key' => 'diag-audit-001'])
        ->postJson('/api/v1/admin/diagnostics/export')
        ->assertStatus(201);

    $this->assertDatabaseHas('audit_log_entries', [
        'actor_user_id' => $admin->id,
        'action'        => 'diagnostic_export.triggered',
        'target_type'   => 'diagnostic_export',
    ]);
});
