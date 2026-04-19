# Test Traceability

Maps every authored test to the requirement it covers, the endpoint under test, and its current pass/fail status. Updated as tests are authored across all prompts.

**Status values:** `authored` | `passing` | `failing` | `skipped`

> **Note on `authored` status:** this column reflects *static presence* of a test
> case that is wired to its requirement — it is **not** a verified execution
> outcome. Promoting a row to `passing` requires running the suite against the
> current code and capturing the result. Rows still labeled `authored` must
> therefore not be interpreted as a guarantee of runtime correctness.
>
> **High-risk scenarios to re-run and promote explicitly after each change:**
> - Past-due/penalty logic — must include a future-due negative case (`Domain/Billing/PenaltyTest.php`).
> - Admin-only health metrics — must include non-admin 403 assertion (`HealthTest.php`).
> - Nested moderation/report route integrity (`Domain/Moderation/NestedIntegrityTest.php`).
> - Cross-scope staff denial across orders/bills/appointments/report queue (`Authorization/ScopeBypassPolicyTest.php`).
> - Scoped registrar roster import (cross-term denial + assigned-term allow) (`Domain/Roster/ImportTest.php`).
> - Backup DB-export generation and encryption round-trip (`BackupScheduledCommandTest.php`).
> - Circuit-breaker alert emission on mode transitions (`unit_tests/Jobs/AlertThresholdEvaluationJobTest.php`).
> - Contract echo endpoint auth gate (`Contract/IdempotencyContractTest.php`, `Contract/MalformedRequestTest.php`).
> - Validation error code contract (`VALIDATION_FAILED`, not `UNPROCESSABLE_ENTITY`) in `Contract/ErrorEnvelopeTest.php`.
> - Moderation report adapter call signature (`unit_tests/adapters/moderation.test.ts`).

---

## Backend Unit Tests (`repo/backend/unit_tests/`)

| Test File | Test Name | Req Coverage | Status |
|---|---|---|---|
| `BootstrapTest.php` | Laravel application instance is created | — | authored |
| `BootstrapTest.php` | application has correct name | — | authored |
| `BootstrapTest.php` | database connection is configured for mysql | — | authored |
| `BootstrapTest.php` | queue connection is configured | — | authored |
| `Domain/Billing/MoneyTest.php` | add, subtract, multiplyBps, allocate, format, overflow guards | R-38 | authored |
| `Domain/Billing/TaxRuleCalculatorTest.php` | base × rate_bps half-up; zero & edge rates | R-37 | authored |
| `Domain/Billing/PenaltyCalculatorTest.php` | < 10 days → 0; ≥ 10 days → 5% half-up | R-32 | authored |
| `Domain/Billing/BillScheduleCalculatorTest.php` | 1st-of-month 02:00 next-run; end_on stop; closed schedule null | R-31 | authored |
| `Domain/Billing/RefundPolicyTest.php` | allowed within ceiling; rejected when exceeds (paid − refunded); reason-code required | R-34, R-35 | authored |
| `Domain/Billing/IdempotencyServiceTest.php` | first-call executes & stores; replay returns cached; differing fingerprint conflict | R-33 | authored |
| `Domain/Orders/OrderStateMachineTest.php` | legal/illegal transitions across pending→paid/canceled/auto_closed, paid→refunded/redeemed | R-22, R-25, R-26 | authored |
| `Domain/Moderation/EditWindowPolicyTest.php` | boundary at 0, 14:59, 15:00, 15:01 | R-08 | authored |
| `Domain/Moderation/ModerationStateMachineTest.php` | visible↔hidden; visible→locked; locked→visible via unlock | R-10 | authored |
| `Domain/Moderation/SensitiveWordFilterTest.php` | substring + exact; case-insensitive; UTF-8 NFC; multiple matches & ranges | R-11 | authored |
| `Domain/Auth/PasswordRuleTest.php` | < 10 chars rejected; empty rejected; ≥ 10 accepted | R-28 | authored |
| `Domain/Auth/LoginThrottlePolicyTest.php` | 4/15min allow; 5 → lock; outside window → allow | R-29 | authored |
| `Domain/Auth/ScopeResolutionServiceTest.php` | admin@global override; registrar@term ancestry; teacher@section; grade_item specificity | R-18, R-19 | authored |
| `Domain/Observability/CircuitBreakerPolicyTest.php` | > tripThresholdBps trips; < resetThresholdBps resets; hysteresis band holds mode; small sample holds mode | R-42 | authored |
| `Domain/Auth/AuthServiceTest.php` | successful login returns token; wrong password → InvalidCredentials; 5 failures → AccountLocked; expired lock releases; old attempts outside window don't count; success clears attempts | R-27, R-28, R-29 | authored |
| `Domain/Observability/MetricsAggregatorTest.php` | zero metrics → 0 rate; error rate calculation; out-of-window exclusion; latency percentiles | R-40, R-41 | authored |

