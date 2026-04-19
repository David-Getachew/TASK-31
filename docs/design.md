# CampusLearn — System Design

## 1. Platform Classification

CampusLearn is a **full-stack offline LAN web platform** for district-operated learning programs. It runs entirely on a district-controlled local network with no dependency on internet services, cloud storage, external payment processors, SaaS messaging vendors, or hosted identity providers. All data, media, queues, logs, backups, and payments are handled within the district's physical infrastructure.

---

## 2. System Topology

```
Browser (Vue 3 SPA)
    │  HTTP/JSON over LAN
    ▼
Backend API (Laravel 13, PHP 8.3)  :8000
    │
    ├── MySQL 8.4                   :3306   (primary data store)
    ├── Database Queue              (Laravel queue driver: database)
    ├── Laravel Scheduler           (cron: recurring billing, auto-close, backups)
    ├── Encrypted Backup Volume     (local filesystem, nightly job)
    └── Encrypted Diagnostic Store  (local filesystem, on-demand export)
```

**Service ports (all LAN-local):**

| Service | Port | Notes |
|---|---|---|
| Frontend (Vite dev / nginx prod) | 5173 / 80 | Vue 3 SPA |
| Backend API | 8000 | Laravel, PHP-FPM |
| MySQL | 3306 | Primary data store |

No external network egress is permitted or required.

---

## 3. Role Model

| Role | Abbrev | Description |
|---|---|---|
| Student | STU | Enrolled learner; reads courses, posts in discussions, views own billing |
| Teacher | TCH | Delivers instruction; manages announcements and discussions for assigned sections |
| Registrar | REG | Manages enrollment, roster imports, grade publication within assigned terms |
| Administrator | ADM | Full system access; manages users, roles, settings, moderation, billing, backups |

### Multi-Role Accounts
One account may hold multiple role assignments simultaneously. Permissions are resolved as the **union of all active scoped grants** for the account. Administrator-only operations require an explicit admin-role grant; they are not implied by the union of other roles.

---

## 4. Scope Model

Permissions are scoped at four granularity levels:

```
Term
 └── Course
      └── Class Section
           └── Grade Item
```

A **scope grant** binds a role assignment to one or more nodes in this hierarchy. A teacher's `publish` grant on Section A does not extend to Section B even within the same course. A registrar's roster-import grant is bound to a specific term. Grade-item visibility grants control which items a teacher may publish or a student may view.

---

## 5. Discussion / Moderation Architecture

### Content Model
A single **Thread** entity covers both announcements and discussion boards, distinguished by `thread_type` (`announcement` | `discussion`). Each thread contains **Posts** (top-level and replies) and **Comments**. Posts and comments can carry `@mention` references that trigger notifications.

### Edit Window
Authors may self-edit posts and comments within **15 minutes** of creation. This is enforced server-side by comparing `created_at` to the current timestamp in the domain policy. Privileged moderation and administrative edits are allowed outside the window and are recorded in the audit log.

### Sensitive-Word Filter Pipeline
```
Client submit → API receive → Normalize text → Match against SensitiveWordRule set
    ↓ match found                          ↓ no match
Return 422 with matched                 Persist post/comment
term positions + ranges                 (visible / pending state)
(content NOT stored)
```

The sensitive-word rule set is admin-managed. Matching is case-insensitive against normalized text. The rejection payload includes matched terms and character ranges for UI highlighting.

### Moderation State Machine
```
visible ──[hide]──► hidden ──[restore]──► visible
   └──────────────────────[lock]──────────────► locked
```
`locked` is terminal for new replies; existing content remains readable. Moderation actions are recorded as `ModerationAction` entities (actor, action, timestamp, notes) and never overwrite prior history.

---

## 6. Notification Architecture

### Notification Center
The unified Notification Center aggregates all system notices and event-triggered alerts. Every notification carries a `category` field from the set:

| Category | Trigger Examples |
|---|---|
| `announcements` | New thread in subscribed course |
| `mentions` | `@mention` in a post or comment |
| `billing` | New bill, payment confirmation, refund approval |
| `system` | Account lockout, system health alert, DR event |

### Delivery
Notifications are fanned out via the local **database queue** (Laravel Queue, `database` driver). No external messaging services. Notification records are persisted in MySQL with `read_at` timestamps. The Notification Center exposes unread counts per category, bulk mark-as-read, and per-user category subscription preferences.

---

## 7. Billing, Ledger, Refund, and Reconciliation

### Charge Types
| Type | Trigger |
|---|---|
| Initial | Enrollment/service activation |
| Recurring | Scheduler: 1st of month at 02:00 AM |
| Supplemental | Manual admin/registrar posting |
| Penalty | 5% of outstanding balance, 10 days past due |

### Monetary Rules
- All amounts stored as **integer cents** in MySQL.
- Displayed as two-decimal strings in the UI.
- Tax computed as **additive** on top of the pre-tax line amount.
- Tax rules are effective-dated per fee category; the applied tax-rule snapshot is frozen on each charge.
- Rounding: half-up at the cent level per line item.

### Ledger
Every financial event (charge, payment, refund, reversal, penalty) creates an immutable **LedgerEntry**. Entries are never updated; corrections are made by reversal entries.

### Refunds
- Partial and full refunds supported.
- Refundable ceiling = paid amount − already-refunded amount.
- A `RefundReasonCode` is mandatory.
- Approval recorded in audit log.
- Every approved refund generates: a reversal LedgerEntry + a ReconciliationFlag for end-of-day closeout.

### Idempotency
Payment completion endpoints accept an `Idempotency-Key` header. The server stores the key with the result and returns the cached result on replay, preventing double-posting when staff retries a transaction.

