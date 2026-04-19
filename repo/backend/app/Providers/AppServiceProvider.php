<?php

namespace App\Providers;

use App\Policies\AppointmentPolicy;
use App\Policies\BackupPolicy;
use App\Policies\BillPolicy;
use App\Policies\CatalogItemPolicy;
use App\Policies\CommentPolicy;
use App\Policies\CoursePolicy;
use App\Policies\DiagnosticExportPolicy;
use App\Policies\EnrollmentPolicy;
use App\Policies\DrDrillPolicy;
use App\Policies\FeeCategoryPolicy;
use App\Policies\GradeItemPolicy;
use App\Policies\LedgerEntryPolicy;
use App\Policies\NotificationPolicy;
use App\Policies\OrderPolicy;
use App\Policies\PostPolicy;
use App\Policies\RefundOperatorPolicy;
use App\Policies\ReportPolicy;
use App\Policies\RosterImportPolicy;
use App\Policies\SectionPolicy;
use App\Policies\SensitiveWordRulePolicy;
use App\Policies\SystemSettingPolicy;
use App\Policies\TermPolicy;
use App\Policies\ThreadPolicy;
use App\Repositories\EloquentIdempotencyKeyStore;
use App\Services\BillingService;
use App\Services\NotificationOrchestrator;
use CampusLearn\Billing\BillScheduleCalculator;
use App\Repositories\EloquentNotificationRepository;
use App\Repositories\EloquentNotificationWriter;
use App\Repositories\EloquentScopeResolver;
use App\Services\AuthService;
use App\Services\CircuitBreakerService;
use App\Services\EncryptionHelper;
use App\Services\RequestMetricsService;
use App\Support\AuditLogger;
use CampusLearn\Moderation\MentionParser;
use CampusLearn\Auth\Contracts\ScopeResolver;
use CampusLearn\Auth\LoginThrottlePolicy;
use CampusLearn\Auth\PasswordRule;
use CampusLearn\Auth\ScopeResolutionService;
use CampusLearn\Billing\Contracts\IdempotencyKeyStore;
use CampusLearn\Billing\IdempotencyService;
use CampusLearn\Billing\PenaltyCalculator;
use CampusLearn\Billing\TaxRuleCalculator;
use CampusLearn\Moderation\EditWindowPolicy;
use CampusLearn\Moderation\ModerationStateMachine;
use CampusLearn\Moderation\SensitiveWordFilter;
use CampusLearn\Notifications\Contracts\NotificationRepository;
use CampusLearn\Notifications\Contracts\NotificationWriter;
use CampusLearn\Notifications\NotificationDispatcher;
use CampusLearn\Notifications\UnreadCounter;
use CampusLearn\Observability\CircuitBreakerPolicy;
use CampusLearn\Orders\OrderStateMachine;
use CampusLearn\Orders\PaymentSettlementPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Idempotency
        $this->app->bind(IdempotencyKeyStore::class, EloquentIdempotencyKeyStore::class);
        $this->app->singleton(IdempotencyService::class, function ($app) {
            return new IdempotencyService(
                $app->make(IdempotencyKeyStore::class),
                (int) config('campuslearn.idempotency.ttl_hours', 24),
            );
        });

        // Auth domain
        $this->app->bind(ScopeResolver::class, EloquentScopeResolver::class);
        $this->app->singleton(ScopeResolutionService::class, function ($app) {
            return new ScopeResolutionService($app->make(ScopeResolver::class));
        });
        $this->app->singleton(PasswordRule::class, function () {
            return new PasswordRule((int) config('campuslearn.auth.password_min_length', 10));
        });
        $this->app->singleton(LoginThrottlePolicy::class, function () {
            return new LoginThrottlePolicy(
                threshold:           (int) config('campuslearn.auth.login_lock_threshold', 5),
                windowMinutes:       (int) config('campuslearn.auth.login_lock_window_minutes', 15),
                lockDurationMinutes: (int) config('campuslearn.auth.login_lock_duration_minutes', 15),
            );
        });
        $this->app->singleton(AuthService::class, function ($app) {
            return new AuthService(
                passwordRule:     $app->make(PasswordRule::class),
                throttlePolicy:   $app->make(LoginThrottlePolicy::class),
                tokenTtlMinutes:  (int) config('campuslearn.auth.token_ttl_minutes', 720),
            );
        });

        // Billing
        $this->app->singleton(TaxRuleCalculator::class);
        $this->app->singleton(PenaltyCalculator::class, function () {
            return new PenaltyCalculator(
                (int) config('campuslearn.billing.penalty_grace_days', 10),
                (int) config('campuslearn.billing.penalty_rate_bps', 500),
            );
        });
        $this->app->singleton(BillScheduleCalculator::class, function () {
            return new BillScheduleCalculator(
                (int) config('campuslearn.billing.recurring_day_of_month', 1),
                (int) config('campuslearn.billing.recurring_hour', 2),
            );
        });

        // Orders
        $this->app->singleton(OrderStateMachine::class);
        $this->app->singleton(PaymentSettlementPolicy::class);

        // Moderation
        $this->app->singleton(ModerationStateMachine::class);
        $this->app->singleton(SensitiveWordFilter::class);
        $this->app->singleton(EditWindowPolicy::class, function () {
            return new EditWindowPolicy(
                (int) config('campuslearn.moderation.edit_window_minutes', 15),
            );
        });

        // Observability
        $this->app->singleton(CircuitBreakerPolicy::class, function () {
            return new CircuitBreakerPolicy(
                tripThresholdBps:  (int) config('campuslearn.observability.circuit_trip_bps', 200),
                resetThresholdBps: (int) config('campuslearn.observability.circuit_reset_bps', 100),
            );
        });
        $this->app->singleton(CircuitBreakerService::class, function ($app) {
            $windowSeconds = (int) config('campuslearn.observability.circuit_window_seconds', 300);
            return new CircuitBreakerService(
                $app->make(CircuitBreakerPolicy::class),
                $windowSeconds,
            );
        });
        $this->app->singleton(RequestMetricsService::class);

        // Encryption
        $this->app->singleton(EncryptionHelper::class);

        // Audit + Mentions
        $this->app->singleton(AuditLogger::class);
        $this->app->singleton(MentionParser::class);
        $this->app->singleton(NotificationOrchestrator::class);

        // Notifications
        $this->app->bind(NotificationWriter::class, EloquentNotificationWriter::class);
        $this->app->bind(NotificationRepository::class, EloquentNotificationRepository::class);
        $this->app->singleton(NotificationDispatcher::class, function ($app) {
            return new NotificationDispatcher($app->make(NotificationWriter::class));
        });
        $this->app->singleton(UnreadCounter::class, function ($app) {
            return new UnreadCounter($app->make(NotificationRepository::class));
        });
    }

    public function boot(): void
    {
        Gate::policy(\App\Models\Post::class, PostPolicy::class);
        Gate::policy(\App\Models\GradeItem::class, GradeItemPolicy::class);
        Gate::policy(\App\Models\Order::class, OrderPolicy::class);
        Gate::policy(\App\Models\Bill::class, BillPolicy::class);
        Gate::policy(\App\Models\Refund::class, RefundOperatorPolicy::class);
        Gate::policy(\App\Models\Enrollment::class, EnrollmentPolicy::class);
        Gate::policy(\App\Models\LedgerEntry::class, LedgerEntryPolicy::class);
        Gate::policy(\App\Models\DiagnosticExport::class, DiagnosticExportPolicy::class);
        Gate::policy(\App\Models\Thread::class, ThreadPolicy::class);
        Gate::policy(\App\Models\Comment::class, CommentPolicy::class);
        Gate::policy(\App\Models\Report::class, ReportPolicy::class);
        Gate::policy(\App\Models\Appointment::class, AppointmentPolicy::class);
        Gate::policy(\App\Models\RosterImport::class, RosterImportPolicy::class);
        Gate::policy(\App\Models\Notification::class, NotificationPolicy::class);
        Gate::policy(\App\Models\CatalogItem::class, CatalogItemPolicy::class);
        Gate::policy(\App\Models\FeeCategory::class, FeeCategoryPolicy::class);
        Gate::policy(\App\Models\SensitiveWordRule::class, SensitiveWordRulePolicy::class);
        Gate::policy(\App\Models\BackupJob::class, BackupPolicy::class);
        Gate::policy(\App\Models\DrDrillRecord::class, DrDrillPolicy::class);
        Gate::policy(\App\Models\SystemSetting::class, SystemSettingPolicy::class);
        Gate::policy(\App\Models\Section::class, SectionPolicy::class);
        Gate::policy(\App\Models\Term::class, TermPolicy::class);
        Gate::policy(\App\Models\Course::class, CoursePolicy::class);
    }
}
