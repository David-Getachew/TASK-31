# Combined Audit Report: Test Coverage + README

Date: 2026-04-19
Scope: static inspection only (no test or code execution)

## 1) Test Coverage Audit

### Project Type Detection
- Declared in README: fullstack (explicit: `Project type: fullstack`).
- Effective audit mode: fullstack.

### Backend Endpoint Inventory
- Source of truth: `repo/backend/routes/api.php`.
- Total endpoints (METHOD + fully resolved PATH): 105.
- Prefix resolution applied: `/api` and `/api/v1` groups.
- Parameterized paths normalized as `{param}` for matching.

### API Test Mapping Table

| Endpoint | Covered | Test type | Test files | Evidence |
|---|---|---|---|---|
| GET /api/health | yes | true no-mock HTTP | api_tests/Authorization/ScopeEnforcementTest.php | api_tests/Authorization/ScopeEnforcementTest.php:37 |
| POST /api/v1/auth/login | yes | true no-mock HTTP | api_tests/Auth/LoginTest.php | api_tests/Auth/LoginTest.php:20 |
| POST /api/v1/auth/logout | yes | true no-mock HTTP | api_tests/Auth/LogoutTest.php | api_tests/Auth/LogoutTest.php:16 |
| GET /api/v1/auth/me | yes | true no-mock HTTP | api_tests/Authorization/ScopeEnforcementTest.php | api_tests/Authorization/ScopeEnforcementTest.php:16 |
| GET /api/v1/health/circuit | yes | true no-mock HTTP | api_tests/Authorization/ScopeEnforcementTest.php | api_tests/Authorization/ScopeEnforcementTest.php:23 |
| GET /api/v1/health/metrics | yes | true no-mock HTTP | api_tests/Authorization/ScopeEnforcementTest.php | api_tests/Authorization/ScopeEnforcementTest.php:30 |
| POST /api/v1/_contract/echo | yes | true no-mock HTTP | api_tests/Contract/IdempotencyContractTest.php | api_tests/Contract/IdempotencyContractTest.php:13 |
| GET /api/v1/dashboard | yes | true no-mock HTTP | api_tests/Domain/Dashboard/DashboardTest.php | api_tests/Domain/Dashboard/DashboardTest.php:10 |
| GET /api/v1/terms | yes | true no-mock HTTP | api_tests/Domain/Terms/ReadTest.php | api_tests/Domain/Terms/ReadTest.php:16 |
| GET /api/v1/terms/{term} | yes | true no-mock HTTP | api_tests/Domain/Terms/ReadTest.php | api_tests/Domain/Terms/ReadTest.php:27 |
| GET /api/v1/courses | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:251 |
| GET /api/v1/courses/{course} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:255 |
| GET /api/v1/sections | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:316 |
| GET /api/v1/sections/{section} | yes | true no-mock HTTP | api_tests/Domain/Sections/ReadTest.php | api_tests/Domain/Sections/ReadTest.php:17 |
| GET /api/v1/sections/{section}/roster | yes | true no-mock HTTP | api_tests/Domain/Sections/ReadTest.php | api_tests/Domain/Sections/ReadTest.php:30 |
| POST /api/v1/terms/{term}/roster-imports | yes | true no-mock HTTP | api_tests/Domain/Roster/ImportTest.php | api_tests/Domain/Roster/ImportTest.php:36 |
| GET /api/v1/terms/{term}/roster-imports | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:293 |
| GET /api/v1/roster-imports/{rosterImport} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:297 |
| GET /api/v1/sections/{section}/grade-items | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:320 |
| POST /api/v1/sections/{section}/grade-items | yes | true no-mock HTTP | api_tests/Domain/GradeItems/CrudAndPublishTest.php | api_tests/Domain/GradeItems/CrudAndPublishTest.php:21 |
| PATCH /api/v1/sections/{section}/grade-items/{gradeItem} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:324 |
| POST /api/v1/sections/{section}/grade-items/{gradeItem}/publish | yes | true no-mock HTTP | api_tests/Domain/GradeItems/PublishScopeTest.php | api_tests/Domain/GradeItems/PublishScopeTest.php:16 |
| POST /api/v1/enrollments/{enrollment}/approve | yes | true no-mock HTTP | api_tests/Domain/Enrollments/ApproveAndDenyTest.php | api_tests/Domain/Enrollments/ApproveAndDenyTest.php:30 |
| POST /api/v1/enrollments/{enrollment}/deny | yes | true no-mock HTTP | api_tests/Domain/Enrollments/ApproveAndDenyTest.php | api_tests/Domain/Enrollments/ApproveAndDenyTest.php:54 |
| GET /api/v1/threads | yes | true no-mock HTTP | api_tests/Domain/Threads/ThreadScopeTest.php | api_tests/Domain/Threads/ThreadScopeTest.php:36 |
| POST /api/v1/threads | yes | true no-mock HTTP | api_tests/Domain/Threads/SensitiveWordRewriteTest.php | api_tests/Domain/Threads/SensitiveWordRewriteTest.php:20 |
| GET /api/v1/threads/{thread} | yes | true no-mock HTTP | api_tests/Domain/Threads/ThreadScopeTest.php | api_tests/Domain/Threads/ThreadScopeTest.php:54 |
| PATCH /api/v1/threads/{thread} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:360 |
| GET /api/v1/threads/{thread}/posts | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:356 |
| POST /api/v1/threads/{thread}/posts | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:348 |
| GET /api/v1/threads/{thread}/posts/{post} | yes | true no-mock HTTP | api_tests/Domain/Threads/BindingIntegrityTest.php | api_tests/Domain/Threads/BindingIntegrityTest.php:20 |
| PATCH /api/v1/threads/{thread}/posts/{post} | yes | true no-mock HTTP | api_tests/Domain/Threads/BindingIntegrityTest.php | api_tests/Domain/Threads/BindingIntegrityTest.php:31 |
| DELETE /api/v1/threads/{thread}/posts/{post} | yes | true no-mock HTTP | api_tests/Domain/Threads/BindingIntegrityTest.php | api_tests/Domain/Threads/BindingIntegrityTest.php:42 |
| POST /api/v1/posts/{post}/comments | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:382 |
| PATCH /api/v1/posts/{post}/comments/{comment} | yes | true no-mock HTTP | api_tests/Domain/Threads/BindingIntegrityTest.php | api_tests/Domain/Threads/BindingIntegrityTest.php:54 |
| DELETE /api/v1/posts/{post}/comments/{comment} | yes | true no-mock HTTP | api_tests/Domain/Threads/BindingIntegrityTest.php | api_tests/Domain/Threads/BindingIntegrityTest.php:66 |
| POST /api/v1/posts/{post}/reports | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:392 |
| POST /api/v1/posts/{post}/comments/{comment}/reports | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:399 |
| GET /api/v1/mentions | yes | true no-mock HTTP | api_tests/Domain/Mentions/IndexTest.php | api_tests/Domain/Mentions/IndexTest.php:14 |
| GET /api/v1/admin/moderation/queue | yes | true no-mock HTTP | api_tests/Domain/Moderation/ModerationQueueTest.php | api_tests/Domain/Moderation/ModerationQueueTest.php:15 |
| GET /api/v1/admin/moderation/history | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:157 |
| POST /api/v1/admin/threads/{thread}/hide | yes | true no-mock HTTP | api_tests/Domain/Moderation/ModerationQueueTest.php | api_tests/Domain/Moderation/ModerationQueueTest.php:33 |
| POST /api/v1/admin/threads/{thread}/restore | yes | true no-mock HTTP | api_tests/Domain/Moderation/ModerationQueueTest.php | api_tests/Domain/Moderation/ModerationQueueTest.php:46 |
| POST /api/v1/admin/threads/{thread}/lock | yes | true no-mock HTTP | api_tests/Domain/Moderation/ModerationQueueTest.php | api_tests/Domain/Moderation/ModerationQueueTest.php:57 |
| POST /api/v1/admin/threads/{thread}/posts/{post}/hide | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:145 |
| POST /api/v1/admin/threads/{thread}/posts/{post}/restore | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:151 |
| POST /api/v1/sensitive-words/check | yes | true no-mock HTTP | api_tests/Domain/Threads/SensitiveWordRewriteTest.php | api_tests/Domain/Threads/SensitiveWordRewriteTest.php:52 |
| GET /api/v1/admin/sensitive-words | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:54 |
| POST /api/v1/admin/sensitive-words | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:43 |
| DELETE /api/v1/admin/sensitive-words/{sensitiveWordRule} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:58 |
| GET /api/v1/notifications | yes | true no-mock HTTP | api_tests/Domain/Notifications/IndexAndUnreadCountTest.php | api_tests/Domain/Notifications/IndexAndUnreadCountTest.php:26 |
| GET /api/v1/notifications/unread-count | yes | true no-mock HTTP | api_tests/Domain/Notifications/BulkMarkReadTest.php | api_tests/Domain/Notifications/BulkMarkReadTest.php:64 |
| POST /api/v1/notifications/mark-read | yes | true no-mock HTTP | api_tests/Domain/Notifications/BulkMarkReadTest.php | api_tests/Domain/Notifications/BulkMarkReadTest.php:15 |
| POST /api/v1/notifications/{id}/read | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:415 |
| GET /api/v1/notifications/preferences | yes | true no-mock HTTP | api_tests/Domain/Notifications/PreferencesTest.php | api_tests/Domain/Notifications/PreferencesTest.php:14 |
| PATCH /api/v1/notifications/preferences | yes | true no-mock HTTP | api_tests/Domain/Notifications/PreferencesTest.php | api_tests/Domain/Notifications/PreferencesTest.php:23 |
| GET /api/v1/orders | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:71 |
| POST /api/v1/orders | yes | true no-mock HTTP | api_tests/Domain/Orders/CreateOrderTest.php | api_tests/Domain/Orders/CreateOrderTest.php:19 |
| GET /api/v1/orders/{order} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:75 |
| DELETE /api/v1/orders/{order} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:79 |
| GET /api/v1/orders/{order}/timeline | yes | true no-mock HTTP | api_tests/Domain/Orders/TimelineTest.php | api_tests/Domain/Orders/TimelineTest.php:23 |
| GET /api/v1/orders/{order}/receipt | yes | true no-mock HTTP | api_tests/Domain/Receipts/ShowAndPrintTest.php | api_tests/Domain/Receipts/ShowAndPrintTest.php:23 |
| GET /api/v1/orders/{order}/receipt/print | yes | true no-mock HTTP | api_tests/Domain/Receipts/ShowAndPrintTest.php | api_tests/Domain/Receipts/ShowAndPrintTest.php:39 |
| GET /api/v1/catalog | yes | true no-mock HTTP | api_tests/Domain/Catalog/CrudTest.php | api_tests/Domain/Catalog/CrudTest.php:36 |
| POST /api/v1/admin/catalog | yes | true no-mock HTTP | api_tests/Domain/Catalog/CrudTest.php | api_tests/Domain/Catalog/CrudTest.php:16 |
| PATCH /api/v1/admin/catalog/{catalogItem} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:128 |
| GET /api/v1/admin/fee-categories | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:95 |
| POST /api/v1/admin/fee-categories | yes | true no-mock HTTP | api_tests/Domain/FeeCategory/CrudTest.php | api_tests/Domain/FeeCategory/CrudTest.php:15 |
| PATCH /api/v1/admin/fee-categories/{feeCategory} | yes | true no-mock HTTP | api_tests/Domain/FeeCategory/CrudTest.php | api_tests/Domain/FeeCategory/CrudTest.php:29 |
| POST /api/v1/admin/fee-categories/{feeCategory}/tax-rules | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:99 |
| PATCH /api/v1/admin/fee-categories/{feeCategory}/tax-rules/{taxRule} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:110 |
| GET /api/v1/bills | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:236 |
| GET /api/v1/bills/{bill} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:240 |
| GET /api/v1/admin/bills | yes | true no-mock HTTP | api_tests/Domain/Billing/AdminGenerateBillTest.php | api_tests/Domain/Billing/AdminGenerateBillTest.php:62 |
| GET /api/v1/billing-schedules | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:214 |
| PATCH /api/v1/billing-schedules/{billSchedule} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:218 |
| GET /api/v1/refunds | yes | true no-mock HTTP | api_tests/Domain/Refunds/CreateAndReversalTest.php | api_tests/Domain/Refunds/CreateAndReversalTest.php:73 |
| GET /api/v1/refunds/{refund} | yes | true no-mock HTTP | api_tests/Domain/Refunds/CreateAndReversalTest.php | api_tests/Domain/Refunds/CreateAndReversalTest.php:91 |
| GET /api/v1/refund-reason-codes | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:265 |
| POST /api/v1/admin/refunds/{refund}/approve | yes | true no-mock HTTP | api_tests/Domain/Refunds/RefundApprovalTest.php | api_tests/Domain/Refunds/RefundApprovalTest.php:20 |
| POST /api/v1/admin/refunds/{refund}/reject | yes | true no-mock HTTP | api_tests/Domain/Refunds/RefundApprovalTest.php | api_tests/Domain/Refunds/RefundApprovalTest.php:35 |
| GET /api/v1/admin/ledger | yes | true no-mock HTTP | api_tests/Domain/Ledger/AdminIndexTest.php | api_tests/Domain/Ledger/AdminIndexTest.php:26 |
| GET /api/v1/admin/reconciliation | yes | true no-mock HTTP | api_tests/Domain/Reconciliation/CrudTest.php | api_tests/Domain/Reconciliation/CrudTest.php:24 |
| GET /api/v1/admin/reconciliation/summary | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:175 |
| POST /api/v1/admin/reconciliation/{reconciliationFlag}/resolve | yes | true no-mock HTTP | api_tests/Domain/Reconciliation/CrudTest.php | api_tests/Domain/Reconciliation/CrudTest.php:40 |
| POST /api/v1/admin/reconciliation-flags/{reconciliationFlag}/resolve | yes | true no-mock HTTP | api_tests/Domain/Refunds/RefundApprovalTest.php | api_tests/Domain/Refunds/RefundApprovalTest.php:59 |
| GET /api/v1/appointments | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:190 |
| POST /api/v1/appointments | yes | true no-mock HTTP | api_tests/Domain/Appointments/CrudAndChangeNotificationTest.php | api_tests/Domain/Appointments/CrudAndChangeNotificationTest.php:17 |
| GET /api/v1/appointments/{appointment} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:194 |
| PATCH /api/v1/appointments/{appointment} | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:199 |
| DELETE /api/v1/appointments/{appointment} | yes | true no-mock HTTP | api_tests/Domain/Appointments/CrudAndChangeNotificationTest.php | api_tests/Domain/Appointments/CrudAndChangeNotificationTest.php:49 |
| GET /api/v1/admin/diagnostics/exports | yes | true no-mock HTTP | api_tests/DiagnosticsTest.php | api_tests/DiagnosticsTest.php:59 |
| GET /api/v1/admin/backups | yes | true no-mock HTTP | api_tests/BackupTest.php | api_tests/BackupTest.php:26 |
| GET /api/v1/admin/backups/{id} | yes | true no-mock HTTP | api_tests/BackupTest.php | api_tests/BackupTest.php:85 |
| GET /api/v1/admin/dr-drills | yes | true no-mock HTTP | api_tests/DrillTest.php | api_tests/DrillTest.php:23 |
| POST /api/v1/admin/dr-drills | yes | true no-mock HTTP | api_tests/DrillTest.php | api_tests/DrillTest.php:45 |
| GET /api/v1/admin/settings | yes | true no-mock HTTP | api_tests/AdminSettingsTest.php | api_tests/AdminSettingsTest.php:14 |
| PATCH /api/v1/admin/settings | yes | true no-mock HTTP | api_tests/AdminSettingsTest.php | api_tests/AdminSettingsTest.php:32 |
| GET /api/v1/admin/audit-log | yes | true no-mock HTTP | api_tests/AdminSettingsTest.php | api_tests/AdminSettingsTest.php:104 |
| POST /api/v1/orders/{order}/payment | yes | true no-mock HTTP | api_tests/Domain/Coverage/EndpointGapClosureTest.php | api_tests/Domain/Coverage/EndpointGapClosureTest.php:431 |
| POST /api/v1/orders/{order}/payment/complete | yes | true no-mock HTTP | api_tests/Domain/Orders/PaymentIdempotencyTest.php | api_tests/Domain/Orders/PaymentIdempotencyTest.php:20 |
| POST /api/v1/admin/bills/generate | yes | true no-mock HTTP | api_tests/Domain/Billing/AdminGenerateBillTest.php | api_tests/Domain/Billing/AdminGenerateBillTest.php:33 |
| POST /api/v1/bills/{bill}/refunds | yes | true no-mock HTTP | api_tests/Domain/Refunds/CreateAndReversalTest.php | api_tests/Domain/Refunds/CreateAndReversalTest.php:29 |
| POST /api/v1/admin/diagnostics/export | yes | true no-mock HTTP | api_tests/DiagnosticsTest.php | api_tests/DiagnosticsTest.php:18 |
| POST /api/v1/admin/backups/trigger | yes | true no-mock HTTP | api_tests/BackupTest.php | api_tests/BackupTest.php:50 |

