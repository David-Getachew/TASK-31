<?php

declare(strict_types=1);

use App\Enums\BackupStatus;
use App\Models\BackupJob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// GET /api/v1/admin/backups
test('admin can list backup jobs', function () {
    $admin = User::factory()->asAdmin()->create();

    BackupJob::create([
        'scheduled_for'        => now()->subDay(),
        'file_path'            => '/backups/backup_20240101.enc',
        'file_size_bytes'      => 2048,
        'checksum_sha256'      => str_repeat('b', 64),
        'status'               => BackupStatus::Completed,
        'retention_expires_on' => now()->addDays(30)->toDateString(),
        'completed_at'         => now()->subDay(),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/backups');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [['id', 'status', 'scheduled_for', 'retention_expires_on']],
            ],
        ]);
});

test('non-admin cannot list backups', function () {
    $registrar = User::factory()->asRegistrar()->create();

    $this->actingAs($registrar)
        ->getJson('/api/v1/admin/backups')
        ->assertForbidden();
});

// POST /api/v1/admin/backups/trigger
test('admin can trigger a backup job', function () {
    $admin = User::factory()->asAdmin()->create();

    $response = $this->actingAs($admin)
        ->withHeaders(['Idempotency-Key' => 'backup-trigger-001'])
        ->postJson('/api/v1/admin/backups/trigger')
        ->assertStatus(202);

    // Response must carry the deterministic pending job row
    expect($response->json('data.id'))->toBeInt();
    expect($response->json('data.status'))->toBe(\App\Enums\BackupStatus::Pending->value);

    $this->assertDatabaseHas('jobs', [
        'queue' => 'default',
    ]);
});

test('non-admin cannot trigger backup', function () {
    $teacher = User::factory()->asTeacher()->create();

    $this->actingAs($teacher)
        ->withHeaders(['Idempotency-Key' => 'backup-trigger-002'])
        ->postJson('/api/v1/admin/backups/trigger')
        ->assertForbidden();
});

// GET /api/v1/admin/backups/{id}
test('admin can view a specific backup job', function () {
    $admin = User::factory()->asAdmin()->create();

    $job = BackupJob::create([
        'scheduled_for'        => now(),
        'file_path'            => null,
        'file_size_bytes'      => null,
        'checksum_sha256'      => null,
        'status'               => BackupStatus::Running,
        'retention_expires_on' => now()->addDays(30)->toDateString(),
        'completed_at'         => null,
    ]);

    $response = $this->actingAs($admin)->getJson("/api/v1/admin/backups/{$job->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $job->id)
        ->assertJsonPath('data.status', BackupStatus::Running->value);
});

test('pruned backups appear in history for audit trail', function () {
    $admin = User::factory()->asAdmin()->create();

    BackupJob::create([
        'scheduled_for'        => now()->subDays(40),
        'file_path'            => '/backups/old.enc',
        'file_size_bytes'      => 512,
        'checksum_sha256'      => str_repeat('c', 64),
        'status'               => BackupStatus::Pruned,
        'retention_expires_on' => now()->subDays(10)->toDateString(),
        'completed_at'         => now()->subDays(40),
    ]);

    $response = $this->actingAs($admin)->getJson('/api/v1/admin/backups');

    $backups = collect($response->json('data.data'));
    expect($backups->firstWhere('status', BackupStatus::Pruned->value))->not->toBeNull();
});

test('backup trigger creates audit log entry', function () {
    $admin = User::factory()->asAdmin()->create();

    $this->actingAs($admin)
        ->withHeaders(['Idempotency-Key' => 'backup-audit-001'])
        ->postJson('/api/v1/admin/backups/trigger')
        ->assertStatus(202);

    $this->assertDatabaseHas('audit_log_entries', [
        'actor_user_id' => $admin->id,
        'action'        => 'backup.triggered',
        'target_type'   => 'backup',
    ]);
});
