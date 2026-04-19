<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DiagnosticExportStatus;
use App\Models\DiagnosticExport;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class DiagnosticExportService
{
    public function __construct(
        private readonly EncryptionHelper $encryption,
        private readonly AuditLogger $audit,
    ) {
    }

    public function trigger(User $actor): DiagnosticExport
    {
        return DB::transaction(function () use ($actor): DiagnosticExport {
            $export = DiagnosticExport::create([
                'initiated_by'     => $actor->id,
                'status'           => DiagnosticExportStatus::Running,
                'file_path'        => 'pending',
                'file_size_bytes'  => 0,
                'checksum_sha256'  => null,
                'encryption_key_id' => 'diagnostic-key-v1',
                'completed_at'     => null,
            ]);

            $this->audit->record($actor->id, 'diagnostic_export.triggered', 'diagnostic_export', $export->id, []);

            try {
                $payload  = $this->collectDiagnosticPayload();
                $hexKey   = config('campuslearn.diagnostics.encryption_key');
                if (! is_string($hexKey) || strlen($hexKey) !== 64) {
                    throw new RuntimeException(
                        'DIAGNOSTIC_ENCRYPTION_KEY is not configured or invalid. Cannot export without a valid 32-byte hex key.',
                    );
                }
                $targetDir = storage_path('app/diagnostics');

                if (! is_dir($targetDir)) {
                    mkdir($targetDir, 0750, true);
                }

                $destPath = $targetDir . '/diag_' . $export->id . '_' . now()->format('Ymd_His') . '.enc';
                $tempPath = $this->writeTemp($payload);
                try {
                    $checksum = $this->encryption->encryptFile($tempPath, $destPath, $hexKey);
                    $size     = (int) filesize($destPath);

                    $export->update([
                        'file_path'       => $destPath,
                        'file_size_bytes' => $size,
                        'checksum_sha256' => $checksum,
                        'status'          => DiagnosticExportStatus::Completed,
                        'completed_at'    => now(),
                    ]);

                    $this->audit->record($actor->id, 'diagnostic_export.completed', 'diagnostic_export', $export->id, [
                        'checksum' => $checksum,
                        'size'     => $size,
                    ]);
                } finally {
                    if (is_string($tempPath) && is_file($tempPath)) {
                        @unlink($tempPath);
                    }
                }
            } catch (\Throwable $e) {
                $export->update(['status' => DiagnosticExportStatus::Failed]);
                throw $e;
            }

            return $export->fresh();
        });
    }

    public function list(int $perPage = 20)
    {
        return DiagnosticExport::with('initiator')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    private function collectDiagnosticPayload(): string
    {
        $data = [
            'generated_at'    => now()->toIso8601String(),
            'php_version'     => PHP_VERSION,
            'laravel_version' => app()->version(),
            'db_status'       => $this->checkDb(),
            'queue_status'    => $this->checkQueue(),
            'config_snapshot' => [
                'edit_window_minutes'   => config('campuslearn.moderation.edit_window_minutes'),
                'order_auto_close_min'  => config('campuslearn.orders.auto_close_minutes'),
                'penalty_grace_days'    => config('campuslearn.billing.penalty_grace_days'),
                'retention_days'        => config('campuslearn.backups.retention_days'),
            ],
        ];

        return json_encode($data, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }

    private function checkDb(): string
    {
        try {
            DB::connection()->getPdo();
            return 'healthy';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function checkQueue(): string
    {
        try {
            \Illuminate\Support\Facades\Queue::size();
            return 'healthy';
        } catch (\Throwable) {
            return 'error';
        }
    }

    private function writeTemp(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'diag_');
        if ($path === false) {
            throw new RuntimeException('Cannot create temp file for diagnostic payload.');
        }
        file_put_contents($path, $content);
        return $path;
    }
}