| `Domain/Moderation/MentionParserTest.php` | extracts @handle from body; dedupes; ignores non-user handles; empty body; case-insensitive | R-06 | authored |
| `Services/AuditLoggerTest.php` | record() writes audit_log_entries row with actor+action+target; correlation id pulled from request; null actor stored as null | — | authored |
| `Services/NotificationOrchestratorTest.php` | opted-out recipient excluded from dispatch; opted-in receives job; placeholder substitution renders correctly | R-12, R-13, R-46 | authored |
| `Services/RosterImportServiceTest.php` | non-registrar scope rejected 403; CSV parse errors recorded in roster_import_errors; success inserts enrollment rows | R-21 | authored |
| `Services/OrderServiceTest.php` | tax snapshot frozen into order_lines at creation; auto_close_at set from config; autoClose() transitions status and records timeline event; duplicate auto-close is idempotent | R-22, R-26 | authored |
| `Services/BillingServiceTest.php` | initial bill posts Charge ledger entry; recurring run advances next_run_on via BillScheduleCalculator; penalty skipped when penalty_jobs row exists for same idempotency_key | R-30, R-31, R-32 | authored |
| `Services/RefundServiceTest.php` | request() rejected when amount exceeds ceiling; reason_code required; approve() posts Reversal ledger entry; approve() creates reconciliation_flags row | R-34, R-35, R-36 | authored |
| `Services/ModerationServiceTest.php` | hide transitions to hidden; restore transitions to visible; lock transitions to locked; illegal transition throws InvalidStateTransition | R-10 | authored |
| `Services/ContentSubmissionServiceTest.php` | sensitive-word blocks create with 422 SENSITIVE_WORDS_BLOCKED; happy path persists thread; @mention triggers notification dispatch; edit after window rejected with EditWindowExpired | R-02, R-04, R-06, R-08, R-11 | authored |
| `Services/PaymentServiceTest.php` | initiate creates PaymentAttempt with status=pending; complete transitions order to Paid + creates Receipt + writes ledger entry; complete on already-paid order throws RuntimeException; complete writes audit_log_entries row with action=order.paid | R-23, R-24 | authored |
| `Jobs/OrderAutoCloseJobTest.php` | closes overdue PendingPayment orders; already-canceled orders not double-processed | R-26 | authored |
| `Jobs/RecurringBillingJobTest.php` | runs only on configured day-of-month; advances billing schedule next_run_on; no double-charge on re-run | R-31 | authored |
| `Jobs/AlertThresholdEvaluationJobTest.php` | handle calls CircuitBreakerService::evaluate; artisan command returns exit code 0 | R-41 | authored |
| `Jobs/PenaltyJobTest.php` | unique penalty_jobs.idempotency_key prevents duplicate penalty on same bill+date; applies only after grace period expires | R-32 | authored |
| `Jobs/SendNotificationJobTest.php` | writes notification row and delivery row per recipient; failure increments attempt and records failure_reason | R-46 | authored |

---

## Backend API/Integration Tests (`repo/backend/api_tests/`)

