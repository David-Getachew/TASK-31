# CampusLearn — Student Information & Billing Portal

Project type: fullstack

A full-stack offline LAN web platform for district-operated learning programs. Students, teachers, registrars, and administrators manage courses, communications, and fee-based services entirely on a local network. No internet connectivity is required or used at runtime.

---

## Stack

| Layer | Technology | Version |
|---|---|---|
| Frontend | Vue 3, TypeScript, Vite, Vue Router, Pinia, Axios | Vue 3.5 / Vite 6 |
| Frontend testing | Vitest, Vue Test Utils, Playwright | latest stable |
| Backend | PHP, Laravel, Eloquent ORM | PHP 8.3 / Laravel 13 |
| Backend testing | Pest, PHPUnit | Pest 3 |
| Database | MySQL | 8.4 |
| Container | Docker Compose | 2.x |

---

## Repository Structure

```
TASK-31/
├── docs/
│   ├── design.md                  System architecture and module design
│   ├── api-spec.md                API conventions and endpoint inventory
│   ├── questions.md               Ambiguity log (sole location for unresolved assumptions)
│   ├── requirement-traceability.md  Req → module → endpoint → test mapping
│   ├── endpoint-inventory.md      Full endpoint list with coverage status
│   ├── restore-runbook.md         DR restore procedure (decrypt backup, restore DB, verify)
│   └── test-traceability.md       Test file → requirement and coverage mapping
├── repo/
│   ├── README.md                  This file
│   ├── docker-compose.yml         Service definitions (frontend, backend, mysql)
│   ├── run_tests.sh               Test orchestration script (docker-first)
│   ├── frontend/
│   │   ├── Dockerfile
│   │   ├── package.json
│   │   ├── vite.config.ts
│   │   ├── tsconfig.json
│   │   ├── index.html
│   │   ├── src/                   Vue 3 application source
│   │   │   ├── main.ts
│   │   │   ├── App.vue
│   │   │   ├── router/
│   │   │   ├── stores/
│   │   │   ├── views/
│   │   │   ├── components/
│   │   │   ├── composables/
│   │   │   ├── adapters/
│   │   │   └── types/
│   │   ├── public/
│   │   ├── unit_tests/            Vitest unit tests + e2e/ sub-folder
│   │   └── e2e/                   Playwright E2E tests
│   └── backend/
│       ├── Dockerfile
│       ├── composer.json
│       ├── artisan
│       ├── phpunit.xml
│       ├── .env.example
│       ├── app/                   Laravel Http, Models, Policies, Jobs, etc.
│       ├── bootstrap/
│       ├── config/
│       ├── database/              Migrations, seeders, factories
│       ├── public/
│       ├── resources/
│       ├── routes/
│       ├── src/                   Domain services, repositories, billing engine
│       ├── storage/
│       ├── unit_tests/            Pest unit tests
│       └── api_tests/             Pest API/integration tests (no-mock dominant)
├── sessions/                      (do not modify)
├── execution_plan.md
├── metadata.json
├── CLAUDE.md
└── questions.md                   (original source — docs/questions.md is the live log)
```

---

## Services and Ports

| Service | Port | Description |
|---|---|---|
| Frontend | 5173 (dev) / 80 (prod) | Vue 3 SPA served by Vite or nginx |
| Backend API | 8000 | Laravel, served via PHP-FPM + nginx |
| MySQL | 3306 | Primary data store (internal only) |

All ports are LAN-local only. No public internet exposure.

---

## Offline / Local Network Constraints

- **No external services:** The application makes no calls to external APIs, CDNs, payment processors, or identity providers at runtime.
- **All payments local:** Payment completion is handled by district staff at a local office or kiosk terminal. The system records and confirms the transaction locally.
- **All notifications local:** Notification fan-out uses Laravel's `database` queue driver. No email servers or external messaging services.
- **All backups local:** Nightly encrypted backups are written to a local filesystem volume. No cloud storage.
- **All logs local:** Structured JSON logs are written to `storage/logs/`. No external log aggregation.

---

## Environment Setup

For local development, both startup paths are now zero-config:

- `docker-compose up --build` (hyphenated form) is supported.
- `docker compose up --build` works without pre-creating env files.
- `bash run_tests.sh` auto-creates `repo/.env` and `repo/backend/.env` when missing.

You can still override any value via shell env vars or `repo/.env`.

Defaults are local-only and include:

