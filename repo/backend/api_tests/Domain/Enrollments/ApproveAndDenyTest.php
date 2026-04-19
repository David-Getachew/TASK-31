<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
});

test('staff can approve an enrollment', function () {
    $staff    = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $student  = User::factory()->create(['status' => AccountStatus::Active]);
    $term     = Term::factory()->create();
    $course   = Course::factory()->for($term)->create();
    $section  = Section::factory()->for($course)->create(['term_id' => $term->id]);

    $enrollment = Enrollment::create([
        'user_id'     => $student->id,
        'section_id'  => $section->id,
        'status'      => EnrollmentStatus::Withdrawn,
        'enrolled_at' => now(),
    ]);

    $response = $this->actingAs($staff)->postJson("/api/v1/enrollments/{$enrollment->id}/approve");
    $response->assertStatus(200)
        ->assertJsonPath('data.status', EnrollmentStatus::Enrolled->value);
});

test('staff can deny an enrollment', function () {
    $staff    = User::factory()->asRegistrar()->create(['status' => AccountStatus::Active]);
    $student  = User::factory()->create(['status' => AccountStatus::Active]);
    $term     = Term::factory()->create();
    $course   = Course::factory()->for($term)->create();
    $section  = Section::factory()->for($course)->create(['term_id' => $term->id]);

    $enrollment = Enrollment::create([
        'user_id'    => $student->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
        'enrolled_at' => now(),
    ]);

    $response = $this->actingAs($staff)->postJson("/api/v1/enrollments/{$enrollment->id}/deny");
    $response->assertStatus(200)
        ->assertJsonPath('data.status', EnrollmentStatus::Withdrawn->value);
});