### Billing Schedule
- `BillSchedule` records drive recurring generation.
- Schedule stops when: end date reached, manually closed, or source enrollment/service terminated.

---

## 8. Order / Payment Workflow

### Order Status Lifecycle
```
pending_payment → paid → redeemed
              └→ canceled
              └→ refunded (from paid)
```

### Payment Execution
All payments are completed at a **staffed office** or **kiosk** terminal inside the district network. No external processor calls are made. The workflow:
1. Staff/kiosk initiates a `PaymentAttempt` with local method metadata and an idempotency key.
2. Backend validates and marks the order `paid`, generates a `Receipt`.
3. Receipt is printable from the UI.

### Auto-Close
A scheduler job runs every 5 minutes to close `pending_payment` orders that have been open for **30 minutes** without completion.

---

## 9. Observability, Health, and Read-Only Fallback

### Structured Logging
Every request is tagged with a `correlation_id` (UUID). Logs are emitted as structured JSON to `storage/logs/`. Log entries never contain secrets, raw payment payloads, or raw sensitive student data.

### Health Endpoints
`GET /api/health` returns database connectivity, queue depth, and scheduler heartbeat status. Consumed by the admin health dashboard.

### Alert Thresholds
- Error rate >2% sustained for 5 minutes → alert status recorded; admin notified via system notification.

### Circuit Breaker
When the backend detects degraded database connectivity beyond threshold, it sets a `circuit_open` flag. The API returns `503` with a `"mode":"read_only"` indicator. The Vue client:
1. Reads the flag from the health endpoint.
2. Serves cached IndexedDB data for dashboards, course/discussion reads, billing history.
3. Disables and clearly labels write actions as unavailable.
4. Displays a visible staleness banner with the cached timestamp.

### Diagnostic Export
On-demand encrypted export of diagnostic log data to a local file. Encryption key is provided via environment variable (`DIAGNOSTIC_ENCRYPTION_KEY`). The export record (path, timestamp, size) is stored in MySQL.

---

## 10. High Availability and Disaster Recovery

### Client Resilience
- IndexedDB cache stores recent dashboards, courses, notifications, billing history.
- All API calls use exponential backoff retry (3 attempts: 1s, 2s, 4s) before surfacing an error.
- Sensitive write actions queue to IndexedDB with idempotency keys when offline; dispatched on reconnect with user confirmation.

### Backend Queue
The `database` queue driver is used for notification fan-out, billing job dispatch, and backup jobs. Jobs have `tries = 3` and exponential retry delays.

### Backups
- Laravel scheduler runs a nightly encrypted backup job at 04:00 AM.
- Backups are AES-encrypted using `BACKUP_ENCRYPTION_KEY` from environment.
- Retained for **30 days**; older backups are pruned automatically.
- Backup metadata (filename, size, SHA-256 checksum, created_at) stored in MySQL.

### Restore Runbook
See `docs/restore-runbook.md` for step-by-step restore and DR drill procedures. DR drill records (date, operator, outcome, notes) are tracked in the system and visible in the admin UI.

---

## 11. Appointments

Appointments are **scheduled service/reservation records** used primarily for facility-rental time slots and staff-managed interactions (e.g., registrar meetings). They carry `status`, `scheduled_start`, `scheduled_end`, a linked user, and a service/facility context. State changes (create, reschedule, cancel) trigger `appointment_change` notifications to the linked user.

---

## 12. Requirement-to-Module Traceability

| Requirement | Backend Module | Frontend Area |
|---|---|---|
| Role-aware home dashboards | `DashboardService` | `views/dashboard/` |
| Course announcements | `ThreadService`, `AnnouncementController` | `views/announcements/` |
| Threaded discussions + comments + Q&A + @mentions | `ThreadService`, `PostService`, `MentionService` | `views/discussions/` |
| 15-minute student edit window | `EditWindowPolicy` (domain) | Post/comment edit UI |
| Post/edit/report feedback | `ReportService` | Inline report controls |
| Moderation hide/restore/lock | `ModerationService`, `ModerationController` | `views/moderation/` |
| Sensitive-word filtering (submit-time, highlight) | `SensitiveWordFilter` (domain) | Submit form, highlight overlay |
| Notification Center (unread, bulk read, subscriptions) | `NotificationService`, `NotificationController` | `views/notifications/` |
| Enrollment outcome alerts | `EnrollmentNotifier` | Notification Center |
| Grade publication alerts | `GradeNotifier` | Notification Center |
| Appointment change alerts | `AppointmentNotifier` | Notification Center |
| Billing outcome alerts | `BillingNotifier` | Notification Center |
| Multi-role accounts + scoped grants | `RoleService`, `ScopeGuard`, Policies | Role/permissions UI |
| Registrar roster import | `RosterImportService`, `RosterController` | `views/roster/` |
| Fee-based service ordering | `OrderService`, `OrderController` | `views/orders/` |
| Order timeline (statuses) | `OrderStateMachine` | Order detail view |
| Staffed office / kiosk payment workflow | `PaymentService`, `PaymentController` | `views/payment/` |
| Printable receipts | `ReceiptService` | Receipt print view |
| 30-minute auto-close for incomplete payments | `OrderAutoCloseJob` (scheduler) | Order status indicators |
| Short-lived session tokens + local storage | Laravel Sanctum | Auth store (Pinia) |
| Password rules (≥10 chars, bcrypt) | `AuthService`, `LoginRequest` | Login/register forms |
| 15-minute account lockout after 5 failures | `LoginThrottleService` | Login error UI |
| Bill generation (initial, recurring, supplemental, penalty) | `BillingEngine` | `views/billing/` |
| Recurring billing (1st of month 02:00 AM) | `RecurringBillingJob` (scheduler) | — |
| Penalty charges (5% after 10 days) | `PenaltyJob` (scheduler) | Billing statement |
| Idempotent payment completion | `IdempotencyService` | Payment completion UI |
| Partial refunds + reason codes | `RefundService`, `RefundController` | `views/refunds/` |
| Reversal ledger entries + reconciliation flags | `LedgerService`, `ReconciliationService` | Admin reconciliation view |
| Configurable tax rules per fee category | `TaxRuleService` | Admin settings |
| Structured logs + correlation IDs | `CorrelationMiddleware`, Laravel logging | — |
| Request metrics + health dashboards | `HealthController`, `MetricsService` | `views/admin/health/` |
| Alert thresholds (error rate >2%) | `AlertThresholdMonitor` | Admin health dashboard |
| Circuit breaking + read-only fallback | `CircuitBreakerService` | Offline/read-only banner |
| Diagnostic export (encrypted local file) | `DiagnosticExportService` | Admin diagnostics view |
| Poor-network client cache | IndexedDB adapter | Offline cache composable |
| Exponential backoff retries | HTTP adapter (Axios) | Retry interceptor |
| Local message queue for notification fan-out | Laravel Queue (`database`) | — |
| Nightly encrypted backups (30-day retention) | `BackupService`, `BackupJob` (scheduler) | Admin backup view |
| Restore runbook + DR drill records | `DrillRecordService` | Admin DR view |
| Admin settings (sensitive-word rules, tax, roles) | `AdminController`, various admin services | `views/admin/` |

