<?php

use App\Http\Controllers\Api\AdminSettingsController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\BackupController;
use App\Http\Controllers\Api\DiagnosticExportController;
use App\Http\Controllers\Api\DrillController;
use App\Http\Controllers\Api\BillController;
use App\Http\Controllers\Api\BillingScheduleController;
use App\Http\Controllers\Api\CatalogItemController;
use App\Http\Controllers\Api\CommentController;
use App\Http\Controllers\Api\CourseController;
use App\Http\Controllers\Api\EnrollmentController;
use App\Http\Controllers\Api\FeeCategoryController;
use App\Http\Controllers\Api\GradeItemController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\LedgerController;
use App\Http\Controllers\Api\MentionController;
use App\Http\Controllers\Api\ModerationController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PostController;
use App\Http\Controllers\Api\ReceiptController;
use App\Http\Controllers\Api\ReconciliationController;
use App\Http\Controllers\Api\RefundController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\RosterImportController;
use App\Http\Controllers\Api\SectionController;
use App\Http\Controllers\Api\SensitiveWordRuleController;
use App\Http\Controllers\Api\TermController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Responses\ApiEnvelope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes here receive the "api" middleware group automatically.
| Authentication is enforced per-route or via middleware groups below.
|
*/

// Public health check — no auth required
Route::get('/health', [HealthController::class, 'index']);

