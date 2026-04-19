# audit_report-2 Fix Check

Date: 2026-04-19
Scope: Static re-check only (no runtime execution, no tests run, no Docker).
Baseline: .tmp/audit_report-2.md issues 5.1 to 5.8.

## Overall Result
- Fixed: 6
- Partially Fixed: 2
- Not Fixed: 0

## Issue-by-Issue Verification

### 5.1 Unauthorized cross-scope content/grade writes due permissive policy gates
Status: Fixed

What changed:
- Thread creation now requires section-aware authorization payload instead of class-only create.
- Post creation now checks thread visibility and scoped post-create authorization.
- Comment creation now uses post-aware scoped authorization.
- Grade item create/update policies are no longer unconditional true; they enforce section-scoped teacher/admin rules.

Evidence:
- repo/backend/app/Policies/ThreadPolicy.php:91
- repo/backend/app/Http/Controllers/Api/ThreadController.php:102
- repo/backend/app/Policies/PostPolicy.php:25
- repo/backend/app/Http/Controllers/Api/PostController.php:35
- repo/backend/app/Policies/CommentPolicy.php:25
- repo/backend/app/Http/Controllers/Api/CommentController.php:26
- repo/backend/app/Policies/GradeItemPolicy.php:21
- repo/backend/app/Policies/GradeItemPolicy.php:29

Additional static coverage added:
- repo/backend/api_tests/Domain/Threads/CrossScopeWriteTest.php:43
- repo/backend/api_tests/Domain/GradeItems/CrudAndPublishTest.php:79

---

### 5.2 Object-level read exposure on term/course/section detail endpoints
Status: Fixed

What changed:
- Detail endpoints now call authorize(view, resource).
- Term and course policies were added and registered.

Evidence:
- repo/backend/app/Http/Controllers/Api/CourseController.php:36
- repo/backend/app/Http/Controllers/Api/TermController.php:36
- repo/backend/app/Http/Controllers/Api/SectionController.php:35
- repo/backend/app/Policies/CoursePolicy.php:20
- repo/backend/app/Policies/TermPolicy.php:20
- repo/backend/app/Providers/AppServiceProvider.php:184
- repo/backend/app/Providers/AppServiceProvider.php:185

Additional static coverage added:
- repo/backend/api_tests/Domain/Courses/ReadTest.php:47
- repo/backend/api_tests/Domain/Terms/ReadTest.php:54
- repo/backend/api_tests/Domain/Sections/ReadTest.php:32

---

### 5.3 Scoped authorization weakened by broad role fallbacks
Status: Partially Fixed

What changed:
- Major scoped checks were tightened for enrollment updates, section view/roster, and roster import create/view logic.

Evidence of fixes:
- repo/backend/app/Policies/EnrollmentPolicy.php:18
- repo/backend/app/Policies/SectionPolicy.php:20
- repo/backend/app/Policies/RosterImportPolicy.php:41

Residual gap:
- RosterImportPolicy still has an unscoped registrar role fallback in isStaff(), which can still broaden access semantics for viewAny/history flows.

Residual evidence:
- repo/backend/app/Policies/RosterImportPolicy.php:64

---

### 5.4 Scheduled backup command statically broken
Status: Fixed

What changed:
- Scheduled command now creates a BackupJob and passes job id into BackupMetadataJob::dispatchSync.
- Constructor contract and dispatch now align.

Evidence:
- repo/backend/app/Console/Commands/BackupsRecordMetadataCommand.php:14
- repo/backend/app/Console/Commands/BackupsRecordMetadataCommand.php:31
- repo/backend/app/Jobs/BackupMetadataJob.php:23

Additional static coverage added:
- repo/backend/api_tests/BackupScheduledCommandTest.php:24

---

### 5.5 Payment method contract mismatch (request validation vs schema/enum)
Status: Fixed

What changed:
- InitiatePaymentRequest now derives allowed values directly from PaymentMethod enum cases.
- Validation now matches enum and schema values.

Evidence:
- repo/backend/app/Http/Requests/InitiatePaymentRequest.php:18
- repo/backend/app/Enums/PaymentMethod.php:5
- repo/backend/database/migrations/2026_04_18_005400_create_payment_attempts_table.php:13

---

### 5.6 Restore runbook references nonexistent decrypt command and retention overstatement
Status: Fixed

What changed:
- Backup decrypt command now exists.
- Encryption helper now supports decryptFile.
- Backup pruning logic now deletes files and nulls file_path, aligning with runbook claim.

Evidence:
- repo/backend/app/Console/Commands/BackupDecryptCommand.php:12
- repo/backend/app/Console/Commands/BackupDecryptCommand.php:39
- repo/backend/app/Services/EncryptionHelper.php:100
- repo/backend/app/Jobs/BackupMetadataJob.php:67
- docs/restore-runbook.md:109

Additional static coverage added:
- repo/backend/api_tests/BackupScheduledCommandTest.php:58

---

### 5.7 Requirement/endpoint traceability docs drift from implemented routes
Status: Partially Fixed

What changed:
- Some previously incorrect mappings were corrected (for example, thread route wording now points to /api/v1/threads).

Evidence of correction:
- docs/requirement-traceability.md:12

Residual drift still present:
- R-21 still documents roster/import path while routes use roster-imports.
- R-25 still documents payment methods as cash/card/bank_transfer while enum is cash/check/local_terminal/waiver.

Residual evidence:
- docs/requirement-traceability.md:31
- docs/requirement-traceability.md:35
- repo/backend/routes/api.php:100
- repo/backend/app/Enums/PaymentMethod.php:7

---

### 5.8 Static test corpus overstated critical authz coverage
Status: Fixed

What changed:
- New negative authz tests now exist for cross-scope thread/post/comment writes and term/course/section detail access.

Evidence:
- repo/backend/api_tests/Domain/Threads/CrossScopeWriteTest.php:43
- repo/backend/api_tests/Domain/Threads/CreateThreadTest.php:39
- repo/backend/api_tests/Domain/GradeItems/CrudAndPublishTest.php:79
- repo/backend/api_tests/Domain/Courses/ReadTest.php:47
- repo/backend/api_tests/Domain/Terms/ReadTest.php:54
- repo/backend/api_tests/Domain/Sections/ReadTest.php:32

Note:
- Static verification confirms coverage additions exist; runtime pass/fail still requires manual test execution.

## Final Conclusion
Most material blockers/high issues from the prior inspection are now fixed in static code. Remaining follow-up is focused on documentation drift and one residual unscoped registrar fallback path in roster import staff checks.