---

## 13. Sequence Descriptions (Plain Text)

These describe the canonical request flows established in the domain layer. All invariants are enforced by pure-logic domain services living under `repo/backend/src/CampusLearn/`.

### 13.1 Thread/Discussion Create → Sensitive-Word Filter → Moderation State
1. Authenticated author POSTs `/api/v1/threads` (or `/threads/{id}/posts`).
2. Form request validates structural fields (title, body length bounds, thread_type).
3. `SensitiveWordFilter::inspect($body, $rules)` runs against `sensitive_word_rules` where `is_active = true`.
4. If `FilterResult::isBlocked()` is true, handler throws `SensitiveWordMatched` with match ranges; global exception renderer returns `422 SENSITIVE_WORDS_BLOCKED` envelope so the UI can highlight offending spans.
5. On success, row is inserted with `state = visible`, `created_at = now`, `edited_at = null`; `audit_log_entries` row appended with `action = thread.created`.
6. Author may later PATCH within 15 minutes; `EditWindowPolicy::canAuthorEdit` decides. Expired windows throw `EditWindowExpired` rendered as `423 EDIT_WINDOW_EXPIRED`.
7. Moderator-driven transitions flow through `ModerationStateMachine` (`visible → hidden/locked`, `hidden → visible/locked`, `locked → visible`). Each mutation writes `moderation_actions` and the entity's `state` atomically.

### 13.2 Order Create → Pending Payment → Complete / Auto-Close
1. Authenticated student POSTs `/api/v1/orders` with catalog_item selections.
2. Service snapshots `catalog_items.unit_price_cents` and applicable `tax_rules` into `order_lines.tax_rule_snapshot`, computes `subtotal_cents`, `tax_cents`, `total_cents` via `TaxRuleCalculator`.
3. Order stored with `status = pending_payment`, `auto_close_at = now + 30 min`, `order_timeline_events` row appended (`event = created`).
4. Cashier/kiosk issues `POST /api/v1/orders/{id}/payments/complete` with `Idempotency-Key`. Middleware delegates to `IdempotencyService`; identical replays return cached `X-Idempotent-Replay: true`; differing payloads 409.
5. `PaymentSettlementPolicy::evaluate` checks order is `pending_payment` and attempt amount matches total exactly. `OrderStateMachine::transition(PendingPayment, Paid)` produces `Paid`; ledger `payment` row + `order_timeline_events` row written; `Receipt` row generated.
6. If payment never arrives, the `OrderAutoCloseJob` scheduler (Prompt 3+) scans `orders.status = pending_payment AND auto_close_at <= now`; each match transitions via `OrderStateMachine::transition(PendingPayment, AutoClosed)` → `Canceled`.

### 13.3 Bill Generation → Penalty Accrual → Refund → Reconciliation → Closeout
1. `RecurringBillingJob` (Prompt 3+) fires at 02:00 on the 1st. For each `bill_schedules` row with `status = active AND next_run_on <= today`, a `Bill` is created (subtotal, tax, total cents; `status = open`; `due_on = issued_on + N days`). `BillScheduleCalculator::nextRunAt` computes the following `next_run_on`.
2. `PenaltyJob` (Prompt 3+) scans `bills WHERE status IN ('open','partial') AND (today - due_on) >= 10`; for each, `PenaltyCalculator::compute(outstanding, daysPastDue)` returns `500 bps` of outstanding. A `penalty_jobs` row with unique `idempotency_key` is inserted, then a penalty `Bill` is created + linked via `ledger_entries` (`entry_type = penalty`). Uniqueness of `idempotency_key` prevents double-application.
3. Operator initiates refund via `POST /api/v1/refunds` with `reason_code_id` and `amount_cents`. `RefundPolicy::evaluate(billPaid, billRefunded, requested, reasonCode)` either allows or returns `EXCEEDS_REFUNDABLE_BALANCE`/`REASON_CODE_REQUIRED`.
4. On approval, `ledger_entries` receives a `reversal` row pointing at the original `payment` entry via `reference_entry_id`; `refunds.reversal_ledger_entry_id` captures the link. `reconciliation_flags` row opens (`source_type = refund`).
5. End-of-day closeout resolves `reconciliation_flags.status = open` by manual or automated reconciliation; the resolving user id and `resolved_at` are recorded.