| Test File | Test Name | Endpoint | Req Coverage | Status |
|---|---|---|---|---|
| `HealthTest.php` | GET /api/health returns 200 with ok status | `GET /api/health` | R-40 | authored |
| `HealthTest.php` | GET /api/health returns service name campuslearn | `GET /api/health` | R-40 | authored |
| `HealthTest.php` | GET /api/v1/health/circuit requires authentication | `GET /api/v1/health/circuit` | R-42 | authored |
| `HealthTest.php` | GET /api/v1/health/metrics requires authentication | `GET /api/v1/health/metrics` | R-40 | authored |
| `Contract/ErrorEnvelopeTest.php` | unknown route → 404 envelope; POST /api/health → 405; /api/v1/health/circuit unauthenticated → 401 `UNAUTHENTICATED` | All | — | authored |
| `Contract/MalformedRequestTest.php` | missing `Idempotency-Key` on `idempotent` route → 400 `IDEMPOTENCY_KEY_REQUIRED`; happy path 200 | `POST /api/v1/_contract/echo` | R-33 | authored |
| `Contract/IdempotencyContractTest.php` | replay returns cached body + `X-Idempotent-Replay: true`; mismatched fingerprint → 409 `IDEMPOTENCY_KEY_CONFLICT` | `POST /api/v1/_contract/echo` | R-33 | authored |
| `Contract/CorrelationIdTest.php` | `X-Correlation-Id` auto-generated if absent; inbound header echoed verbatim | Any | R-39 | authored |
| `Auth/LoginTest.php` | successful login → token envelope; wrong password → 401 INVALID_CREDENTIALS; unknown email → 401; locked account → 423 ACCOUNT_LOCKED; missing email → 422; response includes X-Correlation-Id | `POST /api/v1/auth/login` | R-27, R-28, R-29 | authored |
| `Auth/LogoutTest.php` | authenticated logout → 200; token revoked after logout; unauthenticated → 401 UNAUTHENTICATED | `POST /api/v1/auth/logout` | R-27 | authored |
| `Auth/MeTest.php` | returns current user with roles; no token → 401; expired token → 401; response hides password hash | `GET /api/v1/auth/me` | R-27 | authored |
| `Authorization/ScopeEnforcementTest.php` | unauthenticated → 401 on me/circuit/metrics; public /api/health passes; valid token → 200 on me | All authenticated routes | R-18, R-19 | authored |
| `Domain/Threads/CreateThreadTest.php` | teacher can create announcement; sensitive-word blocks post with 422; non-enrolled user rejected with 403 | R-02, R-03, R-11, R-20 | authored |
| `Domain/Orders/CreateOrderTest.php` | creates order with tax snapshot; catalog item not found → 404; list returns user-scoped orders | R-22, R-37 | authored |
| `Domain/Orders/AutoCloseTest.php` | artisan campuslearn:orders:auto-close cancels overdue PendingPayment orders | R-26 | authored |
| `Domain/Payment/CompleteTest.php` | complete payment transitions order to Paid and generates receipt; replay with same Idempotency-Key returns cached 200 | R-23, R-24, R-33 | authored |
| `Domain/Notifications/IndexAndUnreadCountTest.php` | index returns paginated notifications for authenticated user; unread-count returns per-category counts | R-12, R-13 | authored |
| `Domain/Roster/ImportTest.php` | registrar can upload CSV and get 202 import_id; non-registrar receives 403 | R-21 | authored |
| `Domain/GradeItems/CrudAndPublishTest.php` | teacher creates grade item in draft; publish transitions to published and dispatches grade.published notification | R-15, R-20 | authored |
| `Domain/Billing/AdminGenerateBillTest.php` | admin generates initial bill; admin generates supplemental bill; non-admin returns 403 | R-30, R-37 | authored |
| `Domain/Refunds/CreateAndReversalTest.php` | staff creates refund; non-operator returns 403; user list only sees own refunds; user cannot view other user's refund; approve posts Reversal ledger entry | R-34, R-35, R-36 | authored |
| `Domain/Appointments/CrudAndChangeNotificationTest.php` | staff creates appointment; reschedule dispatches appointment.rescheduled notification; cancel dispatches appointment.canceled | R-16, R-50 | authored |
| `Domain/Enrollments/ApproveAndDenyTest.php` | staff can approve enrollment setting status Enrolled; staff can deny enrollment setting status Withdrawn | R-14 | authored |
| `Domain/Dashboard/DashboardTest.php` | student role sees enrollment + bill summary; teacher role sees section + grade-item summary; registrar role sees pending roster imports; admin role sees full system summary; unauthenticated → 401 | R-01 | authored |
| `Domain/Moderation/ModerationQueueTest.php` | staff sees queue with flagged threads; hide transitions thread to hidden; restore transitions to visible; lock is terminal for author edits; illegal transition returns 409 | R-10 | authored |
| `Domain/Notifications/BulkMarkReadTest.php` | bulk mark-read marks selected notifications; category-scoped mark-all clears only that category; user cannot mark another user's notifications; unread count updates after mark | R-12, R-13 | authored |
| `Domain/Orders/PaymentIdempotencyTest.php` | complete payment with Idempotency-Key succeeds; replay with same key returns 200 and cached result; missing Idempotency-Key returns 422; completing already-canceled order returns 422 INVALID_STATE_TRANSITION | R-23, R-25, R-33 | authored |
| `Domain/GradeItems/PublishScopeTest.php` | teacher assigned to section can publish grade item; teacher from different section receives 403; admin bypasses section scope; publishing already-published item returns 409 | R-15, R-20 | authored |
| `Domain/Refunds/RefundApprovalTest.php` | staff approves pending refund and ledger reversal is written; staff rejects refund with reason; student cannot approve refund; refund exceeding ceiling returns 422; reconciliation flag resolved after approval | R-34, R-35, R-36 | authored |
| `Domain/Threads/SensitiveWordRewriteTest.php` | POST with blocked term returns 422 with term positions; clean body returns 201; GET /admin/sensitive-words/check returns blocked=true with matches; admin can create and delete sensitive word rule | R-11 | authored |
| `DiagnosticsTest.php` | admin triggers export → 201 completed status; non-admin is rejected 403; unauthenticated → 401; list returns paginated exports; export creates audit log entry | R-43 | authored |
| `BackupTest.php` | admin lists backup jobs; non-admin is rejected 403; admin triggers backup dispatches job; non-admin trigger → 403; admin views specific job; pruned backups visible in history; trigger creates audit log entry | R-47 | authored |
| `DrillTest.php` | admin lists drill records; non-admin → 403; admin records passed drill; admin records partial drill; missing fields → 422; invalid outcome → 422; creates audit log entry; unauthenticated → 401 | R-48 | authored |
| `AdminSettingsTest.php` | admin reads settings; non-admin → 403; admin updates edit_window_minutes; admin updates multiple settings; unknown keys ignored; invalid type → 422; non-admin update → 403; update creates audit log; admin reads audit log; audit log filterable by action; non-admin audit log → 403 | R-49 | authored |
| `Domain/Notifications/PreferencesTest.php` | GET /notifications/preferences returns user preferences; PATCH updates category settings; unauthenticated → 401 | R-12, R-13 | authored |
| `Domain/Orders/TimelineTest.php` | owner retrieves order timeline events; another user returns 403 | R-22 | authored |
| `Domain/Billing/PenaltyTest.php` | artisan penalty applies to past-due bill; idempotent on same bill same day (PenaltyJob count=1); no penalty within grace period | R-32 | authored |
| `Domain/Billing/RecurringSchedulerTest.php` | artisan recurring generates bills on configured day; skips on non-configured day | R-31 | authored |
| `Domain/Ledger/AdminIndexTest.php` | admin lists ledger entries with pagination; admin filters by user_id; non-admin returns 403 | R-38 | authored |
| `Domain/Reconciliation/CrudTest.php` | admin lists open reconciliation flags; admin resolves a flag; non-admin returns 403 | R-35, R-36 | authored |
| `Domain/Receipts/ShowAndPrintTest.php` | GET receipt for completed order; GET print receipt; 404 for unpaid order; 403 for cross-user receipt show; 403 for cross-user receipt print | R-23, R-24 | authored |
| `Domain/Catalog/CrudTest.php` | admin creates catalog item; GET /catalog returns only active items; non-admin create returns 403 | R-37 | authored |
| `Domain/FeeCategory/CrudTest.php` | admin creates fee category; admin updates fee category | R-37 | authored |
| `Domain/Terms/ReadTest.php` | GET /terms returns paginated list; GET /terms/{id} returns single term | R-19, R-21 | authored |
| `Domain/Sections/ReadTest.php` | GET /sections/{id} returns section; GET /sections/{id}/roster returns roster | R-19, R-21 | authored |
| `Domain/Mentions/IndexTest.php` | GET /mentions returns current user's mentions; unauthenticated returns 401 | R-06 | authored |
| `Domain/Threads/BindingIntegrityTest.php` | GET/PATCH/DELETE post with mismatched thread returns 404; PATCH/DELETE comment with mismatched post returns 404 | R-03, R-04 | authored |
| `Domain/Threads/CrossScopeWriteTest.php` | student not enrolled in thread's section cannot create post; enrolled student can post; unrelated student cannot comment on a post; non-enrolled student cannot index thread posts | R-02, R-03, R-18, R-20 | authored |
| `Domain/Threads/UpdateAuthorizationTest.php` | teacher scoped to different course cannot update thread; unrelated student cannot update someone else's thread; teacher in same course can moderate thread update | R-02, R-18, R-20 | authored |
| `Domain/Courses/ReadTest.php` | admin sees any course; enrolled student sees their course; unrelated student returns 403; teacher on course A cannot view course B; unauthenticated returns 401 | R-19, R-20 | authored |
| `Domain/Payment/InitiatePaymentMethodTest.php` | POST /orders/{id}/payment accepts each PaymentMethod enum value; rejects bank_transfer/card with 422 | R-22, R-23 | authored |
| `BackupScheduledCommandTest.php` | scheduled backup command creates BackupJob and transitions to Completed; prunes expired backups and deletes files; decrypt command round-trips the backup file | R-47 | authored |