### API Test Classification
1. True No-Mock HTTP: 42 files in `repo/backend/api_tests` issue real HTTP requests via Laravel HTTP test client (e.g., `getJson/postJson/patchJson/deleteJson`) with no mock declarations in API test files.
2. HTTP with Mocking: none detected in backend API tests (no `Mockery`, `shouldReceive`, `vi.mock`, `jest.mock`, `sinon.stub` in `repo/backend/api_tests`).
3. Non-HTTP (unit/integration without HTTP) inside API test tree:
   - `repo/backend/api_tests/Domain/Billing/PenaltyTest.php`
   - `repo/backend/api_tests/Domain/Billing/RecurringSchedulerTest.php`
   - `repo/backend/api_tests/Domain/Orders/AutoCloseTest.php`
   - `repo/backend/api_tests/Domain/Payment/CompleteTest.php`

### Mock Detection
- Backend API tests: no mock/stub indicators found (strict grep for mocking primitives returned none in `repo/backend/api_tests`).
- Backend unit tests with mocking (expected in unit context):
  - `repo/backend/unit_tests/Middleware/EnforceReadOnlyModeMiddlewareTest.php`: mocks `CircuitBreakerService` via Mockery.
  - `repo/backend/unit_tests/Jobs/AlertThresholdEvaluationJobTest.php`: mocks `CircuitBreakerService` via Mockery.