- `APP_KEY`
- `DB_PASSWORD`
- `MYSQL_ROOT_PASSWORD`
- `BACKUP_ENCRYPTION_KEY`
- `DIAGNOSTIC_ENCRYPTION_KEY`

### Domain configuration (`config/campuslearn.php`)

Every domain invariant reads from this file — never hardcode. Override via environment variables where noted:

| Key | Default | Purpose |
|---|---|---|
| `moderation.edit_window_minutes` | 15 | Author self-edit window (`EditWindowPolicy`) |
| `orders.auto_close_minutes` | 30 | Pending-payment order auto-close (`OrderStateMachine`) |
| `billing.penalty_rate_bps` | 500 | Late-payment penalty rate (`PenaltyCalculator`) — 500 bps = 5% |
| `billing.penalty_grace_days` | 10 | Days past due before penalty triggers |
| `billing.recurring_day_of_month` | 1 | Recurring billing trigger day |
| `billing.recurring_hour` | 2 | Recurring billing trigger hour (24h) |
| `idempotency.ttl_hours` | 24 | Idempotency-key cache lifetime |
| `backups.retention_days` | 30 | Nightly backup retention |
| `auth.password_min_length` | 10 | Minimum password length (`PasswordRule`) |
| `auth.lockout_threshold` | 5 | Failed attempts before lock (`LoginThrottlePolicy`) |
| `auth.lockout_window_minutes` | 15 | Window for counting failed attempts |
| `auth.lockout_duration_minutes` | 15 | Account lock duration |
| `observability.circuit_trip_bps` | 200 | Error-rate threshold (bps) to trip breaker (`CircuitBreakerPolicy`) |
| `observability.circuit_reset_bps` | 100 | Error-rate threshold (bps) to reset breaker (`CircuitBreakerPolicy`) |
| `observability.circuit_window_seconds` | 300 | Rolling error-rate window in seconds (`CircuitBreakerService`) |
| `auth.token_ttl_minutes` | 720 | Sanctum token lifetime in minutes (12 hours default) |

### Environment Variable Overrides

Every key in `config/campuslearn.php` is overrideable via environment variables.

| Config key | Env var |
|---|---|
| `moderation.edit_window_minutes` | `CL_EDIT_WINDOW_MINUTES` |
| `orders.auto_close_minutes` | `CL_ORDER_AUTO_CLOSE_MINUTES` |
| `billing.penalty_rate_bps` | `CL_PENALTY_RATE_BPS` |
| `billing.penalty_grace_days` | `CL_PENALTY_GRACE_DAYS` |
| `billing.penalty_bill_due_days` | `CL_PENALTY_BILL_DUE_DAYS` |
| `billing.recurring_day_of_month` | `CL_RECURRING_DAY_OF_MONTH` |
| `billing.recurring_hour` | `CL_RECURRING_HOUR` |
| `notifications.fanout_batch_size` | `CL_NOTIFICATION_FANOUT_BATCH_SIZE` |
| `receipts.number_prefix` | `CL_RECEIPT_NUMBER_PREFIX` |
| `idempotency.ttl_hours` | `CL_IDEMPOTENCY_TTL_HOURS` |
| `backups.retention_days` | `CL_BACKUP_RETENTION_DAYS` |
| `backups.source_path` | `CL_BACKUP_SOURCE_PATH` |
| `backups.target_dir` | `CL_BACKUP_TARGET_DIR` |
| `auth.password_min_length` | `CL_PASSWORD_MIN_LENGTH` |
| `auth.login_lock_threshold` | `CL_LOGIN_LOCK_THRESHOLD` |
| `auth.login_lock_window_minutes` | `CL_LOGIN_LOCK_WINDOW_MINUTES` |
| `auth.login_lock_duration_minutes` | `CL_LOGIN_LOCK_DURATION_MINUTES` |
| `auth.token_ttl_minutes` | `CL_TOKEN_TTL_MINUTES` |
| `observability.circuit_trip_bps` | `CL_CIRCUIT_TRIP_BPS` |
| `observability.circuit_reset_bps` | `CL_CIRCUIT_RESET_BPS` |
| `observability.circuit_window_seconds` | `CL_CIRCUIT_WINDOW_SECONDS` |

---

## Business Engines

The following business engines were implemented in the core build phase:

### Scheduler Commands (Laravel 11+ `routes/console.php`)

