<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Post;
use App\Models\Section;
use App\Models\Term;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crossScopeEnrolledSetup(User $actorToEnroll = null): array
{
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    if ($actorToEnroll !== null) {
        Enrollment::factory()->create([
            'user_id'    => $actorToEnroll->id,
            'section_id' => $section->id,
            'status'     => EnrollmentStatus::Enrolled,
        ]);
    }

    Enrollment::factory()->create([
        'user_id'    => $student->id,
        'section_id' => $section->id,
        'status'     => EnrollmentStatus::Enrolled,
    ]);
    $thread = Thread::factory()->create(['section_id' => $section->id, 'course_id' => $course->id]);

    return [$student, $section, $thread];
}

test('student not enrolled in thread section cannot create post', function () {
    [, , $thread] = crossScopeEnrolledSetup();
    $outsider = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

    $this->actingAs($outsider)
        ->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => 'hi'])
        ->assertForbidden();
});

test('enrolled student can create a post in thread they have access to', function () {
    $actor = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    [, , $thread] = crossScopeEnrolledSetup($actor);

    $this->actingAs($actor)
        ->postJson("/api/v1/threads/{$thread->id}/posts", ['body' => 'hello'])
        ->assertStatus(201);
});

test('student not enrolled cannot create a comment on a post', function () {
    $actor = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    [, , $thread] = crossScopeEnrolledSetup($actor);

    $post = Post::factory()->create([
        'thread_id' => $thread->id,
        'author_id' => $actor->id,
    ]);

    $outsider = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

    $this->actingAs($outsider)
        ->postJson("/api/v1/posts/{$post->id}/comments", ['body' => 'x'])
        ->assertForbidden();
});

test('student not enrolled cannot index posts of a thread', function () {
    [, , $thread] = crossScopeEnrolledSetup();
    $outsider = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);

    $this->actingAs($outsider)
        ->getJson("/api/v1/threads/{$thread->id}/posts")
        ->assertForbidden();
});
