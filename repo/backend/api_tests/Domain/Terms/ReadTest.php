<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('GET /terms returns paginated list for admin', function () {
    $user = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    Term::factory()->count(3)->create();

    $response = $this->actingAs($user)->getJson('/api/v1/terms');

    $response->assertStatus(200)
        ->assertJsonStructure(['data']);
    expect(count($response->json('data')))->toBeGreaterThanOrEqual(3);
});

test('admin can view term detail', function () {
    $user = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $term = Term::factory()->create(['name' => 'Spring 2025']);

    $this->actingAs($user)->getJson("/api/v1/terms/{$term->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Spring 2025');
});

test('enrolled student can view their term detail', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create(['name' => 'Fall 2026']);
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);
    Enrollment::factory()->create([
        'user_id'    => $student->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);

    $this->actingAs($student)->getJson("/api/v1/terms/{$term->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.name', 'Fall 2026');
});

test('student not associated with a term gets 403 on term detail', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();

    $this->actingAs($student)->getJson("/api/v1/terms/{$term->id}")
        ->assertForbidden();
});

test('teacher scoped to course of a different term cannot view other term', function () {
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    $term1   = Term::factory()->create();
    $term2   = Term::factory()->create();
    $course1 = Course::factory()->for($term1)->create();

    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'course',
        'scope_id'   => $course1->id,
    ]);

    $this->actingAs($teacher)->getJson("/api/v1/terms/{$term2->id}")
        ->assertForbidden();

    $this->actingAs($teacher)->getJson("/api/v1/terms/{$term1->id}")
        ->assertOk();
});

test('unauthenticated GET /terms returns 401', function () {
    $term = Term::factory()->create();
    $this->getJson("/api/v1/terms/{$term->id}")->assertStatus(401);
    $this->getJson('/api/v1/terms')->assertStatus(401);
});
