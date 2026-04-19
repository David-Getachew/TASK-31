<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Models\User;
use App\Repositories\EloquentNotificationWriter;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('notification writer persists a notification row', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);

    $writer = new EloquentNotificationWriter();
    $id = $writer->write(
        $user->id,
        'billing',
        'billing.generated',
        'Billing generated',
        'A bill is ready',
        ['bill_id' => 10],
    );

    expect($id)->toBeInt();

    $this->assertDatabaseHas('notifications', [
        'id' => $id,
        'user_id' => $user->id,
        'category' => 'billing',
        'type' => 'billing.generated',
    ]);
});
