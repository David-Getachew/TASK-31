<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('artisan penalty command applies penalty to past-due bill', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $bill = Bill::factory()->for($user)->create([
        'status'         => BillStatus::Open,
        'total_cents'    => 10000,
        'paid_cents'     => 0,
        'refunded_cents' => 0,
        'due_on'         => now()->subDays(15)->toDateString(),
    ]);

    Artisan::call('campuslearn:billing:penalty');

    $this->assertDatabaseHas('bills', [
        'user_id' => $user->id,
        'type'    => 'penalty',
    ]);
});

test('penalty is idempotent on same bill same day', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $bill = Bill::factory()->for($user)->create([
        'status'         => BillStatus::Open,
        'total_cents'    => 10000,
        'paid_cents'     => 0,
        'refunded_cents' => 0,
        'due_on'         => now()->subDays(15)->toDateString(),
    ]);

    Artisan::call('campuslearn:billing:penalty');
    Artisan::call('campuslearn:billing:penalty');

    expect(\App\Models\PenaltyJob::where('bill_id', $bill->id)->count())->toBe(1);
});

test('penalty does not apply within grace period', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $bill = Bill::factory()->for($user)->create([
        'status'         => BillStatus::Open,
        'total_cents'    => 10000,
        'paid_cents'     => 0,
        'refunded_cents' => 0,
        'due_on'         => now()->subDays(3)->toDateString(),
    ]);

    Artisan::call('campuslearn:billing:penalty');

    $this->assertDatabaseMissing('bills', [
        'user_id' => $user->id,
        'type'    => 'penalty',
    ]);
});