### 13.4 Notification Fan-Out → Unread State → Bulk Mark Read
1. Domain events (`enrollment.created`, `grade.published`, `appointment.rescheduled`, `bill.issued`, `bill.paid`, `order.paid`, `mention.created`) enqueue a job that invokes `NotificationDispatcher::dispatch` with the resolved recipient list.
2. Dispatcher calls the bound `NotificationWriter`, which inserts one `notifications` row per recipient (`read_at = null`), honoring `notification_subscriptions.enabled` where applicable.
3. `UnreadCounter::summarize(userId)` returns `{total, by_category}` for the Notification Center badge.
4. `POST /api/v1/notifications/read` accepts a list of ids or a `category`; the handler sets `read_at = now` in one statement and returns the updated unread summary.

### 13.5 Poor-Network Cache Refresh → Circuit-Breaker Trip → Read-Only Fallback
1. Frontend Axios interceptor issues requests; on retriable failures it backs off exponentially. `IndexedDB` adapter serves last-known-good payload if the request continues to fail.
2. Backend middleware records `request_metrics` (correlation id, route, status, duration). A scheduler job (Prompt 3+) computes the 5-minute `ErrorRateWindow` and invokes `CircuitBreakerPolicy::evaluate`.
3. When the policy returns `ReadOnly`, `circuit_breaker_state` singleton is updated with `mode = read_only`, `tripped_at`, `tripped_reason`. API middleware (Prompt 3+) refuses mutations with 503 `SERVICE_UNAVAILABLE` until reset; read endpoints stay online.
4. Frontend watches `GET /api/v1/health/circuit`; if `mode = read_only`, a persistent banner informs users and mutation controls disable. When the error rate falls below the reset threshold, the policy returns to `ReadWrite`.

### 13.6 Nightly Encrypted Backup + On-Demand Diagnostic Export
1. Scheduler creates a `backup_jobs` row (`status = pending`, `retention_expires_on = today + 30 days`).
2. The worker dumps the MySQL schema+data to a local file, encrypts with `BACKUP_ENCRYPTION_KEY` (AES-256-GCM), writes the ciphertext, and records `file_path`, `file_size_bytes`, `checksum_sha256`, `status = completed`, `completed_at`.
3. A prune pass moves files whose `retention_expires_on < today` to `status = pruned` and deletes the on-disk file.
4. Admin triggers a diagnostic export via `POST /api/v1/admin/diagnostics`. A `diagnostic_exports` row is created (`status = pending`) and the same encrypt-and-record flow runs. `file_path` points at the local encrypted blob; admin downloads via a signed LAN-only URL (Prompt 3+).

---

## 14. Security and Authorization Foundations (Prompt 3)

### 14.1 Authentication Flow

1. Client POSTs `{ email, password }` to `POST /api/v1/auth/login`. `LoginRequest` validates the shape.
2. `AuthService::login` checks: (a) active `AccountLock` → throw `AccountLocked`; (b) credential validity via `Hash::check` against bcrypt hash; (c) on failure, record `FailedLoginAttempt` and invoke `LoginThrottlePolicy::shouldLock(recentCount)` — if true, create `AccountLock` record and throw `AccountLocked`; on success, delete old attempts, call `User::createToken` with `expires_at = now() + token_ttl_minutes`.
3. Response returns `{ token, expires_at, user }` inside the standard `{ data: ... }` envelope.
4. All subsequent requests carry `Authorization: Bearer <token>`; `auth:sanctum` middleware validates the token and rejects expired tokens.
5. `POST /api/v1/auth/logout` revokes the current `PersonalAccessToken` record.

### 14.2 Scoped Authorization

Role grants live in `role_assignments`. `EloquentScopeResolver` fetches active (non-revoked) grants for the authenticated user and returns `Grant[]`. `ScopeResolutionService` evaluates:
- `administrator @ global` — satisfies any capability in any context.
- `registrar @ term | global` — satisfies registrar capabilities for the matched term scope and below.
- `teacher @ section` — satisfies teacher capabilities for that section and its grade items.
- `student` — no scope capability; policies use `hasRole` for presence check.

Laravel Policies registered in `AppServiceProvider::boot`:
- `PostPolicy` — `update` (self + edit-window), `moderate` (staff), `delete` (staff).
- `GradeItemPolicy` — `create`/`update`/`publish` (teacher@section or admin), `viewScores`.
- `OrderPolicy` — `view` (owner or staff), `complete` (staff), `cancel` (owner or staff).
- `BillPolicy` — `view` (owner or staff), `void`/`refund` (finance staff).
- `RefundOperatorPolicy` — `approve`/`reject` (registrar or admin).
- `DiagnosticExportPolicy` — `create`/`download` (admin only).

### 14.3 Session Handling (Frontend)

- `auth` Pinia store holds `token`, `expiresAt` (epoch ms), and `user`.
- On app boot, `initSession()` checks expiry; if the token is still valid but `user` is null, fetches `GET /api/v1/auth/me` to hydrate the user object.
- If `isSessionExpired`, the store clears itself and the router guard redirects to `/login`.
- Axios request interceptor attaches `Authorization: Bearer <token>`; 401 response clears localStorage and redirects to `/login` via `window.location`.

### 14.4 Request Metrics and Circuit Breaker

