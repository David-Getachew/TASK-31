<?php

declare(strict_types=1);

use App\Enums\BillScheduleStatus;
use App\Jobs\RecurringBillingJob;
use App\Models\BillSchedule;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

test('handle generates bills for active schedules when run on configured day', function () {
    $dayOfMonth = (int) config('campuslearn.billing.recurring_day_of_month', 1);
    Carbon::setTestNow(Carbon::now()->startOfMonth()->addDays($dayOfMonth - 1)->setTime(2, 0));

    $user     = User::factory()->create();
    $category = FeeCategory::factory()->create(['is_taxable' => false]);
    BillSchedule::factory()->for($user)->for($category)->create([
        'amount_cents'  => 3000,
        'schedule_type' => 'recurring_monthly',
        'status'        => BillScheduleStatus::Active,
        'start_on'      => now()->toDateString(),
        'next_run_on'   => now()->toDateString(),
    ]);

    (new RecurringBillingJob())->handle(app(\App\Services\BillingService::class));

    $this->assertDatabaseHas('bills', ['user_id' => $user->id, 'type' => 'recurring']);

    Carbon::setTestNow();
});

test('handle does nothing on a non-configured day of month', function () {
    $dayOfMonth = (int) config('campuslearn.billing.recurring_day_of_month', 1);
    $nonDay = ($dayOfMonth === 15) ? 10 : 15;
    Carbon::setTestNow(Carbon::now()->setDay($nonDay)->setTime(2, 0));

    $user     = User::factory()->create();
    $category = FeeCategory::factory()->create(['is_taxable' => false]);
    BillSchedule::factory()->for($user)->for($category)->create([
        'amount_cents'  => 3000,
        'schedule_type' => 'recurring_monthly',
        'status'        => BillScheduleStatus::Active,
        'start_on'      => now()->toDateString(),
        'next_run_on'   => now()->toDateString(),
    ]);

    (new RecurringBillingJob())->handle(app(\App\Services\BillingService::class));

    $this->assertDatabaseMissing('bills', ['user_id' => $user->id, 'type' => 'recurring']);

    Carbon::setTestNow();
});

test('handle advances next_run_on after generating recurring bill', function () {
    $dayOfMonth = (int) config('campuslearn.billing.recurring_day_of_month', 1);
    Carbon::setTestNow(Carbon::now()->startOfMonth()->addDays($dayOfMonth - 1)->setTime(2, 0));

    $user     = User::factory()->create();
    $category = FeeCategory::factory()->create(['is_taxable' => false]);
    $schedule = BillSchedule::factory()->for($user)->for($category)->create([
        'amount_cents'  => 5000,
        'schedule_type' => 'recurring_monthly',
        'status'        => BillScheduleStatus::Active,
        'start_on'      => now()->subMonth()->toDateString(),
        'next_run_on'   => now()->toDateString(),
    ]);

    (new RecurringBillingJob())->handle(app(\App\Services\BillingService::class));

    expect($schedule->fresh()->next_run_on)->not->toBe(now()->toDateString());

    Carbon::setTestNow();
});
