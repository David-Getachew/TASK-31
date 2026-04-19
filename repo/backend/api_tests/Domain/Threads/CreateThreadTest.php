<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\ContentState;
use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\SensitiveWordRule;
use App\Models\Term;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('enrolled student can create a thread in their section', function () {
    $user    = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    Enrollment::factory()->create([
        'user_id'    => $user->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/threads', [
        'section_id' => $section->id,
        'type'       => 'discussion',
        'title'      => 'Test Thread',
        'body'       => 'Some body content',
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.title', 'Test Thread')
        ->assertJsonPath('data.state', ContentState::Visible->value);
});

test('student not enrolled in the section cannot create a thread', function () {
    $user    = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    $this->actingAs($user)->postJson('/api/v1/threads', [
        'section_id' => $section->id,
        'type'       => 'discussion',
        'title'      => 'Out of Scope',
        'body'       => 'Should fail',
    ])->assertForbidden();
});

test('teacher scoped to a different course cannot create a thread', function () {
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

    $this->actingAs($teacher)->postJson('/api/v1/threads', [
        'section_id' => $section2->id,
        'type'       => 'discussion',
        'title'      => 'Cross Scope',
        'body'       => 'Body',
    ])->assertForbidden();
});

test('sensitive word in body returns 422', function () {
    SensitiveWordRule::factory()->exact()->create(['pattern' => 'badword', 'is_active' => true]);

    $user    = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    Enrollment::factory()->create([
        'user_id'    => $user->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);

    $response = $this->actingAs($user)->postJson('/api/v1/threads', [
        'section_id' => $section->id,
        'type'       => 'discussion',
        'title'      => 'Flagged Thread',
        'body'       => 'This contains badword here',
    ]);

    $response->assertStatus(422);
});

test('unauthenticated request returns 401', function () {
    $this->postJson('/api/v1/threads', [
        'section_id' => 1,
        'type'       => 'discussion',
        'title'      => 'Thread',
        'body'       => 'Body',
    ])->assertStatus(401);
});