1. `RecordRequestMetricsMiddleware` (global, appended after `CorrelationIdMiddleware`) writes one `request_metrics` row per request after the response: route name, method, status, duration_ms, user_id, correlation_id.
2. `CircuitBreakerService::evaluate()` queries the last N seconds of `request_metrics`, builds an `ErrorRateWindow`, and passes it to `CircuitBreakerPolicy::evaluate()`. On mode change it updates `circuit_breaker_state` (singleton row id=1), emits a structured log line, and writes a durable `SystemAlert` row (`kind=circuit.tripped` severity `critical` on trip; `kind=circuit.restored` severity `info` on recovery) with a JSON context snapshot of `error_rate_bps`, `total_requests`, and `window_seconds`. No duplicate alert is emitted while the mode is stable.
3. `EnforceReadOnlyModeMiddleware` (alias `read-only`) rejects non-safe HTTP verbs with 503 when `circuit_breaker_state.mode = read_only`.
4. `GET /api/v1/health/circuit` (auth required) returns the live circuit snapshot including `error_rate_bps`, `tripped_at`, and mode. `GET /api/v1/health/metrics` returns aggregated error rate, request count, and latency percentiles (p50/p95/p99).

### 14.5 Encryption Helper

`EncryptionHelper` (AES-256-GCM via PHP OpenSSL) is registered as a singleton. Encrypted blob format: `base64(iv[12] || tag[16] || ciphertext)`. Used by the backup worker and diagnostic export worker (Prompt 4+). Key is 64 hex chars (32 bytes) sourced from `campuslearn.backups.encryption_key` or `campuslearn.diagnostics.encryption_key`. Keys are environment-variable-provisioned; never hardcoded.

### 14.6 Structured Logging

Default log channel is `json` (Monolog `RotatingFileHandler` + `JsonFormatter`, 14-day rotation). All log calls automatically include `correlation_id` from the `Log::withContext` set by `CorrelationIdMiddleware`. Sensitive fields (passwords, tokens, raw idempotency keys) are never logged.

---

## 15. Business Engine Flows

### 15.1 Discussion Submission + Moderation

1. Controller validates via `CreateThreadRequest` / `CreatePostRequest` / `CreateCommentRequest`.
2. `$this->authorize` hits `ThreadPolicy` / `PostPolicy` / `CommentPolicy`.
3. `ContentSubmissionService::createThread` (or `createPost`, `createComment`) runs:
   a. Loads active `SensitiveWordRule` rows → passes to `SensitiveWordFilter::inspect`. On match throws `SensitiveWordMatched` → 422 `SENSITIVE_WORDS_BLOCKED`.
   b. `DB::transaction` persists the content row. Audit row written inside transaction.
   c. `processMentions` extracts `@handles` via `MentionParser`, resolves to user IDs, creates `mentions` rows, dispatches `discussion.mention` via `NotificationOrchestrator`.
   d. Announcement threads additionally fan out `announcement.posted` to enrolled students.
4. Edit path: author edits gated by `EditWindowPolicy::canAuthorEdit` (15 min). Moderator edits via `ModerationService::apply` which uses `ModerationStateMachine`, creates `moderation_actions` row, audits, and notifies author.

### 15.2 Order Lifecycle

1. `POST /orders` → `OrderService::create` computes tax via `TaxRuleCalculator` (active rule snapshot frozen into `order_lines.tax_rule_snapshot`), sets `auto_close_at = now + 30 min`.
2. `POST /orders/{id}/payment` (idempotent) → `PaymentService::initiate` creates `payment_attempts` row with `status=Pending`.
3. `POST /orders/{id}/payment/complete` (idempotent) → `PaymentService::complete` finalizes attempt, transitions order to `Paid`, posts `ledger_entries[type=Payment, amount=-total_cents]`, generates `Receipt` row with sequential number, audit-logs `order.paid`, notifies via `billing.paid`.
4. `OrderAutoCloseJob` (every 5 min) finds orders where `status=PendingPayment AND auto_close_at ≤ now`, transitions to `Canceled`, appends `AutoClosed` timeline event.

### 15.3 Billing Pipeline

1. **Initial**: `BillingService::generateInitialBill(user, schedule)` — creates `bills` + `bill_lines` snapshot, posts `ledger_entries[type=Charge]`, fires `billing.initial` notification.
2. **Recurring**: `RecurringBillingJob` (daily 02:00) guards with `today().day == recurring_day_of_month`, iterates `BillSchedule::activeSchedulesDueToday()`, calls `generateRecurring`, advances `next_run_on` via `BillScheduleCalculator`.
3. **Supplemental**: `BillingService::generateSupplemental` — admin-triggered, creates standalone bill.
4. **Penalty**: `PenaltyJob` (daily 03:00) iterates `Bill::pastDue()`, computes penalty via `PenaltyCalculator`, creates new `type=Penalty` bill + ledger entry. Idempotent via `penalty_jobs.idempotency_key = sha256(bill_id:run_date)`.

### 15.4 Refund + Reconciliation

1. `RefundService::request` validates ceiling via `RefundPolicy::evaluate`, creates `refunds` row `status=Pending`.
2. `RefundService::approve` transitions to `Completed`, posts `ledger_entries[type=Reversal]` + `[type=Refund]`, increments `bills.refunded_cents`, creates `reconciliation_flags` row `status=Open`.
3. `ReconciliationService::resolve` closes open flags; `summary` aggregates open/resolved counts by source type.

### 15.5 Notification Fan-out

1. Caller invokes `NotificationOrchestrator::notify(type, recipientIds, placeholders)`.
2. Loads `notification_templates` for `type`. Filters recipients by `notification_subscriptions.enabled` (default: enabled if no row).
3. Renders `title_template` / `body_template` with `strtr` placeholder substitution.
4. Chunks recipients by `fanout_batch_size` (default 50). Dispatches `SendNotificationJob` per chunk.
5. `SendNotificationJob::handle` calls `NotificationWriter::write` per recipient, writes `notification_deliveries` row. Retry-safe: up to 3 attempts; failure reason recorded in `notification_deliveries.failure_reason`.

### 15.6 Roster Import