- Frontend unit tests: heavy mocking by design (Vitest). Representative files:
  - `repo/frontend/unit_tests/adapters/orders.test.ts` (`vi.mock` http adapter)
  - `repo/frontend/unit_tests/views/dashboard.test.ts` (`vi.mock` dashboard/notification adapters)
  - `repo/frontend/unit_tests/stores/notifications.test.ts` (`vi.mock` notifications adapter)

### Coverage Summary
- Total endpoints: 105
- Endpoints with HTTP tests: 105
- Endpoints with TRUE no-mock HTTP tests: 105
- HTTP coverage: 100.00%
- True API coverage: 100.00%

### Unit Test Summary
#### Backend Unit Tests
- Test files present under `repo/backend/unit_tests`: yes (40+ files).
- Controllers covered: minimal direct unit coverage (explicit: `NotificationControllerUnitTest.php`).
- Services covered: broad coverage in `repo/backend/unit_tests/Services` (Order, Payment, Billing, Refund, Moderation, ContentSubmission, Notification orchestration, Roster import, Audit).
- Repositories covered: present in `repo/backend/unit_tests/Repositories`.
- Auth/guards/middleware covered: present in `repo/backend/unit_tests/Domain/Auth` and `repo/backend/unit_tests/Middleware`.
- Important backend modules NOT unit-tested directly (controller-unit perspective): most API controllers in `repo/backend/app/Http/Controllers/Api` are not unit-tested individually (coverage is API/integration level instead).

