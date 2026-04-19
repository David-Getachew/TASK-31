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

test('enrolled student can view their section', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    Enrollment::factory()->create([
        'user_id'    => $student->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);

    $this->actingAs($student)->getJson("/api/v1/sections/{$section->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $section->id);
});

test('not-enrolled student cannot view a section', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    $this->actingAs($student)->getJson("/api/v1/sections/{$section->id}")
        ->assertForbidden();
});

test('teacher scoped to other course cannot view section detail', function () {
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course1 = Course::factory()->for($term)->create();
    $course2 = Course::factory()->for($term)->create();
    $section2 = Section::factory()->for($course2)->create(['term_id' => $term->id]);

    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'course',
        'scope_id'   => $course1->id,
    ]);

    $this->actingAs($teacher)->getJson("/api/v1/sections/{$section2->id}")
        ->assertForbidden();
});

test('GET /sections/{id}/roster returns section roster for admin', function () {
    $admin   = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $section = Section::factory()->create();
    $student = User::factory()->create(['status' => AccountStatus::Active]);

    Enrollment::factory()->for($student)->for($section)->create();

    $this->actingAs($admin)->getJson("/api/v1/sections/{$section->id}/roster")
        ->assertStatus(200)
        ->assertJsonStructure(['data']);
});

test('student cannot view section roster even when enrolled', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);
    Enrollment::factory()->create([
        'user_id'    => $student->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);

    $this->actingAs($student)->getJson("/api/v1/sections/{$section->id}/roster")
        ->assertForbidden();
});

test('unauthenticated section detail returns 401', function () {
    $section = Section::factory()->create();
    $this->getJson("/api/v1/sections/{$section->id}")->assertStatus(401);
});
