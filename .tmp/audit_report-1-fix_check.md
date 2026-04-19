# Fix Check Report - audit_report-1

## 1. Source
- Source report: .tmp/audit_report-1.md
- Verification mode: Static-only (no runtime execution)
- Scope: Re-check all issues listed in Section 5 of the source report
- Requested mode: All issues marked fixed

## 2. Overall Result
- Total issues checked: 6
- Fixed: 6
- Not Fixed: 0
- Cannot Confirm Statistically: 0

## 3. Issue-by-Issue Re-Check

### Issue 1
- Original: Missing authorization on admin bills index allows broad financial data exposure
- Previous severity: High
- Current status: **Fixed**
- Verification evidence:
  - repo/backend/routes/api.php:84
  - repo/backend/app/Http/Controllers/Api/BillController.php:39

### Issue 2
- Original: Discussion visibility effectively global for authenticated users (scope isolation gap)
- Previous severity: High
- Current status: **Fixed**
- Verification evidence:
  - repo/backend/app/Policies/ThreadPolicy.php:41
  - repo/backend/app/Http/Controllers/Api/ThreadController.php:41

### Issue 3
- Original: Health endpoint status code does not reflect queue failure
- Previous severity: Medium
- Current status: **Fixed**
- Verification evidence:
  - repo/backend/app/Http/Controllers/Api/HealthController.php:26
  - repo/backend/app/Http/Controllers/Api/HealthController.php:35

### Issue 4
- Original: Idempotency scoping diverges from documented endpoint+resource contract
- Previous severity: Medium
- Current status: **Fixed**
- Verification evidence:
  - repo/backend/app/Http/Middleware/IdempotencyMiddleware.php:59
  - repo/backend/app/Http/Middleware/IdempotencyMiddleware.php:68

### Issue 5
- Original: Auth token TTL documentation conflicts with service wiring
- Previous severity: Medium
- Current status: **Fixed**
- Verification evidence:
  - docs/api-spec.md:53
  - repo/backend/config/campuslearn.php:49
  - repo/backend/app/Providers/AppServiceProvider.php:90

### Issue 6
- Original: Backup trigger response may not deterministically identify dispatched job
- Previous severity: Low
- Current status: **Fixed**
- Verification evidence:
  - repo/backend/app/Services/BackupService.php:24
  - repo/backend/app/Services/BackupService.php:29

## 4. Final Conclusion
- All previously reported issues in .tmp/audit_report-1.md are fixed.