1. `POST /terms/{term}/roster-imports` with CSV upload.
2. `RosterImportService::import` scope-checks: admin OR registrar@term.
3. Opens CSV, reads `email,name,section_code` header, iterates rows.
4. Per row: validates required fields → looks up `Section` → `User::firstOrCreate` → `Enrollment::updateOrCreate(status=Enrolled)`.
5. Errors recorded in `roster_import_errors` with codes: `file_unreadable`, `missing_field`, `section_not_found`.
6. `RosterImport` row updated with `row_count`, `success_count`, `error_count`, `status=Completed`.

### 15.7 Grade Publish

1. `GradeItemService::publish` transitions `state: Draft → Published`, sets `published_at`.
2. Fetches enrolled students from `enrollments` where `status=Enrolled` and `section_id=section`.
3. Dispatches `grade.published` notification to all enrolled students via `NotificationOrchestrator`.

### 15.8 Appointments

1. Staff creates appointment via `AppointmentService::create` (status=Scheduled).
2. Update with new `scheduled_start`/`scheduled_end` → service fires `appointment.rescheduled` notification to owner.
3. Cancel via `AppointmentService::cancel` → fires `appointment.canceled` notification.

---

## 16. Frontend Architecture (Prompt 5)

### 16.1 Application Shell

The Vue 3 SPA mounts at `src/main.ts`, wraps a Pinia store context, and renders through `src/App.vue`. Two layout components govern the authenticated/unauthenticated split:

| Layout | File | Usage |
|---|---|---|
| GuestLayout | `src/layouts/GuestLayout.vue` | Login page (no nav, no session watchers) |
| AuthLayout  | `src/layouts/AuthLayout.vue`  | All authenticated routes; includes AppNav, GlobalToast, SessionExpiredOverlay, ReadOnlyBanner |

### 16.2 Router & Guards

`src/router/index.ts` defines all routes with `meta.requiresAuth` and optional `meta.roles`. The `beforeEach` guard enforces:
1. Session-expiry detection → redirect to `/login`
2. Unauthenticated access to guarded routes → `/login`
3. Role-restricted routes (`meta.roles`) → `/unauthorized` if not in allowed roles
4. Already-authenticated users redirected away from `/login` → `/`

### 16.3 Pinia Stores

| Store | File | Responsibility |
|---|---|---|
| auth | `stores/auth.ts` | Token, expiry, user, role helpers, session lifecycle |
| toast | `stores/toast.ts` | Global toast queue (success/error/warning/info) |
| dashboard | `stores/dashboard.ts` | Role-aware summary fetch |
| courses | `stores/courses.ts` | Courses, sections, threads, posts |
| notifications | `stores/notifications.ts` | Notification list, unread counts, preferences, bulk read |
| orders | `stores/orders.ts` | Catalog, orders, timeline, payment, receipt |
| billing | `stores/billing.ts` | Bills, schedules, refunds, reason codes |
| admin | `stores/admin.ts` | Health status, diagnostic exports |
| offline | `stores/offline.ts` | Read-only flag, pending-action queue, cache I/O |

### 16.4 API Layer (`src/adapters/http.ts`)

- `X-Correlation-ID` generated per request (client-owned, included in logs for tracing)
- `Authorization: Bearer <token>` attached from localStorage
- `generateIdempotencyKey()` exported for payment/billing completion flows
- `normalizeError()` converts all Axios errors to typed `NormalizedError` objects — callers never parse raw Axios shapes
- 503 responses trigger `onCircuitOpen()` callback → sets `offlineStore.isReadOnly = true`
- `withRetry(fn, maxAttempts, baseDelayMs)` — exponential-backoff helper with jitter; never retries on 401/403/409/422

### 16.5 Offline / Poor-Network Architecture

```
IndexedDB (via IdbStore wrapper)
  ├── CacheStore  — TTL-keyed read-model snapshots (default TTL: 5 min)
  │                  getStale() returns expired entries without deletion (for fallback display)
  └── PendingQueue — serialized PendingAction entries with retry metadata

OfflineStore (Pinia)
  ├── isReadOnly — set true on 503/circuit-open; disables all write actions in UI
  ├── pendingActions — in-memory mirror of queue
  ├── retryBanner — shows RetryBanner when isReadOnly and queue non-empty
  └── loadQueue() — called at App.vue mount to rehydrate pending actions from IndexedDB

Exponential Backoff (src/offline/backoff.ts)
  ├── computeBackoffDelay(attempt, opts) — base 500ms, factor 2, max 30s, full jitter
  └── shouldRetry(httpStatus) — true for 5xx/429, false for 4xx except 429
```

**Conflict visibility**: stores never silently overwrite. `conflict` state fields surface `IDEMPOTENCY_KEY_CONFLICT` and `INVALID_STATE_TRANSITION` errors in the UI without hiding them.

### 16.6 UI Primitives (`src/components/ui/`)

| Component | Purpose |
|---|---|
| BaseCard | Container with header/footer slots and variant styling |
| BaseTable | Accessible table with typed column definitions |
| BaseField | Label + error + hint wrapper for form controls |
| BaseTabs | ARIA-role tab/tabpanel pair |
| StatusChip | Maps domain status strings to color variants |
| ConfirmModal | Teleported accessible modal with confirm/cancel actions |
| AlertBanner | Dismissible inline alert (error/warning/success/info) |
| EmptyState | Empty list state with heading and description |
| ErrorState | Error state with optional retry button |
| LoadingSpinner | Screen-reader accessible spinner with optional overlay |
| SearchInput | Labelled search input with v-model binding |
| TimelineItem | Timeline entry with dot, label, and timestamp |
| ReceiptPanel | Structured receipt view with line items and totals |
| RetryBanner | Shows pending offline action count |
| GlobalToast | Teleported toast container (driven by toast store) |