#### Frontend Unit Tests (STRICT REQUIREMENT)
- Frontend test files: present (e.g., `repo/frontend/unit_tests/views/dashboard.test.ts`, `repo/frontend/unit_tests/components/ReadOnlyBanner.test.ts`, `repo/frontend/unit_tests/stores/notifications.test.ts`, `repo/frontend/unit_tests/adapters/http.test.ts`).
- Framework/tools detected: Vitest (`repo/frontend/package.json`), Vue Test Utils, jsdom.
- Evidence tests target actual frontend modules/components:
  - component render/import: `ReadOnlyBanner.vue` in `repo/frontend/unit_tests/components/ReadOnlyBanner.test.ts`
  - view render/import: `StudentDashboard.vue`, `AdminDashboard.vue` in `repo/frontend/unit_tests/views/dashboard.test.ts`
  - store/module tests: `repo/frontend/unit_tests/stores/*.test.ts`, `repo/frontend/unit_tests/composables/*.test.ts`
- Important frontend modules with no direct unit tests found (representative):
  - `repo/frontend/src/views/LoginView.vue`
  - `repo/frontend/src/views/HomeView.vue`
  - `repo/frontend/src/components/GlobalToast.vue`
  - `repo/frontend/src/components/SessionExpiredOverlay.vue`
  - `repo/frontend/src/stores/toast.ts`, `repo/frontend/src/stores/orders.ts`, `repo/frontend/src/stores/billing.ts`, `repo/frontend/src/stores/courses.ts`, `repo/frontend/src/stores/admin.ts`
