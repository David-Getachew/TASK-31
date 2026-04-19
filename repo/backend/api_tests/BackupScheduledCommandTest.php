<?php

declare(strict_types=1);

use App\Enums\BackupStatus;
use App\Models\BackupJob;
use App\Services\DatabaseExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(function () {
    $dir = sys_get_temp_dir() . '/campuslearn_backups_' . uniqid();
    @mkdir($dir, 0777, true);

    // No explicit source_path: the backup pipeline must generate its own DB export.
    config()->set('campuslearn.backups.source_path', null);
    config()->set('campuslearn.backups.target_dir', $dir);
    config()->set('campuslearn.backups.encryption_key', str_repeat('ab', 32));

    $this->backupDir = $dir;
});

test('scheduled backup command generates a DB export and transitions to Completed', function () {
    $exit = Artisan::call('campuslearn:backups:record-metadata');
    expect($exit)->toBe(0);

    $job = BackupJob::orderByDesc('id')->first();
    expect($job)->not->toBeNull();
    expect($job->status)->toBe(BackupStatus::Completed);
    expect($job->file_path)->not->toBeNull();
    expect(file_exists($job->file_path))->toBeTrue();
    expect($job->file_size_bytes)->toBeGreaterThan(0);
    expect($job->checksum_sha256)->toHaveLength(64);
});

test('backup artifact round-trips through decrypt to a valid DB export header', function () {
    Artisan::call('campuslearn:backups:record-metadata');
    $job = BackupJob::orderByDesc('id')->first();

    $out = $this->backupDir . '/restored.sql';
    $exit = Artisan::call('campuslearn:backup:decrypt', [
        '--input'  => $job->file_path,
        '--output' => $out,
    ]);

    expect($exit)->toBe(0);
    expect(file_exists($out))->toBeTrue();
    $firstLine = fgets(fopen($out, 'rb'));
    expect($firstLine)->toContain(DatabaseExportService::HEADER_MAGIC);
});

test('DatabaseExportService produces an artifact that validates and contains DB rows', function () {
    \App\Models\User::factory()->create(['email' => 'dump-test@example.test']);

    $path = $this->backupDir . '/test-export.sql';
    /** @var DatabaseExportService $service */
    $service = app(DatabaseExportService::class);
    $result  = $service->export($path);

    expect($service->validateArtifact($path))->toBeTrue();
    expect($result['tables'])->toBeGreaterThan(0);
    expect($result['rows'])->toBeGreaterThan(0);
    expect($result['bytes'])->toBeGreaterThan(0);
    expect(file_get_contents($path))->toContain('dump-test@example.test');
});

test('backup job fails cleanly when target directory is unwritable (missing config)', function () {
    config()->set('campuslearn.backups.target_dir', null);
    $exit = 0;
    try {
        Artisan::call('campuslearn:backups:record-metadata');
    } catch (\Throwable) {
        $exit = 1;
    }
    $job = BackupJob::orderByDesc('id')->first();
    expect($job)->not->toBeNull();
    expect($job->status)->toBe(BackupStatus::Failed);
});

test('scheduled backup command prunes expired backups and deletes their files', function () {
    $expiredFile = $this->backupDir . '/old.enc';
    file_put_contents($expiredFile, 'stale');

    $old = BackupJob::create([
        'scheduled_for'        => now()->subDays(40),
        'file_path'            => $expiredFile,
        'file_size_bytes'      => 5,
        'checksum_sha256'      => str_repeat('a', 64),
        'status'               => BackupStatus::Completed,
        'retention_expires_on' => now()->subDays(10)->toDateString(),
        'completed_at'         => now()->subDays(40),
    ]);

    Artisan::call('campuslearn:backups:record-metadata');

    $old->refresh();
    expect($old->status)->toBe(BackupStatus::Pruned);
    expect($old->file_path)->toBeNull();
    expect(file_exists($expiredFile))->toBeFalse();
});
