<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\EncryptionHelper;
use Illuminate\Console\Command;

final class BackupDecryptCommand extends Command
{
    protected $signature = 'campuslearn:backup:decrypt
        {--input= : Path to the encrypted backup file (.enc)}
        {--output= : Destination path for the decrypted plaintext}
        {--key= : 64-char hex encryption key (defaults to campuslearn.backups.encryption_key)}';

    protected $description = 'Decrypts a nightly backup archive produced by campuslearn:backups:record-metadata.';

    public function handle(EncryptionHelper $encryption): int
    {
        $input  = (string) $this->option('input');
        $output = (string) $this->option('output');
        $key    = (string) ($this->option('key') ?: (string) config('campuslearn.backups.encryption_key'));

        if ($input === '' || $output === '') {
            $this->error('Both --input and --output are required.');
            return self::FAILURE;
        }
        if (! file_exists($input)) {
            $this->error("Input file not found: {$input}");
            return self::FAILURE;
        }
        if ($key === '') {
            $this->error('Encryption key not provided and BACKUP_ENCRYPTION_KEY is not configured.');
            return self::FAILURE;
        }

        try {
            $encryption->decryptFile($input, $output, $key);
        } catch (\Throwable $e) {
            $this->error('Decryption failed: ' . $e->getMessage());
            return self::FAILURE;
        }

        $this->info("Decrypted backup written to {$output}");
        return self::SUCCESS;
    }
}
