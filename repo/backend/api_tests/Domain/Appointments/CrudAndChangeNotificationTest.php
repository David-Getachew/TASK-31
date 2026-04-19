<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

test('staff can create an appointment', function () {
    $staff  = User::factory()->create(['status' => AccountStatus::Active]);
    $owner  = User::factory()->create(['status' => AccountStatus::Active]);

    $response = $this->actingAs($staff)->postJson('/api/v1/appointments', [
        'owner_user_id'   => $owner->id,
        'resource_type'   => 'facility',
        'scheduled_start' => now()->addDay()->toDateTimeString(),
        'scheduled_end'   => now()->addDay()->addHour()->toDateTimeString(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.status', AppointmentStatus::Scheduled->value);
});

test('canceling appointment dispatches notification job', function () {
    $staff = User::factory()->create(['status' => AccountStatus::Active]);
    $owner = User::factory()->create(['status' => AccountStatus::Active]);

    $appointment = Appointment::create([
        'owner_user_id'   => $owner->id,
        'resource_type'   => 'registrar_meeting',
        'scheduled_start' => now()->addDay(),
        'scheduled_end'   => now()->addDay()->addHour(),
        'status'          => AppointmentStatus::Scheduled,
        'created_by'      => $staff->id,
    ]);

    $this->actingAs($staff)->deleteJson("/api/v1/appointments/{$appointment->id}")
        ->assertStatus(204);

    expect($appointment->fresh()->status)->toBe(AppointmentStatus::Canceled);
});
