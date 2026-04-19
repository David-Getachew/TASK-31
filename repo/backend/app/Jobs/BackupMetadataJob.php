<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\BackupStatus;
use App\Models\BackupJob;
use App\Services\DatabaseExportService;
use App\Services\EncryptionHelper;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

final class BackupMetadataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(private readonly int $backupJobId) {}

    public function handle(EncryptionHelper $encryption, DatabaseExportService $exporter): void
    {
        $targetDir = config('campuslearn.backups.target_dir');
        $hexKey    = config('campuslearn.backups.encryption_key');
        $override  = config('campuslearn.backups.source_path');

        $job = BackupJob::findOrFail($this->backupJobId);
        $job->update(['status' => BackupStatus::Running]);

        $generatedPath = null;

        try {
            if (! is_string($targetDir)) {
                throw new \RuntimeException('Backup target directory not configured.');
            }
            if (! is_dir($targetDir)) {
                mkdir($targetDir, 0750, true);
            }

            if (is_string($override) && $override !== '' && file_exists($override)) {
                // Staging/test override: use the pre-seeded artifact as-is.
                $sourcePath = $override;
            } else {
                // Primary path: produce a deterministic SQL dump from the live DB.
                $generatedPath = rtrim($targetDir, '/\\') . '/dump_' . now()->format('Ymd_His') . '.sql';
                $exporter->export($generatedPath);
                if (! $exporter->validateArtifact($generatedPath)) {
                    throw new \RuntimeException('Generated DB export failed artifact validation.');
                }
                $sourcePath = $generatedPath;
            }

            $destFile = rtrim($targetDir, '/\\') . '/backup_' . now()->format('Ymd_His') . '.enc';
            $checksum = $encryption->encryptFile($sourcePath, $destFile, $hexKey ?? '');
            $size     = (int) filesize($destFile);

            $job->update([
                'file_path'       => $destFile,
                'file_size_bytes' => $size,
                'checksum_sha256' => $checksum,
                'status'          => BackupStatus::Completed,
                'completed_at'    => now(),
            ]);

        } catch (Throwable $e) {
            $job->update(['status' => BackupStatus::Failed]);
            throw $e;
        } finally {
            if ($generatedPath !== null && is_file($generatedPath)) {
                @unlink($generatedPath);
            }
        }

        // Mark old records past retention as pruned and delete their files from disk.
        $expired = BackupJob::where('status', BackupStatus::Completed)
            ->where('retention_expires_on', '<', now()->toDateString())
            ->get();

        foreach ($expired as $record) {
            if (is_string($record->file_path) && $record->file_path !== '' && file_exists($record->file_path)) {
                @unlink($record->file_path);
            }
            $record->update([
                'status'    => BackupStatus::Pruned,
                'file_path' => null,
            ]);
        }
    }
}