---

## Frontend Unit Tests (`repo/frontend/unit_tests/`)

| Test File | Test Name | Req Coverage | Status |
|---|---|---|---|
| `bootstrap.test.ts` | creates a Vue application instance without error | — | authored |
| `bootstrap.test.ts` | mounts with Pinia and Router installed | — | authored |
| `auth/authStore.test.ts` | starts unauthenticated; setSession persists to localStorage; isSessionExpired when past expiry; clearSession removes all; role helpers reflect user roles; isAdmin; initSession returns false on expired | R-27, R-28, R-29 | authored |
| `router/guard.test.ts` | unauthenticated redirect to login; authenticated access guarded route; authenticated redirect from login to home; expired session clears and redirects | R-27 | authored |
| `adapters/threads.test.ts` | listThreads calls GET /courses/:id/threads; createThread calls POST; createPost calls POST /threads/:id/posts; updatePost calls PATCH | R-02, R-03, R-04 | authored |
| `adapters/notifications.test.ts` | list calls GET /notifications; unreadCount calls GET /notifications/unread-count; markRead calls POST /notifications/mark-read; getPreferences calls GET /notifications/preferences | R-12, R-13 | authored |
| `adapters/orders.test.ts` | create calls POST /orders; initiatePayment passes Idempotency-Key header; completePayment passes Idempotency-Key; getReceipt calls GET receipt endpoint | R-22, R-23, R-33 | authored |
| `adapters/bills.test.ts` | mine calls GET /bills; adminGenerate calls POST /admin/bills/generate with idempotency key; createRefund calls POST /bills/:id/refunds with idempotency key; reasonCodes calls GET /refund-reason-codes | R-30, R-34, R-33 | authored |
| `adapters/http.test.ts` | generateIdempotencyKey produces unique values; normalizeError maps SENSITIVE_WORDS_BLOCKED with terms; normalizeError maps generic API error; withRetry retries on 503; withRetry does not retry on 422 | R-33, R-45 | authored |
| `offline/backoff.test.ts` | computeBackoffDelay is non-negative; delay is bounded by maxMs; delay grows with attempts; shouldRetry true for 503; shouldRetry true for 429; shouldRetry false for 422; shouldRetry false for 403 | R-44, R-45 | authored |
| `stores/notifications.test.ts` | fetchNotifications populates list on success; fetchNotifications sets error on failure; totalUnread sums all category counts; markRead calls adapter and clears selection; updatePreferences calls adapter | R-12, R-13 | authored |
| `stores/dashboard.test.ts` | fetchDashboard populates summary on success; fetchDashboard sets error on failure; reset clears summary and error | R-01 | authored |
| `stores/offline.test.ts` | setReadOnly(true) sets isReadOnly; enqueueAction adds to pendingActions; removeAction removes from pendingActions; pendingCount reflects queue length | R-44 | authored |
| `composables/useMask.test.ts` | maskEmail hides local part with reveal=false; maskEmail returns full address with reveal=true; maskName hides surname; maskAmount shows bullets; formatCents formats currency string | R-38 | authored |
| `composables/usePermission.test.ts` | can(role) true when user has matching role; can(role, scopeType, scopeId) true when scope matches; canManageSection true for admin; canManageSection true for teacher with matching section scope; canModerate true for admin and registrar | R-18, R-19 | authored |
| `components/ReadOnlyBanner.test.ts` | renders when isReadOnly is true; has role=alert; has aria-live=assertive | R-42 | authored |
| `components/navigation.test.ts` | shows admin links for administrator role; shows grade-items link for teacher; shows roster link for registrar; hides admin links for student | R-01, R-18 | authored |
| `views/dashboard.test.ts` | StudentDashboard renders enrollment and bill counts; renders loading spinner while fetching; renders error alert on fetch failure; circuit-open banner shown when isReadOnly is true | R-01, R-42 | authored |
| `views/discussions.test.ts` | CreateThreadModal renders title and body fields; shows blocked-terms alert when sensitive words detected; submit button is disabled when blocked terms exist; calls threadsAdapter.create on valid submit; canEdit is false when post is older than 15 minutes; canEdit button is visible within 15-minute window | R-02, R-08, R-11 | authored |
| `views/notifications.test.ts` | renders Notification Center heading; renders empty state when no notifications; renders notification items; bulk mark-read button is disabled when nothing selected | R-12 | authored |
| `views/orders.test.ts` | StatusChip renders correct variant for each status; completePayment sets conflict on IDEMPOTENCY_KEY_CONFLICT; completePayment sets conflict on INVALID_STATE_TRANSITION; initiatePayment returns attempt and idempotencyKey on success | R-22, R-23, R-33 | authored |
| `views/billing.test.ts` | requestRefund sets conflict on REFUND_EXCEEDS_BALANCE; requestRefund sets conflict on IDEMPOTENCY_KEY_CONFLICT; generateBill sets conflict on IDEMPOTENCY_KEY_CONFLICT | R-30, R-34, R-33 | authored |
| `views/admin.test.ts` | ModerationQueueView renders Queue is empty when no threads; shows Hide and Lock buttons for visible threads; HealthView shows circuit-open alert when circuit breaker is open; shows error rate above threshold warning | R-10, R-40, R-41, R-42 | authored |
| `views/DiagnosticsAdmin.test.ts` | shows "No exports found" when list is empty; displays export history rows when exports exist; shows "Generate Diagnostic Export" button | R-43 | authored |
| `views/BackupAdmin.test.ts` | shows "No backups found" when empty; displays backup rows with status and retention date; shows trigger button; displays 30-day retention note | R-47 | authored |
| `views/DRAdmin.test.ts` | shows "No drills recorded yet" when empty; renders drill history with outcome; shows Record Drill form; shows quarterly drill reminder note | R-48 | authored |
| `views/AdminSettings.test.ts` | renders settings form fields; shows audit log section heading; renders audit log entries; shows "No entries found" when empty | R-49 | authored |
| `adapters/appointments.test.ts` | list calls GET /appointments; create calls POST /appointments; cancel calls DELETE /appointments/{id} | R-16 | authored |
| `adapters/gradeItems.test.ts` | list calls GET /sections/{id}/grade-items; create calls POST; publish calls POST /publish | R-15, R-20 | authored |
| `adapters/roster.test.ts` | import calls POST /terms/{id}/roster-imports with FormData; history calls GET /terms/{id}/roster-imports | R-21 | authored |
| `adapters/moderation.test.ts` | queue calls GET /admin/moderation/queue; hideThread calls POST hide; lockThread calls POST lock | R-10 | authored |
| `adapters/catalog.test.ts` | list calls GET /catalog; create calls POST /admin/catalog | R-37 | authored |
| `composables/useEditWindow.test.ts` | isEditable returns true within 15-min window; false after window; secondsLeft decrements correctly | R-08 | authored |
| `composables/useMoney.test.ts` | formatCents renders 1000 as "10.00"; 0 as "0.00"; 10050 as "100.50" | R-38 | authored |
| `views/academic.test.ts` | GradeItemsView renders empty state; shows Publish button for draft items; RosterImportView renders file input; shows error table when import has errors | R-15, R-20, R-21 | authored |