- Mandatory verdict: **Frontend unit tests: PRESENT**

#### Cross-Layer Observation
- Backend and frontend are both tested. Balance is backend-heavy in API depth, but frontend unit and e2e suites are present; no frontend testing absence gap.

### API Observability Check
- Endpoint visibility: strong (explicit method+path in tests).
- Request input visibility: strong (explicit request bodies/headers in most write tests).
- Response content visibility: moderate-to-strong; many tests assert JSON paths and DB state, but a subset are status-only authorization checks.
- Overall observability verdict: acceptable, with minor weakness in status-only checks.

### Tests Check
- Success paths: present across auth, dashboard, discussions, orders, billing, refunds, admin ops.
- Failure/validation/authz paths: present (401/403/404/409/422 across many files).
- Edge/idempotency cases: present (contract idempotency + payment idempotency + scheduler idempotence tests).
- Assertion depth: generally meaningful (JSON path + database assertions), with some superficial status-only checks.
- `repo/run_tests.sh`: Docker-based orchestration (compliant).
- Local dependency requirement in script: not required; commands are containerized.

### End-to-End Expectations (Fullstack)
- E2E tests exist in `repo/frontend/e2e` and are wired in `repo/run_tests.sh` (suite `--suite=e2e`).
- Fullstack expectation status: satisfied.