| Command | Schedule | Job | Purpose |
|---|---|---|---|
| `campuslearn:orders:auto-close` | every 5 minutes | `OrderAutoCloseJob` | Cancels `PendingPayment` orders past their `auto_close_at` timestamp |
| `campuslearn:billing:recurring` | daily 02:00 | `RecurringBillingJob` | Generates recurring bills on the 1st of each month |
| `campuslearn:billing:penalty` | daily 03:00 | `PenaltyJob` | Applies 5% penalty to bills that are ≥10 days past due (idempotent) |
| `campuslearn:health:evaluate-circuit` | every minute | `AlertThresholdEvaluationJob` | Evaluates error rate; trips/resets circuit breaker |
| `campuslearn:backups:record-metadata` | daily 04:00 | `BackupMetadataJob` | Encrypts backup file and records SHA-256 + retention in `backup_jobs` |

### Queue Driver

All notification fan-out uses Laravel's `database` queue driver. No external queues are used. The `jobs`, `job_batches`, and `failed_jobs` tables are managed by standard Laravel migrations.

Fan-out flow: `NotificationOrchestrator::notify(type, recipients, payload)` → resolves template → filters opted-out recipients → dispatches `SendNotificationJob` chunks → job writes `notifications` + `notification_deliveries` rows.

Configurable chunk size: `config('campuslearn.notifications.fanout_batch_size')` (default 50).

### New Endpoint Groups

The following endpoint groups became live in this phase (controllers, routes, tests all authored):

| Group | Path Prefix | Key Endpoints |
|---|---|---|
| Discussions | `/api/v1/threads/*`, `/api/v1/posts/*` | Create/edit threads, posts, comments; sensitive-word filter; @mentions |
| Moderation | `/api/v1/admin/moderation/*`, `/api/v1/admin/threads/*` | Queue/history plus hide/restore/lock actions; audit log |
| Notifications | `/api/v1/notifications/*` | List, unread-count, bulk-mark-read, per-category preferences |
| Orders | `/api/v1/orders/*` | Create order with tax snapshot; timeline; auto-close |
| Payments | `/api/v1/orders/{id}/payment*` | Initiate + complete (idempotent); receipt generation |
| Billing | `/api/v1/bills/*`, `/api/v1/admin/bills/*` | My bills; admin generate; billing schedules |
| Refunds | `/api/v1/bills/{id}/refunds`, `/api/v1/refunds/*` | Create (idempotent); reversal ledger; reconciliation flag |
| Ledger | `/api/v1/admin/ledger`, `/api/v1/admin/reconciliation*` | Admin read + resolve + summary |
| Grade Items | `/api/v1/sections/{id}/grade-items/*` | CRUD + publish with student notifications |
| Roster Import | `/api/v1/terms/{id}/roster-imports`, `/api/v1/roster-imports/{id}` | CSV upload; history; per-row error detail |
| Enrollments | `/api/v1/enrollments/{id}/approve`, `/api/v1/enrollments/{id}/deny` | Staff approve/deny; sends outcome notification |
| Appointments | `/api/v1/appointments/*` | CRUD; reschedule/cancel triggers change notifications |
| Catalog / Fees | `/api/v1/catalog`, `/api/v1/admin/catalog/*`, `/api/v1/admin/fee-categories/*` | Catalog items; fee categories; tax rules |
| Sensitive Words | `/api/v1/admin/sensitive-words/*` | Admin CRUD; audited |

---

## Docker

### Service topology

| Service | Container | Port | Notes |
|---|---|---|---|
| `frontend` | nginx:alpine (runtime stage) | 5173 → 80 | Serves Vue SPA; proxies `/api/...` to `http://backend:8000` |
| `backend` | php:8.3-fpm-alpine + nginx + supervisord | 8000 | PHP-FPM behind nginx; queue worker managed by supervisord |
| `mysql` | mysql:8.4 | 3306 | Stores all data; health-checked before backend starts |
| `frontend-test` | node:20-alpine (builder stage) | — | Node-based; runs Vitest unit tests |
| `e2e` | node:20-alpine + chromium (e2e-runner stage) | — | Playwright; requires `frontend` and `backend` running |

API calls from the browser go to `http://localhost:5173/api/...`. The `frontend` nginx service proxies them to `http://backend:8000/api/...` inside the docker network. The frontend never talks directly to MySQL.

### Starting the stack

```bash
cd repo
docker-compose up --build
# or
docker compose up --build
```