---

## Frontend E2E Tests (`repo/frontend/e2e/`)

| Test File | Test Name | Req Coverage | Status |
|---|---|---|---|
| `placeholder.spec.ts` | home page loads with correct title | R-01 | authored |
| `placeholder.spec.ts` | unauthenticated user is redirected to login | R-27 | authored |
| `discussion-flow.spec.ts` | student can view thread list; student can create post; sensitive-word blocks submission; edit button disappears after 15 minutes; moderator can hide a post from moderation queue | R-02, R-03, R-08, R-10, R-11 | authored |
| `notification-center.spec.ts` | unread badge shows correct count; mark-read removes unread indicator; bulk select and mark-read; preference toggle disables category | R-12, R-13 | authored |
| `order-billing-flow.spec.ts` | student adds catalog item to order; operator initiates payment; operator completes payment; receipt is displayed; refund request appears in admin reconciliation | R-22, R-23, R-24, R-34, R-36 | authored |
| `auth-flow.spec.ts` | unauthenticated user redirected to login; successful login redirects to home; failed login shows error; already-authenticated user redirected from /login to home | R-27 | authored |
| `admin-screens.spec.ts` | health view loads with status indicators; diagnostics view trigger button present; backup view history table present; DR view drill form present | R-40, R-43, R-47, R-48 | authored |

