<?php

declare(strict_types=1);

use App\Jobs\SendNotificationJob;
use App\Models\Notification;
use App\Models\NotificationDelivery;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    NotificationDelivery::query()->delete();
    Notification::query()->delete();
});

test('writes notification row and delivery row per recipient', function () {
    $user = User::factory()->create();

    $job = new SendNotificationJob(
        recipientIds: [$user->id],
        category: 'system',
        type: 'test.event',
        title: 'Test Title',
        body: 'Test Body',
        payload: [],
    );

    $job->handle(app(\CampusLearn\Notifications\Contracts\NotificationWriter::class));

    $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'test.event']);
    $this->assertDatabaseHas('notification_deliveries', ['delivered_at' => now()->toDateTimeString()]);
});

test('handles multiple recipients', function () {
    $user1 = User::factory()->create();
    $user2 = User::factory()->create();

    $job = new SendNotificationJob(
        recipientIds: [$user1->id, $user2->id],
        category: 'billing',
        type: 'billing.paid',
        title: 'Paid',
        body: 'Your order was paid.',
        payload: [],
    );

    $job->handle(app(\CampusLearn\Notifications\Contracts\NotificationWriter::class));

    expect(Notification::count())->toBe(2);
    expect(NotificationDelivery::count())->toBe(2);
});
