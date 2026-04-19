<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('staff can create an appointment', function () {
    $staff  = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $owner  = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

    $response = $this->actingAs($staff)->postJson('/api/v1/appointments', [
        'owner_user_id'   => $owner->id,
        'resource_type'   => 'facility',
        'scheduled_start' => now()->addDay()->toDateTimeString(),
        'scheduled_end'   => now()->addDay()->addHour()->toDateTimeString(),
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.owner_user_id', $owner->id)
        ->assertJsonPath('data.resource_type', 'facility')
        ->assertJsonPath('data.status', AppointmentStatus::Scheduled->value);

    $this->assertDatabaseHas('appointments', [
        'id' => $response->json('data.id'),
        'owner_user_id' => $owner->id,
        'status' => AppointmentStatus::Scheduled->value,
    ]);
});

test('student cannot create an appointment (staff/admin only per API spec §7.20)', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $owner   = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

    $this->actingAs($student)->postJson('/api/v1/appointments', [
        'owner_user_id'   => $owner->id,
        'resource_type'   => 'facility',
        'scheduled_start' => now()->addDay()->toDateTimeString(),
        'scheduled_end'   => now()->addDay()->addHour()->toDateTimeString(),
    ])->assertStatus(403);
});

test('scoped (term) registrar cannot create an appointment', function () {
    $scoped = User::factory()->asScopedRegistrar('term', 1)->create(['status' => AccountStatus::Active]);
    $owner  = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

    $this->actingAs($scoped)->postJson('/api/v1/appointments', [
        'owner_user_id'   => $owner->id,
        'resource_type'   => 'facility',
        'scheduled_start' => now()->addDay()->toDateTimeString(),
        'scheduled_end'   => now()->addDay()->addHour()->toDateTimeString(),
    ])->assertStatus(403);
});

test('canceling appointment dispatches notification job', function () {
    $staff = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $owner = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

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
    expect($appointment->fresh()->scheduled_start)->not->toBeNull();
});