---

## Coverage Summary by Requirement

| Req ID | Requirement | Tests Authored | Tests Needed |
|---|---|---|---|
| R-01 | Role-aware home dashboards | 1 E2E (placeholder) | Full dashboard unit + API tests |
| R-08 | Edit window | 1 unit (`EditWindowPolicyTest`) | Full PATCH /posts API coverage |
| R-10 | Moderation state machine | 1 unit (`ModerationStateMachineTest`) | Moderation controller tests |
| R-11 | Sensitive-word filter | 1 unit (`SensitiveWordFilterTest`) | Integration path through post creation |
| R-18, R-19 | Multi-role + scoped permissions | 1 unit (`ScopeResolutionServiceTest`) + 1 API (`ScopeEnforcementTest`) | Per-resource policy controller tests |
| R-22, R-25, R-26 | Order state machine | 1 unit (`OrderStateMachineTest`) | Payment/auto-close controller tests |
| R-27 | Session tokens + auth redirect | 3 API tests + 2 frontend unit tests + 1 E2E | Full auth flow coverage |
| R-28 | Password rule | 1 unit (`PasswordRuleTest`) + 1 service test (`AuthServiceTest`) + 1 API test | Password change controller |
| R-29 | Login throttle | 1 unit (`LoginThrottlePolicyTest`) + 1 service test (`AuthServiceTest`) + 1 API test | — |
| R-31 | Recurring billing schedule | 1 unit (`BillScheduleCalculatorTest`) | Scheduler integration |
| R-32 | Penalty 5% after 10 days | 1 unit (`PenaltyCalculatorTest`) | Scheduler integration |
| R-33 | Idempotent completion | 1 unit + 2 contract tests | Per-endpoint integration |
| R-34, R-35 | Partial refunds + reversal | 1 unit (`RefundPolicyTest`) | Refund controller tests |
| R-37 | Configurable tax per category | 1 unit (`TaxRuleCalculatorTest`) | Admin tax controller tests |
| R-38 | Money in cents | 1 unit (`MoneyTest`) | Display/formatting tests |
| R-39 | Correlation IDs | 1 contract test | Integration across all routes |
| R-40 | Health dashboards / metrics | 2 API tests + 1 unit (`MetricsAggregatorTest`) | Live circuit/metrics dashboard |
| R-41 | Alert thresholds (>2%/5min) | 1 unit (`CircuitBreakerPolicyTest`) + 1 unit (`MetricsAggregatorTest`) | — |
| R-42 | Circuit breaker + read-only | 1 unit (`CircuitBreakerPolicyTest`) + `EnforceReadOnlyModeMiddleware` + `CircuitBreakerService` | Mutation-guard integration test |
| R-43 | Diagnostic export (encrypted) | `DiagnosticsTest.php` (API) + `DiagnosticsAdmin.test.ts` (FE) | — |
| All envelope shapes | Global error rendering | `ErrorEnvelopeTest` + `LoginTest` codes | Per-domain error cases |
| R-02 | Announcements + threaded posts | `CreateThreadTest` (API) + `ContentSubmissionServiceTest` (unit) + `threads.test.ts` (FE) | — |
| R-03 | Threaded discussions | `CreateThreadTest` (API) + `threads.test.ts` (FE) | — |
| R-04 | Comments | `ContentSubmissionServiceTest` (unit) + `threads.test.ts` (FE) | — |
| R-05 | Q&A mode | `CreateThreadTest` (qa_enabled path) | — |
| R-06 | @mentions | `MentionParserTest` (unit) + `CreateThreadTest` (mention dispatch path) | — |
| R-09 | Reporting | `ContentSubmissionServiceTest` (unit) | Full integration coverage |
| R-10 | Moderation | `ModerationServiceTest` (unit) + `ModerationStateMachineTest` (unit) | Full controller coverage |
| R-11 | Sensitive-word filter | `CreateThreadTest` (sensitive path) | — |
| R-12, R-13 | Notification Center | `IndexAndUnreadCountTest` (API) + `NotificationOrchestratorTest` (unit) + `notifications.test.ts` (FE) | — |
| R-14 | Enrollment alerts | `ApproveAndDenyTest` (API) | — |
| R-15 | Grade alerts | `CrudAndPublishTest` (API) | — |
| R-16 | Appointment alerts | `CrudAndChangeNotificationTest` (API) | — |
| R-17 | Billing alerts | `AdminGenerateBillTest` (API) | — |
| R-20 | Teacher scope | `CreateThreadTest` (scope-rejection path) | — |
| R-21 | Roster import | `ImportTest` (API) + `RosterImportServiceTest` (unit) | — |
| R-22, R-25, R-26 | Order timeline + auto-close | `CreateOrderTest` + `AutoCloseTest` (API) + `OrderServiceTest` + `OrderAutoCloseJobTest` (unit) | — |
| R-23, R-24 | Payment + receipts | `CompleteTest` (API) | — |
| R-30 | Bill generation | `AdminGenerateBillTest` (API) + `BillingServiceTest` (unit) + `bills.test.ts` (FE) | — |
| R-31 | Recurring billing | `RecurringBillingJobTest` (unit) | Scheduler integration |
| R-32 | Penalty | `PenaltyJobTest` (unit) + `PenaltyCalculatorTest` (unit) | — |
| R-34, R-35, R-36 | Refunds + reversal + reconciliation | `CreateAndReversalTest` (API) + `RefundServiceTest` (unit) | — |
| R-37 | Tax per category | `AdminGenerateBillTest` (API) + `TaxRuleCalculatorTest` (unit) | — |
| R-46 | Queue-backed notifications | `SendNotificationJobTest` + `NotificationOrchestratorTest` (unit) | — |
| R-50 | Appointments | `CrudAndChangeNotificationTest` (API) | — |
| R-44 | Client cache, retries, backups, DR, admin settings | `stores/offline.test.ts`, `offline/backoff.test.ts` | — |
| R-45 | Exponential backoff retries | `adapters/http.test.ts` (withRetry logic) | — |
| R-47 | Nightly encrypted backups, 30-day retention | `BackupTest.php` (API) + `BackupAdmin.test.ts` (FE) | — |
| R-48 | DR drill records, restore runbook | `DrillTest.php` (API) + `DRAdmin.test.ts` (FE) | — |
| R-49 | Admin settings, audit log | `AdminSettingsTest.php` (API) + `AdminSettings.test.ts` (FE) | — |

---

*Prompt 8 hardening additions: 2 new backend unit test files (PaymentServiceTest, AlertThresholdEvaluationJobTest); 11 new backend API test files (Notifications/PreferencesTest, Orders/TimelineTest, Billing/PenaltyTest, Billing/RecurringSchedulerTest, Ledger/AdminIndexTest, Reconciliation/CrudTest, Receipts/ShowAndPrintTest, Catalog/CrudTest, FeeCategory/CrudTest, Terms/ReadTest, Sections/ReadTest, Mentions/IndexTest); 7 new frontend adapter tests (appointments, gradeItems, roster, moderation, catalog); 2 new composable tests (useEditWindow, useMoney); 1 new view test (academic); 2 new E2E specs (auth-flow, admin-screens). All tests are authored only — execution is deferred per project rules.*