### 16.7 Masking & Permission Helpers

- `useMask.ts`: `maskEmail`, `maskName`, `maskAmount` — all masked by default; `reveal = true` required for display
- `usePermission.ts`: `can(role, scopeType?, scopeId?)`, `canManageSection(id)`, `canImportRosterForTerm(id)`, `canModerate()`
- `useCircuitBreaker.ts`: `isReadOnly` computed, `guardWrite(fn)` blocks fn and logs when read-only

---

## 17. Screen Map and Role-to-Screen Matrix (Prompt 6)

### 17.1 Route Map

| Route | Component | Roles |
|---|---|---|
| `/` | HomeView → role-specific dashboard | All |
| `/sections/:id/threads` | ThreadListView | All enrolled |
| `/sections/:id/threads/:id` | ThreadDetailView | All enrolled |
| `/notifications` | NotificationCenterView | All |
| `/notifications/preferences` | NotificationPreferencesView | All |
| `/catalog` | CatalogView | All |
| `/orders` | OrderListView | All |
| `/orders/:id` | OrderDetailView | All |
| `/orders/:id/payment` | PaymentView | Staff + self |
| `/orders/:id/receipt` | ReceiptView | All |
| `/bills` | BillListView | All |
| `/bills/:id` | BillDetailView | All |
| `/bills/:id/refund` | RefundRequestView | Registrar, Admin |
| `/grade-items` | GradeItemsView | Teacher, Admin |
| `/roster-import` | RosterImportView | Registrar, Admin |
| `/admin/moderation` | ModerationQueueView | Admin |
| `/admin/billing` | BillingOversightView | Admin, Registrar |
| `/admin/refunds` | RefundReconciliationView | Admin, Registrar |
| `/admin/health` | HealthView | Admin |

### 17.2 Role-to-Screen Matrix

| Screen | Student | Teacher | Registrar | Admin |
|---|---|---|---|---|
| Role dashboard | ✓ | ✓ | ✓ | ✓ |
| Discussions (read) | ✓ | ✓ | ✓ | ✓ |
| Discussions (post/edit within window) | ✓ | ✓ | — | ✓ |
| Moderation controls | — | — | — | ✓ |
| Notification Center | ✓ | ✓ | ✓ | ✓ |
| Catalog / Orders | ✓ | ✓ | ✓ | ✓ |
| Payment completion | — | — | ✓ | ✓ |
| Bills (own) | ✓ | ✓ | ✓ | ✓ |
| Refund request | — | — | ✓ | ✓ |
| Grade Items (publish) | — | ✓ (scoped) | — | ✓ |
| Roster Import | — | — | ✓ (scoped) | ✓ |
| Billing Oversight | — | — | ✓ | ✓ |
| Refund/Reconciliation Admin | — | — | ✓ | ✓ |
| Health / Diagnostics | — | — | — | ✓ |

### 17.3 Key UI State Invariants

- **Read-only mode**: `ReadOnlyBanner` always shown when `offline.isReadOnly = true`; all write buttons carry `:disabled="offline.isReadOnly"`
- **Sensitive-word blocking**: `CreateThreadModal` and `ThreadDetailView` debounce-check body content; submit is disabled and blocked terms listed while any remain
- **Edit-window countdown**: `PostItem` shows remaining time and disables edit after 15 minutes (client-side + server enforced)
- **Payment idempotency**: `PaymentView` generates key once at initiation; retry uses same key; conflict surfaces as visible `conflict` banner not silent error
- **Permission denied**: Role-restricted views render `permission-denied` section (not 404) so users understand why access is blocked

---

## Section 18 — Operational Modules (Prompt 7)

### 18.1 Diagnostic Export Flow

1. Admin triggers `POST /api/v1/admin/diagnostics/export` (requires `Idempotency-Key`).
2. `DiagnosticExportController` authorizes via `DiagnosticExportPolicy::create` (admin-only).
3. `DiagnosticExportService::trigger()` creates a `diagnostic_exports` row (`status = running`), collects a JSON payload (PHP version, Laravel version, DB/queue status, config snapshot), encrypts it with `EncryptionHelper::encryptFile` using `campuslearn.diagnostics.encryption_key`, writes to `storage/app/diagnostics/diag_{id}_{timestamp}.enc`, updates the row with path, size, SHA-256, and `status = completed`, writes an `audit_log_entries` row inside the same DB transaction.
4. `GET /api/v1/admin/diagnostics/exports` returns a paginated list of export records.
5. Frontend: `DiagnosticsAdminView.vue` polls `adminAdapter.listExports()` and surfaces export history. The trigger button is disabled in read-only mode.

### 18.2 Backup Management Flow

1. `BackupMetadataJob` runs nightly at 04:00 via the scheduler (registered in `routes/console.php`).
2. The job creates a `backup_jobs` row (`status = running`), then generates a fresh, deterministic DB export via `DatabaseExportService` (driver-agnostic: iterates tables via the active connection and emits INSERT statements under a fixed `-- CampusLearn SQL Export v1` header). The artifact is validated (non-empty + header magic) before being encrypted to `campuslearn.backups.target_dir`. The encrypted file's SHA-256 checksum, size, and `retention_expires_on = today + retention_days` are recorded. On success: `status = completed`. On failure: `status = failed`. The intermediate SQL dump file is always deleted in a `finally` block. If `campuslearn.backups.source_path` is explicitly set to an existing file, that path is used as the source artifact instead (staging/test override only).
3. Admin can trigger an ad-hoc backup via `POST /api/v1/admin/backups/trigger` (idempotent). `BackupController::trigger()` dispatches `BackupMetadataJob` to the queue and returns `202 Accepted`. An audit log entry is written.
4. `GET /api/v1/admin/backups` returns all backup records including `pruned` entries (retained for audit trail).
5. `GET /api/v1/admin/backups/{id}` returns a single record.
6. `BackupAdminView.vue` surfaces the backup list with status, retention date, and a trigger button (disabled in read-only mode). Pruned backups remain visible for audit trail.

