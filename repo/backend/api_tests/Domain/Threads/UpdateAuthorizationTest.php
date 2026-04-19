<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Section;
use App\Models\Term;
use App\Models\Thread;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function threadUpdateAuthSetup(): array
{
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);
    $author  = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    Enrollment::factory()->create([
        'user_id'    => $author->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);
    $thread = Thread::factory()->create([
        'section_id' => $section->id,
        'course_id'  => $course->id,
        'author_id'  => $author->id,
    ]);

    return [$term, $course, $section, $author, $thread];
}

test('teacher scoped to different course cannot update thread', function () {
    [, , , , $thread] = threadUpdateAuthSetup();
    $otherCourse = Course::factory()->for($thread->section->term)->create();
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'course',
        'scope_id'   => $otherCourse->id,
    ]);

    $this->actingAs($teacher)
        ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Hacked'])
        ->assertForbidden();
});

test('unrelated student cannot update someone elses thread', function () {
    [, , , , $thread] = threadUpdateAuthSetup();
    $outsider = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

    $this->actingAs($outsider)
        ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Nope'])
        ->assertForbidden();
});

test('teacher in same course can moderate thread update', function () {
    [, $course, , , $thread] = threadUpdateAuthSetup();
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'course',
        'scope_id'   => $course->id,
    ]);

    $this->actingAs($teacher)
        ->patchJson("/api/v1/threads/{$thread->id}", ['title' => 'Moderated'])
        ->assertOk();
});
