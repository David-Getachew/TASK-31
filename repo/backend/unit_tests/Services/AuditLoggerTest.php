<?php

declare(strict_types=1);

use App\Models\AuditLogEntry;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

test('record writes an audit log entry', function () {
    $user = User::factory()->create();

    $logger = app(AuditLogger::class);
    $entry  = $logger->record($user->id, 'test.action', 'user', 42, ['key' => 'value']);

    expect($entry)->toBeInstanceOf(AuditLogEntry::class)
        ->and($entry->actor_user_id)->toBe($user->id)
        ->and($entry->action)->toBe('test.action')
        ->and($entry->target_type)->toBe('user')
        ->and($entry->target_id)->toBe(42);
});

test('record uses correlation_id from request attributes', function () {
    $request = Request::create('/test');
    $request->attributes->set('correlation_id', 'test-correlation-id-123');
    app()->instance('request', $request);

    $logger = app(AuditLogger::class);
    $entry  = $logger->record(null, 'test.action', 'system', null);

    expect($entry->correlation_id)->toBe('test-correlation-id-123');
});

test('record falls back to uuid when no correlation_id in request', function () {
    $logger = app(AuditLogger::class);
    $entry  = $logger->record(null, 'test.action', 'system', null);

    expect($entry->correlation_id)->toBeString()->not->toBeEmpty();
});
