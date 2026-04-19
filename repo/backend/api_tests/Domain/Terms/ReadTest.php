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

test('teacher scoped to course cannot view term detail endpoints directly', function () {
    $term1   = Term::factory()->create();
    $term2   = Term::factory()->create();
    $course1 = Course::factory()->for($term1)->create();
    $teacher = User::factory()->asScopedTeacher('course', $course1->id)->create(['status' => AccountStatus::Active]);

    $this->actingAs($teacher)->getJson("/api/v1/terms/{$term2->id}")
        ->assertForbidden();

    $this->actingAs($teacher)->getJson("/api/v1/terms/{$term1->id}")
        ->assertForbidden();
});

test('unauthenticated GET /terms returns 401', function () {
    $term = Term::factory()->create();
    $this->getJson("/api/v1/terms/{$term->id}")->assertStatus(401);
    $this->getJson('/api/v1/terms')->assertStatus(401);
});
