<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Http\Controllers\Api\NotificationController;
use App\Models\Notification;
use App\Models\User;
use App\Repositories\EloquentNotificationRepository;
use App\Services\NotificationService;
use CampusLearn\Notifications\UnreadCounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('notification controller markOneRead delegates to service and returns marked response', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $notification = Notification::factory()->for($user)->create(['read_at' => null]);

    $service = new NotificationService(new UnreadCounter(new EloquentNotificationRepository()));

    $controller = new NotificationController($service);

    $request = Request::create("/api/v1/notifications/{$notification->id}/read", 'POST');
    $request->setUserResolver(fn () => $user);

    $response = $controller->markOneRead($request, $notification->id);

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getContent())->toContain('"marked":true');
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('notification controller unreadCount returns category map from service', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    Notification::factory()->for($user)->create(['category' => 'billing', 'read_at' => null]);
    Notification::factory()->for($user)->create(['category' => 'billing', 'read_at' => null]);
    Notification::factory()->for($user)->create(['category' => 'system', 'read_at' => null]);

    $service = new NotificationService(new UnreadCounter(new EloquentNotificationRepository()));

    $controller = new NotificationController($service);

    $request = Request::create('/api/v1/notifications/unread-count', 'GET');
    $request->setUserResolver(fn () => $user);

    $response = $controller->unreadCount($request);

    expect($response->getStatusCode())->toBe(200);
    expect((string) $response->getContent())->toContain('"billing":2');
    expect((string) $response->getContent())->toContain('"system":1');
});