### Test Coverage Score (0-100)
- Score: **94/100**

### Score Rationale
- + full route coverage at HTTP layer (105/105)
- + no mocking in backend API HTTP tests
- + broad failure/auth/idempotency coverage
- - four files in API test tree are non-HTTP and should be relocated for taxonomy clarity
- - some assertions are status-only (observability depth variance)

### Key Gaps
- Test taxonomy gap: non-HTTP tests under `repo/backend/api_tests` blur API-test boundaries.
- Missing direct unit tests for several frontend modules and many backend controllers (integration coverage exists, but unit granularity is uneven).

### Confidence & Assumptions
- Confidence: high.
- Assumption: route declarations in `repo/backend/routes/api.php` are the complete endpoint source; no dynamic runtime route registration beyond inspected files.
- Assumption: strict static match uses method + normalized path; query-string variants do not define new endpoints.

### Test Coverage Final Verdict
- **PASS (with structural quality notes)**

## 2) README Audit

### README Location
- Required file `repo/README.md`: present.

### Hard Gate Checks
- Formatting/readability: PASS (structured markdown with sections/tables).
- Startup instructions (fullstack): PASS (contains `docker-compose up --build` and `docker compose up --build`).
- Access method: PASS (frontend URL + backend URL + health URL with ports).
- Verification method: PASS (curl health checks and auth flow verification examples).
- Environment rules (no runtime package installs/manual DB setup): PASS (no npm/pip/apt install instructions; operations shown as Docker-contained commands).
- Demo credentials (auth exists): PASS (email/password provided for Student, Teacher, Registrar, Administrator).

### Engineering Quality
- Tech stack clarity: strong.
- Architecture explanation: strong (frontend architecture, offline design, queue flow, scheduler table).
- Testing instructions: strong (unit/api/frontend-unit/e2e suites documented).
- Security/roles explanation: moderate-to-strong (role list and auth behavior included).
- Workflow/operations clarity: strong (docker topology, verification commands, environment defaults).

### High Priority Issues
- None.

### Medium Priority Issues
- Resolved: endpoint-group path prefixes in `repo/README.md` now align with backend route declarations (notably discussions and moderation groups).
- Resolved: endpoint examples were normalized to fully-qualified prefixes to reduce tester confusion (including enrollments approve/deny examples).

### Low Priority Issues
- README is very long; critical startup/verification steps are correct but buried among extensive architecture details.

### Hard Gate Failures
- None.

### README Verdict
- **PARTIAL PASS** (all hard gates pass; medium-level documentation accuracy issues remain).

## Final Verdicts
- Test Coverage Audit: **PASS (with structural quality notes)**
- README Audit: **PARTIAL PASS**