// Versioned API routes
Route::prefix('v1')->group(function () {

    // ── Authentication ─────────────────────────────────────────────────────
    Route::post('/auth/login', [AuthController::class, 'login'])
        ->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])
            ->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])
            ->name('auth.me');
    });

    // ── Health & observability (authenticated) ─────────────────────────────
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/health/circuit', [HealthController::class, 'circuit'])
            ->name('health.circuit');
        Route::get('/health/metrics', [HealthController::class, 'metrics'])
            ->name('health.metrics');
    });

    // ── Contract test harness (non-production only) ────────────────────────
    // Exercises IdempotencyMiddleware + envelope renderer without domain logic.
    // Gated: disabled in production, and requires an authenticated caller elsewhere
    // so it cannot be hit anonymously on any deployed environment.
    if (! app()->environment('production')) {
        Route::post('/_contract/echo', function (Request $request) {
            return ApiEnvelope::data([
                'echoed'      => $request->all(),
                'received_at' => now()->toIso8601String(),
            ]);
        })->middleware(['auth:sanctum', 'idempotent'])->name('contract.echo');
    }

    // ── Domain routes (Prompt 4) ───────────────────────────────────────────
    Route::middleware(['auth:sanctum', 'read-only'])->group(function () {

        // Role-aware dashboard
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard.index');

        // Academic: Terms, Courses, Sections
        Route::get('/terms', [TermController::class, 'index'])->name('terms.index');
        Route::get('/terms/{term}', [TermController::class, 'show'])->name('terms.show');
        Route::get('/courses', [CourseController::class, 'index'])->name('courses.index');
        Route::get('/courses/{course}', [CourseController::class, 'show'])->name('courses.show');
        Route::get('/sections', [SectionController::class, 'index'])->name('sections.index');
        Route::get('/sections/{section}', [SectionController::class, 'show'])->name('sections.show');
        Route::get('/sections/{section}/roster', [SectionController::class, 'roster'])->name('sections.roster');

        // Roster imports
        Route::post('/terms/{term}/roster-imports', [RosterImportController::class, 'store'])->name('roster-imports.store');
        Route::get('/terms/{term}/roster-imports', [RosterImportController::class, 'history'])->name('roster-imports.history');
        Route::get('/roster-imports/{rosterImport}', [RosterImportController::class, 'show'])->name('roster-imports.show');

        // Grade items
        Route::get('/sections/{section}/grade-items', [GradeItemController::class, 'index'])->name('grade-items.index');
        Route::post('/sections/{section}/grade-items', [GradeItemController::class, 'store'])->name('grade-items.store');
        Route::patch('/sections/{section}/grade-items/{gradeItem}', [GradeItemController::class, 'update'])->name('grade-items.update');
        Route::post('/sections/{section}/grade-items/{gradeItem}/publish', [GradeItemController::class, 'publish'])->name('grade-items.publish');

        // Enrollments
        Route::post('/enrollments/{enrollment}/approve', [EnrollmentController::class, 'approve'])->name('enrollments.approve');
        Route::post('/enrollments/{enrollment}/deny', [EnrollmentController::class, 'deny'])->name('enrollments.deny');

        // Discussions: Threads, Posts, Comments, Reports
        Route::get('/threads', [ThreadController::class, 'index'])->name('threads.index');
        Route::post('/threads', [ThreadController::class, 'store'])->name('threads.store');
        Route::get('/threads/{thread}', [ThreadController::class, 'show'])->name('threads.show');
        Route::patch('/threads/{thread}', [ThreadController::class, 'update'])->name('threads.update');

        Route::get('/threads/{thread}/posts', [PostController::class, 'index'])->name('posts.index');
        Route::post('/threads/{thread}/posts', [PostController::class, 'store'])->name('posts.store');
        Route::get('/threads/{thread}/posts/{post}', [PostController::class, 'show'])->name('posts.show');
        Route::patch('/threads/{thread}/posts/{post}', [PostController::class, 'update'])->name('posts.update');
        Route::delete('/threads/{thread}/posts/{post}', [PostController::class, 'destroy'])->name('posts.destroy');

        Route::post('/posts/{post}/comments', [CommentController::class, 'store'])->name('comments.store');
        Route::patch('/posts/{post}/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
        Route::delete('/posts/{post}/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');

        Route::post('/posts/{post}/reports', [ReportController::class, 'storeForPost'])->name('reports.post');
        Route::post('/posts/{post}/comments/{comment}/reports', [ReportController::class, 'storeForComment'])->name('reports.comment');

        // Mentions
        Route::get('/mentions', [MentionController::class, 'index'])->name('mentions.index');

        // Moderation — admin-prefixed action-specific routes (match frontend adapter)
        Route::get('/admin/moderation/queue', [ModerationController::class, 'queue'])->name('moderation.queue');
        Route::get('/admin/moderation/history', [ModerationController::class, 'history'])->name('moderation.history');
        Route::post('/admin/threads/{thread}/hide', [ModerationController::class, 'hideThread'])->name('moderation.thread.hide');
        Route::post('/admin/threads/{thread}/restore', [ModerationController::class, 'restoreThread'])->name('moderation.thread.restore');
        Route::post('/admin/threads/{thread}/lock', [ModerationController::class, 'lockThread'])->name('moderation.thread.lock');
        Route::post('/admin/threads/{thread}/posts/{post}/hide', [ModerationController::class, 'hidePost'])->name('moderation.post.hide');
        Route::post('/admin/threads/{thread}/posts/{post}/restore', [ModerationController::class, 'restorePost'])->name('moderation.post.restore');

        // Sensitive-word check (pre-submission, authenticated)
        Route::post('/sensitive-words/check', [SensitiveWordRuleController::class, 'check'])->name('sensitive-words.check');

        // Admin sensitive-word rules
        Route::get('/admin/sensitive-words', [SensitiveWordRuleController::class, 'index'])->name('sensitive-words.index');
        Route::post('/admin/sensitive-words', [SensitiveWordRuleController::class, 'store'])->name('sensitive-words.store');
        Route::delete('/admin/sensitive-words/{sensitiveWordRule}', [SensitiveWordRuleController::class, 'destroy'])->name('sensitive-words.destroy');

        // Notifications
        Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount'])->name('notifications.unread-count');
        Route::post('/notifications/mark-read', [NotificationController::class, 'markRead'])->name('notifications.mark-read');
        Route::post('/notifications/{id}/read', [NotificationController::class, 'markOneRead'])->name('notifications.read-one');
        Route::get('/notifications/preferences', [NotificationController::class, 'preferences'])->name('notifications.preferences');
        Route::patch('/notifications/preferences', [NotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');

        // Orders
        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::delete('/orders/{order}', [OrderController::class, 'destroy'])->name('orders.destroy');
        Route::get('/orders/{order}/timeline', [OrderController::class, 'timeline'])->name('orders.timeline');
        Route::get('/orders/{order}/receipt', [ReceiptController::class, 'show'])->name('receipts.show');
        Route::get('/orders/{order}/receipt/print', [ReceiptController::class, 'print'])->name('receipts.print');

        // Catalog + fee categories + tax rules
        Route::get('/catalog', [CatalogItemController::class, 'index'])->name('catalog.index');
        Route::post('/admin/catalog', [CatalogItemController::class, 'store'])->name('catalog.store');
        Route::patch('/admin/catalog/{catalogItem}', [CatalogItemController::class, 'update'])->name('catalog.update');

        Route::get('/admin/fee-categories', [FeeCategoryController::class, 'index'])->name('fee-categories.index');
        Route::post('/admin/fee-categories', [FeeCategoryController::class, 'store'])->name('fee-categories.store');
        Route::patch('/admin/fee-categories/{feeCategory}', [FeeCategoryController::class, 'update'])->name('fee-categories.update');
        Route::post('/admin/fee-categories/{feeCategory}/tax-rules', [FeeCategoryController::class, 'storeTaxRule'])->name('tax-rules.store');
        Route::patch('/admin/fee-categories/{feeCategory}/tax-rules/{taxRule}', [FeeCategoryController::class, 'updateTaxRule'])->name('tax-rules.update');

        // Bills + billing schedules
        Route::get('/bills', [BillController::class, 'mineIndex'])->name('bills.mine');
        Route::get('/bills/{bill}', [BillController::class, 'show'])->name('bills.show');
        Route::get('/admin/bills', [BillController::class, 'adminIndex'])->name('bills.admin-index');
        Route::get('/billing-schedules', [BillingScheduleController::class, 'index'])->name('billing-schedules.index');
        Route::patch('/billing-schedules/{billSchedule}', [BillingScheduleController::class, 'update'])->name('billing-schedules.update');

        // Refunds
        Route::get('/refunds', [RefundController::class, 'index'])->name('refunds.index');
        Route::get('/refunds/{refund}', [RefundController::class, 'show'])->name('refunds.show');
        Route::get('/refund-reason-codes', [RefundController::class, 'reasonCodes'])->name('refund-reason-codes.index');
        Route::post('/admin/refunds/{refund}/approve', [RefundController::class, 'approve'])->name('refunds.approve');
        Route::post('/admin/refunds/{refund}/reject', [RefundController::class, 'reject'])->name('refunds.reject');

        // Ledger + reconciliation
        Route::get('/admin/ledger', [LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/admin/reconciliation', [ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::get('/admin/reconciliation/summary', [ReconciliationController::class, 'summary'])->name('reconciliation.summary');
        Route::post('/admin/reconciliation/{reconciliationFlag}/resolve', [ReconciliationController::class, 'resolve'])->name('reconciliation.resolve');
        Route::post('/admin/reconciliation-flags/{reconciliationFlag}/resolve', [ReconciliationController::class, 'resolve'])->name('reconciliation-flags.resolve');

        // Appointments
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->name('appointments.update');
        Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy'])->name('appointments.destroy');
    });

    // ── Diagnostics, Backups, DR Drills, Admin Settings (Prompt 7) ────────────
    Route::middleware(['auth:sanctum', 'read-only'])->group(function () {
        Route::get('/admin/diagnostics/exports', [DiagnosticExportController::class, 'index'])->name('diagnostics.index');
        Route::get('/admin/backups', [BackupController::class, 'index'])->name('backups.index');
        Route::get('/admin/backups/{id}', [BackupController::class, 'show'])->name('backups.show');
        Route::get('/admin/dr-drills', [DrillController::class, 'index'])->name('dr-drills.index');
        Route::post('/admin/dr-drills', [DrillController::class, 'store'])->name('dr-drills.store');
        Route::get('/admin/settings', [AdminSettingsController::class, 'index'])->name('admin-settings.index');
        Route::patch('/admin/settings', [AdminSettingsController::class, 'update'])->name('admin-settings.update');
        Route::get('/admin/audit-log', [AdminSettingsController::class, 'auditLog'])->name('admin.audit-log');
    });

    // Idempotent write routes (payment + billing generate)
    Route::middleware(['auth:sanctum', 'read-only', 'idempotent'])->group(function () {
        Route::post('/orders/{order}/payment', [PaymentController::class, 'initiate'])->name('payment.initiate');
        Route::post('/orders/{order}/payment/complete', [PaymentController::class, 'complete'])->name('payment.complete');
        Route::post('/admin/bills/generate', [BillController::class, 'adminGenerate'])->name('bills.generate');
        Route::post('/bills/{bill}/refunds', [RefundController::class, 'store'])->name('refunds.store');
        Route::post('/admin/diagnostics/export', [DiagnosticExportController::class, 'trigger'])->name('diagnostics.trigger');
        Route::post('/admin/backups/trigger', [BackupController::class, 'trigger'])->name('backups.trigger');
    });
});