After the stack starts:
- Frontend URL: `http://localhost:5173/` (redirects to `/login`)
- Backend API URL: `http://localhost:8000/api/v1`
- Health URL: `http://localhost:8000/api/health`

### Verification Method

Use these executable checks to confirm the system is working:

```bash
# Health verification
curl -sS http://localhost:8000/api/health

# Frontend availability verification
curl -sI http://localhost:5173 | head -n 1
```

Authenticate with demo credentials and verify an authenticated API call:

```bash
TOKEN=$(curl -sS -X POST http://localhost:8000/api/v1/auth/login \
	-H "Content-Type: application/json" \
	-d '{"email":"student@example.com","password":"Password1234!"}' | \
	php -r '$d=json_decode(stream_get_contents(STDIN), true); echo $d["data"]["token"] ?? "";')

curl -sS http://localhost:8000/api/v1/auth/me \
	-H "Authorization: Bearer ${TOKEN}" \
	-H "Accept: application/json"
```

### First-time database setup

No manual DB setup is required. The backend container applies migrations on startup.

If you need to re-seed deterministic demo users and fixtures, use a Docker-contained command:

```bash
docker compose exec backend php artisan db:seed --class=Database\\Seeders\\E2eTestSeeder --force --no-interaction
```

### Demo Credentials

Authentication is required.

| Role | Email | Password |
|---|---|---|
| Student | `student@example.com` | `Password1234!` |
| Teacher | `teacher@example.com` | `TeacherPass999!` |
| Registrar | `registrar@example.com` | `RegistrarPass999!` |
| Administrator | `admin@example.com` | `AdminPass999!` |

### Idempotency Verification (API)

Write endpoints protected by idempotency require `Idempotency-Key`.

```bash
IDEMPOTENCY_KEY=$(cat /proc/sys/kernel/random/uuid)

curl -sS -X POST http://localhost:8000/api/v1/orders/1/payment/complete \
	-H "Authorization: Bearer ${TOKEN}" \
	-H "Content-Type: application/json" \
	-H "Idempotency-Key: ${IDEMPOTENCY_KEY}" \
	-d '{"payment_method":"cash","amount_cents":1000}'
```

### No HTTPS

All communication is HTTP on the LAN. HTTPS termination is not implemented in this repository. For production hardening, an ops team would add an nginx TLS terminator with a local CA certificate. That is out of scope and is documented in `docs/questions.md`.

---

## Running Tests

`run_tests.sh` orchestrates all four suites in docker-first order:

```bash
cd repo

# Full suite
bash run_tests.sh

# Individual suites
bash run_tests.sh --suite=unit          # Backend unit tests (SQLite in-memory, no mysql needed)
bash run_tests.sh --suite=api           # Backend API/integration tests (SQLite in-memory)
bash run_tests.sh --suite=frontend-unit # Frontend unit tests (Vitest, node builder stage)
bash run_tests.sh --suite=e2e           # E2E tests (requires frontend + backend + mysql running)
```

### Suite details

| Suite | Location | Runner | Requires |
|---|---|---|---|
| Backend unit | `repo/backend/unit_tests/` | Pest/PHPUnit | `backend` container (SQLite in-memory) |
| Backend API | `repo/backend/api_tests/` | Pest/PHPUnit | `backend` container (SQLite in-memory) |
| Frontend unit | `repo/frontend/unit_tests/` | Vitest | `frontend-test` container (node stage) |
| E2E | `repo/frontend/e2e/` | Playwright | `e2e` container + `frontend` + `backend` + `mysql` running |

**Note:** Frontend unit tests run in the `frontend-test` docker service (node builder stage, has node). They do **not** run in the `frontend` service (nginx runtime, no node).

**Note:** E2E tests are fully orchestrated by `run_tests.sh`; required services are started automatically.

---

## Frontend Architecture

The frontend is a Vue 3 TypeScript SPA organized around two layout shells and nine Pinia stores.

### Application Shell

| Component | Path | Purpose |
|---|---|---|
| `AuthLayout.vue` | `src/layouts/` | Wraps all guarded routes; renders `AppNav` + `ReadOnlyBanner` |
| `GuestLayout.vue` | `src/layouts/` | Wraps the login page; no nav chrome |
| `AppNav.vue` | `src/components/` | Role-conditional nav links; unread notification badge |
| `ReadOnlyBanner.vue` | `src/components/ui/` | Shown when circuit breaker trips; blocks write actions |
| `App.vue` | `src/` | Mounts router, registers circuit-open callback, rehydrates offline queue |

