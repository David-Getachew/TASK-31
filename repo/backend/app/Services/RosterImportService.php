<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\RosterImportStatus;
use App\Models\Enrollment;
use App\Models\RosterImport;
use App\Models\RosterImportError;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Support\AuditLogger;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class RosterImportService
{
    public function __construct(
        private readonly ScopeResolutionService $scopeService,
        private readonly AuditLogger $audit,
    ) {
    }

    /**
     * Accept a CSV file path + metadata and ingest rows:
     * email, name, section_code
     *
     * Scope: operator must be an admin or the registrar assigned to the term.
     */
    public function import(User $operator, Term $term, string $sourceFilename, string $csvPath): RosterImport
    {
        if (! $this->canImport($operator->id, $term->id)) {
            throw new RuntimeException('Operator not authorized for term roster import.');
        }

        return DB::transaction(function () use ($operator, $term, $sourceFilename, $csvPath): RosterImport {
            $import = RosterImport::create([
                'term_id'         => $term->id,
                'initiated_by'    => $operator->id,
                'source_filename' => $sourceFilename,
                'row_count'       => 0,
                'success_count'   => 0,
                'error_count'     => 0,
                'status'          => RosterImportStatus::Running,
                'completed_at'    => null,
            ]);

            [$rowCount, $successCount, $errorCount] = $this->processCsv($import, $term, $csvPath);

            $import->update([
                'row_count'     => $rowCount,
                'success_count' => $successCount,
                'error_count'   => $errorCount,
                'status'        => $errorCount === 0 ? RosterImportStatus::Completed : RosterImportStatus::Completed,
                'completed_at'  => now(),
            ]);

            $this->audit->record($operator->id, 'roster.imported', 'roster_import', $import->id, [
                'term_id'       => $term->id,
                'row_count'     => $rowCount,
                'success_count' => $successCount,
                'error_count'   => $errorCount,
            ]);

            return $import->fresh('errors');
        });
    }

    private function canImport(int $userId, int $termId): bool
    {
        if ($this->scopeService->canPerform($userId, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        return $this->scopeService->canPerform($userId, RoleName::Registrar, ScopeContext::term($termId));
    }

    /**
     * @return array{0:int,1:int,2:int} row_count, success_count, error_count
     */
    private function processCsv(RosterImport $import, Term $term, string $csvPath): array
    {
        $handle = @fopen($csvPath, 'rb');
        if ($handle === false) {
            RosterImportError::create([
                'roster_import_id' => $import->id,
                'row_number'       => 0,
                'error_code'       => 'file_unreadable',
                'message'          => 'Unable to open uploaded CSV.',
                'raw_row'          => [],
                'created_at'       => now(),
            ]);
            return [0, 0, 1];
        }

        $row      = 0;
        $success  = 0;
        $errors   = 0;
        $header   = null;

        while (($cols = fgetcsv($handle)) !== false) {
            $row++;
            if ($header === null) {
                $header = array_map('strtolower', array_map('trim', $cols));
                continue;
            }

            $record = array_combine($header, array_pad($cols, count($header), '')) ?: [];
            $email  = trim((string) ($record['email'] ?? ''));
            $name   = trim((string) ($record['name'] ?? ''));
            $code   = trim((string) ($record['section_code'] ?? ''));

            if ($email === '' || $name === '' || $code === '') {
                $errors++;
                $this->recordError($import->id, $row, 'missing_field', 'email, name, and section_code are required.', $record);
                continue;
            }

            $section = Section::where('term_id', $term->id)->where('section_code', $code)->first();
            if ($section === null) {
                $errors++;
                $this->recordError($import->id, $row, 'section_not_found', "Section {$code} not in term.", $record);
                continue;
            }

            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name'     => $name,
                    'password' => Hash::make(bin2hex(random_bytes(8))),
                    'locale'   => 'en',
                    'status'   => AccountStatus::Active,
                ],
            );

            Enrollment::updateOrCreate(
                ['user_id' => $user->id, 'section_id' => $section->id],
                [
                    'status'       => EnrollmentStatus::Enrolled,
                    'enrolled_at'  => now(),
                    'withdrawn_at' => null,
                ],
            );
            $success++;
        }
        fclose($handle);

        return [$row > 0 ? $row - 1 : 0, $success, $errors];
    }

    /** @param array<string, mixed> $rawRow */
    private function recordError(int $importId, int $row, string $code, string $message, array $rawRow): void
    {
        RosterImportError::create([
            'roster_import_id' => $importId,
            'row_number'       => $row,
            'error_code'       => $code,
            'message'          => $message,
            'raw_row'          => $rawRow,
            'created_at'       => now(),
        ]);
    }
}
