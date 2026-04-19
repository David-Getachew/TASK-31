<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\BillScheduleStatus;
use App\Models\BillSchedule;
use App\Models\FeeCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('artisan recurring command generates bills for active schedules on configured day', function () {
    $dayOfMonth = (int) config('campuslearn.billing.recurring_day_of_month', 1);
    Carbon::setTestNow(Carbon::now()->startOfMonth()->addDays($dayOfMonth - 1)->setTime(2, 0));

    $user     = User::factory()->create(['status' => AccountStatus::Active]);
    $category = FeeCategory::factory()->create(['is_taxable' => false]);
    BillSchedule::factory()->for($user)->for($category)->create([
        'amount_cents'  => 4000,
        'schedule_type' => 'recurring_monthly',
        'status'        => BillScheduleStatus::Active,
        'start_on'      => now()->subMonth()->toDateString(),
        'next_run_on'   => now()->toDateString(),
    ]);

    Artisan::call('campuslearn:billing:recurring');

    $this->assertDatabaseHas('bills', [
        'user_id' => $user->id,
        'type'    => 'recurring',
    ]);

    Carbon::setTestNow();
});

test('artisan recurring command skips generation on non-configured day', function () {
    $dayOfMonth = (int) config('campuslearn.billing.recurring_day_of_month', 1);
    $nonDay = ($dayOfMonth === 15) ? 10 : 15;
    Carbon::setTestNow(Carbon::now()->setDay($nonDay)->setTime(2, 0));

    $user     = User::factory()->create(['status' => AccountStatus::Active]);
    $category = FeeCategory::factory()->create(['is_taxable' => false]);
    BillSchedule::factory()->for($user)->for($category)->create([
        'amount_cents'  => 4000,
        'schedule_type' => 'recurring_monthly',
        'status'        => BillScheduleStatus::Active,
        'start_on'      => now()->subMonth()->toDateString(),
        'next_run_on'   => now()->toDateString(),
    ]);

    Artisan::call('campuslearn:billing:recurring');

    $this->assertDatabaseMissing('bills', [
        'user_id' => $user->id,
        'type'    => 'recurring',
    ]);

    Carbon::setTestNow();
});