### Router and Guards

All authenticated routes nest under `AuthLayout`. The `beforeEach` guard enforces:
1. Session-expiry → clear and redirect to login
2. Unauthenticated on `requiresAuth` route → redirect to login
3. User hydration (token present but no user object) via `auth.initSession()`
4. Role restriction via `meta.roles` → redirect to `unauthorized`
5. Already-authenticated user navigating to login → redirect to home

### Pinia Stores

| Store | File | Primary State |
|---|---|---|
| `useAuthStore` | `stores/auth.ts` | `user`, `token`, `expiresAt`, `isAuthenticated`, `isSessionExpired` |
| `useDashboardStore` | `stores/dashboard.ts` | `summary`, `loading`, `error` |
| `useCoursesStore` | `stores/courses.ts` | `courses`, `sections`, `threads`, `posts` |
| `useNotificationsStore` | `stores/notifications.ts` | `notifications`, `unreadCounts`, `preferences`, `totalUnread` |
| `useOrdersStore` | `stores/orders.ts` | `orders`, `catalog`, `receipt`, `conflict` |
| `useBillingStore` | `stores/billing.ts` | `bills`, `schedules`, `refunds`, `conflict` |
| `useAdminStore` | `stores/admin.ts` | `health`, `moderationQueue`, `exports` |
| `useOfflineStore` | `stores/offline.ts` | `isReadOnly`, `pendingActions`, `retryBanner` |
| `useToastStore` | `stores/toast.ts` | `toasts` queue |

### API Layer (`src/adapters/`)

- **`http.ts`** — Axios instance with request interceptor adding `Authorization` and `X-Correlation-ID` headers. Response interceptor triggers circuit-open callback on 503 and clears auth on 401. Exports `generateIdempotencyKey()`, `normalizeError()`, `withRetry(fn, maxAttempts, baseDelayMs)`, and `onCircuitOpen(cb)`.
- **Domain adapters** — One file per domain: `threads.ts`, `moderation.ts`, `notifications.ts`, `orders.ts`, `bills.ts`, `roster.ts`, `gradeItems.ts`, `appointments.ts`, `dashboard.ts`, `admin.ts`, `enrollments.ts`. Each exports typed functions that call `http.ts`.

### Offline Architecture (`src/offline/`)

| Module | Purpose |
|---|---|
| `db.ts` — `IdbStore` | Thin IndexedDB wrapper: `get`, `set`, `delete`, `getAll` |
| `cache.ts` — `CacheStore` | TTL-aware read cache (default 5 min); `getStale()` for fallback display |
| `queue.ts` — `PendingQueue` | Serialized pending-action queue: `enqueue`, `dequeue`, `getAll`, `update` |
| `backoff.ts` | `computeBackoffDelay(attempt)`: base 500ms, ×2 factor, 30s cap, full jitter. `shouldRetry(httpStatus)`: true for 5xx/429, false for 4xx. |

`OfflineStore.loadQueue()` is called at `App.vue` mount to rehydrate `pendingActions` from IndexedDB. `setReadOnly(true)` is triggered by the circuit-open callback registered in `App.vue`.

### Masking and Permissions

- **`useMask.ts`** — `maskEmail`, `maskName`, `maskAmount` all return obscured strings by default; pass `reveal=true` for plain values. `formatCents(cents)` formats as locale currency.
- **`usePermission.ts`** — `can(role, scopeType?, scopeId?)` checks the authenticated user's role grants including optional scope matching. `canManageSection(id)`, `canImportRosterForTerm(id)`, `canModerate()` are convenience wrappers.
- **`useCircuitBreaker.ts`** — `isReadOnly` computed from `OfflineStore`. `guardWrite(fn)` throws if `isReadOnly` is true.

---

## Application Screens

The SPA is organized into these screen groups, each guarded to the listed roles.

### Role-Aware Dashboards

`HomeView.vue` renders the appropriate dashboard component based on the authenticated user's primary role.

| Component | Route | Roles | Data shown |
|---|---|---|---|
| `StudentDashboard.vue` | `/` | student | Enrolled courses, unpaid bills, recent notifications |
| `TeacherDashboard.vue` | `/` | teacher | Assigned sections, draft grade items, discussion activity |
| `RegistrarDashboard.vue` | `/` | registrar | Pending roster imports, enrollment queue, upcoming terms |
| `AdminDashboard.vue` | `/` | administrator | System health, open reconciliation flags, billing summary |

