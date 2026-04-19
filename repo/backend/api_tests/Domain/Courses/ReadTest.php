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

test('admin can view any course detail', function () {
    $admin  = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $term   = Term::factory()->create();
    $course = Course::factory()->for($term)->create(['code' => 'CS101']);

    $this->actingAs($admin)->getJson("/api/v1/courses/{$course->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.code', 'CS101');
});

test('enrolled student can view their course detail', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    Enrollment::factory()->create([
        'user_id'    => $student->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);

    $this->actingAs($student)->getJson("/api/v1/courses/{$course->id}")
        ->assertStatus(200)
        ->assertJsonPath('data.id', $course->id);
});

test('unrelated student gets 403 on course detail', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();

    $this->actingAs($student)->getJson("/api/v1/courses/{$course->id}")
        ->assertForbidden();
});

test('teacher granted on other course cannot view course detail', function () {
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course1 = Course::factory()->for($term)->create();
    $course2 = Course::factory()->for($term)->create();

    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'course',
        'scope_id'   => $course1->id,
    ]);

    $this->actingAs($teacher)->getJson("/api/v1/courses/{$course2->id}")
        ->assertForbidden();

    $this->actingAs($teacher)->getJson("/api/v1/courses/{$course1->id}")
        ->assertOk();
});

test('unauthenticated course detail returns 401', function () {
    $term   = Term::factory()->create();
    $course = Course::factory()->for($term)->create();
    $this->getJson("/api/v1/courses/{$course->id}")->assertStatus(401);
});
