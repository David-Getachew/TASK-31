<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\BackupStatus;
use App\Jobs\BackupMetadataJob;
use App\Models\BackupJob;
use Illuminate\Console\Command;

final class BackupsRecordMetadataCommand extends Command
{
    protected $signature = 'campuslearn:backups:record-metadata';
    protected $description = 'Encrypt the backup source file, record metadata, and prune expired entries';

    public function handle(): int
    {
        $retainDays = (int) config('campuslearn.backups.retention_days', 30);

        $job = BackupJob::create([
            'scheduled_for'        => now(),
            'file_path'            => null,
            'file_size_bytes'      => null,
            'checksum_sha256'      => null,
            'status'               => BackupStatus::Pending,
            'retention_expires_on' => now()->addDays($retainDays)->toDateString(),
            'completed_at'         => null,
        ]);

        BackupMetadataJob::dispatchSync($job->id);

        $this->info('Backup metadata job complete: BackupJob #' . $job->id);
        return self::SUCCESS;
    }
}