### Discussion Screens

| Route | Component | Key behaviors |
|---|---|---|
| `/courses/:id/threads` | `ThreadListView.vue` | Thread list with type filter; pagination |
| `/threads/:id` | `ThreadDetailView.vue` | Posts + comments; @mention autocomplete; inline reply |
| Modal | `CreateThreadModal.vue` | Debounced sensitive-word check (600ms); blocked terms listed; submit disabled while terms blocked |
| Inline | `PostItem.vue` | 15-minute edit countdown (client-side); EDIT_WINDOW_EXPIRED from server shown as alert; moderator hide/restore/lock controls |

### Notification Screens

| Route | Component | Key behaviors |
|---|---|---|
| `/notifications` | `NotificationCenterView.vue` | Category tabs; unread badge; checkbox bulk-select; "Mark selected read" disabled when none selected |
| `/notifications/preferences` | `NotificationPreferencesView.vue` | Toggle per category; saved via `PATCH /notifications/preferences` |

### Academic Screens

| Route | Component | Roles | Key behaviors |
|---|---|---|---|
| `/sections/:id/grade-items` | `GradeItemsView.vue` | teacher | Create draft; publish transitions to published; notification dispatched to section roster |
| `/roster-import` | `RosterImportView.vue` | registrar | CSV upload; progress; per-row error table |

### Order and Billing Screens

| Route | Component | Key behaviors |
|---|---|---|
| `/catalog` | `CatalogView.vue` | Browse items; add to order |
| `/orders` | `OrdersView.vue` | Status-filtered list; `StatusChip` color variants |
| `/orders/:id` | `OrderDetailView.vue` | Timeline; cancel button |
| `/orders/:id/payment` | `PaymentView.vue` | Step 1 initiate (→ idempotency key); Step 2 complete with retry safety; conflict banner for IDEMPOTENCY_KEY_CONFLICT |
| `/orders/:id/receipt` | `ReceiptView.vue` | `ReceiptPanel` printable output |
| `/bills` | `BillsView.vue` | Student bill list |
| `/bills/:id` | `BillDetailView.vue` | Line items; refund request form; conflict banner for REFUND_EXCEEDS_BALANCE |

### Admin Screens

| Route | Component | Roles | Key behaviors |
|---|---|---|---|
| `/admin/moderation` | `ModerationQueueView.vue` | staff | Flagged threads; hide/restore/lock buttons; state-machine enforced |
| `/admin/billing` | `AdminBillingView.vue` | administrator | Bill generation with idempotency; billing schedule management |
| `/admin/refunds` | `AdminRefundsView.vue` | staff | Approve/reject refunds; reconciliation flag resolution |
| `/admin/health` | `HealthView.vue` | administrator | Circuit state alert; error rate threshold warning; diagnostic export trigger |

### Key UI State Invariants

| Invariant | Enforcement |
|---|---|
| Read-only mode | `ReadOnlyBanner` visible; `guardWrite()` blocks all mutating actions |
| Sensitive-word blocking | Submit button `disabled` while `blockedTerms.length > 0`; unblocked only after body rewrite |
| Edit-window countdown | `PostItem` `isEditable` computed expires at 15 min; edit button removed; server enforces independently |
| Payment idempotency | Idempotency key generated once at initiation; held in component state; reused on all retries; `IDEMPOTENCY_KEY_CONFLICT` surfaces as user-visible conflict banner |
| Permission denied | `403` responses → `AlertBanner` with "You don't have permission" message; never silently swallowed |
| Conflict vs error | `IDEMPOTENCY_KEY_CONFLICT`, `INVALID_STATE_TRANSITION`, `REFUND_EXCEEDS_BALANCE` stored in `store.conflict`; other errors in `store.error`; rendered separately |

---

## Documentation Index

| Document | Purpose |
|---|---|
| `docs/design.md` | Architecture, role model, module boundaries, frontend shell + screen map |
| `docs/api-spec.md` | API conventions, endpoint inventory skeleton |
| `docs/questions.md` | Ambiguity log — all unresolved assumptions recorded here |
| `docs/requirement-traceability.md` | Req → backend module → endpoint → test mapping |
| `docs/endpoint-inventory.md` | Full endpoint list with method, auth, idempotency, and coverage status |
| `docs/test-traceability.md` | Test file → requirement → status mapping for all authored tests |
