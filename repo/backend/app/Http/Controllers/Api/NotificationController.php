<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkMarkReadRequest;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Http\Responses\ApiEnvelope;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $userId     = $request->user()->id;
        $category   = $request->query('category');
        $unreadOnly = $request->boolean('unread_only');
        $perPage    = (int) $request->integer('per_page', 20);

        $notifications = $this->notificationService->list($userId, $category, $unreadOnly, $perPage);
        return ApiEnvelope::data($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $counts = $this->notificationService->unreadCount($request->user()->id);
        return ApiEnvelope::data($counts['by_category'] ?? []);
    }

    public function markRead(BulkMarkReadRequest $request): JsonResponse
    {
        $userId = $request->user()->id;

        if ($request->filled('ids')) {
            $this->notificationService->markManyRead($userId, $request->input('ids'));
        } elseif ($request->filled('category')) {
            $this->notificationService->markAllRead($userId, $request->input('category'));
        } else {
            $this->notificationService->markAllRead($userId, null);
        }

        return ApiEnvelope::data(['marked' => true]);
    }

    public function markOneRead(Request $request, int $id): JsonResponse
    {
        $this->notificationService->markOneRead($request->user()->id, $id);
        return ApiEnvelope::data(['marked' => true]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $prefs = $this->notificationService->getPreferences($request->user()->id);
        return ApiEnvelope::data($prefs);
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): JsonResponse
    {
        $prefs = $this->notificationService->updatePreferences(
            $request->user()->id,
            $request->input('preferences'),
        );
        return ApiEnvelope::data($prefs);
    }
}