### 18.3 DR Drill Record Flow

1. Admin navigates to the DR Drills screen (`DRAdminView.vue`).
2. Admin submits a drill record via `POST /api/v1/admin/dr-drills` with `drill_date`, `outcome` (passed/partial/failed), and optional `notes`.
3. `DrillController::store()` authorizes via `DrDrillPolicy::create`, delegates to `DrillRecordService::record()`.
4. `DrillRecordService` creates a `dr_drill_records` row inside a DB transaction and writes an `audit_log_entries` row.
5. `GET /api/v1/admin/dr-drills` returns a paginated list ordered by `drill_date` descending.
6. Frontend renders drill history with color-coded outcome chips and a prominently placed reminder that quarterly drills are required per the restore runbook.

### 18.4 Admin Settings and Audit Log

1. `GET /api/v1/admin/settings` reads `system_settings` rows for a curated allow-list of keys (edit window, order auto-close, penalty grace, penalty rate, fanout batch size, backup retention, receipt prefix).
2. `PATCH /api/v1/admin/settings` accepts a `settings` object; unknown keys are silently ignored; known keys are upserted in the `system_settings` table; an `audit_log_entries` row is written inside the same DB transaction.
3. `GET /api/v1/admin/audit-log` returns paginated `audit_log_entries` records with optional filters: `action` (substring), `actor_id`, `target_type`, `from`/`to` date range.
4. All three endpoints require the `Administrator` role globally (enforced via `SystemSettingPolicy`).
5. `AdminSettingsView.vue` renders a grouped settings form and displays the 50 most-recent audit log entries below the form. Saving triggers a log refresh.

### 18.5 Health Dashboard Endpoints (Summary)

All three health endpoints are wired to `HealthController`:
- `GET /api/health` (public) — returns `{ status, service, checks: { database, queue } }`. Used by load-balancer probes and the frontend initial-connectivity check.
- `GET /api/v1/health/circuit` (auth) — returns `CircuitBreakerService::snapshot()` with `mode`, `error_rate_pct`, `sample_count`, `window_seconds`. Consumed by `useCircuitBreaker.ts` every 30 seconds in the browser.
- `GET /api/v1/health/metrics` (admin) — returns `RequestMetricsService::summary()` + latency percentiles. Used by `HealthView.vue` to render the error-rate gauge and alert banner.

---

## Section 19 — Docker and Deployment Topology

### 19.1 Container Architecture

The system uses a `docker-compose.yml` at `repo/docker-compose.yml` with five services:

| Service | Build stage | Base image | Exposed port |
|---|---|---|---|
| `frontend` | `runtime` (nginx) | `nginx:alpine` | 5173 → 80 |
| `frontend-test` | `test` (builder) | `node:20-alpine` | none |
| `e2e` | `e2e-runner` (builder + chromium) | `node:20-alpine` | none |
| `backend` | — | `php:8.3-fpm-alpine` + nginx + supervisor | 8000 |
| `mysql` | — | `mysql:8.4` | 3306 |

### 19.2 Frontend Dockerfile Multi-Stage Layout

The `repo/frontend/Dockerfile` has four stages:

1. **`builder`** — `node:20-alpine`; installs npm deps (`npm ci --frozen-lockfile`); copies source; runs `npm run build` producing `dist/`.
2. **`test`** — inherits `builder`; adds no new layers. Node and all deps are available. Vitest runs here via `docker compose run --rm frontend-test npx vitest run unit_tests/`.
3. **`e2e-runner`** — inherits `builder`; runs `npx playwright install --with-deps chromium`. Playwright tests run here via `docker compose run --rm e2e npx playwright test e2e/`.
4. **`runtime`** — `nginx:alpine`; copies only `dist/` from `builder`; copies `nginx.conf`. This is the production image.

### 19.3 API Proxy

The `frontend` nginx service proxies all `/api/...` requests to `http://backend:8000` inside the docker network. This is required because the Vue SPA makes relative `/api/...` calls that the browser resolves to `http://localhost:5173/api/...`. Without the proxy block, these requests would not reach the backend.

The proxy block in `repo/frontend/nginx.conf`:
```nginx
location /api {
    proxy_pass http://backend:8000;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_read_timeout 60s;
}
```

### 19.4 Volume Layout

| Volume | Mount path | Purpose |
|---|---|---|
| `mysql_data` | `/var/lib/mysql` | MySQL data directory (persistent) |
| `backend_storage` | `/var/www/html/storage/app` | Laravel storage (queued files, receipts) |
| `backup_volume` | `/var/www/html/storage/backups` | Nightly encrypted backup files |

### 19.5 Test Orchestration

`repo/run_tests.sh` is the single entry point for all test suites. It passes the correct docker service names:

- Backend unit/API tests: `docker compose run --rm backend php artisan test ...`
- Frontend unit tests: `docker compose run --rm frontend-test npx vitest run unit_tests/ ...`
- E2E tests: `docker compose run --rm e2e npx playwright test e2e/`

Backend tests use SQLite in-memory (`DB_CONNECTION=sqlite` in `phpunit.xml`) and do not require MySQL to be running.

### 19.6 No HTTPS

All service communication is HTTP on the LAN. HTTPS is not implemented in this repository. An ops team deploying to production would add an nginx TLS terminator (or a local CA certificate) in front of the `frontend` service. That is out of scope and recorded as an assumption in `docs/questions.md`.

