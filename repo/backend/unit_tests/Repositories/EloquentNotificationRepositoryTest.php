<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\EloquentNotificationRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notification repository returns unread counts by category', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);

    Notification::factory()->for($user)->create(['category' => 'billing', 'read_at' => null]);
    Notification::factory()->for($user)->create(['category' => 'billing', 'read_at' => null]);
    Notification::factory()->for($user)->create(['category' => 'system', 'read_at' => null]);
    Notification::factory()->for($user)->create(['category' => 'system', 'read_at' => now()]);

    $repo = new EloquentNotificationRepository();
    $counts = $repo->unreadCountsByCategory($user->id);

    expect($counts['billing'])->toBe(2);
    expect($counts['system'])->toBe(1);
});
