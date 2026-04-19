<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\GradeItemState;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use App\Models\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('section-scoped teacher can create a grade item', function () {
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'section',
        'scope_id'   => $section->id,
    ]);

    $response = $this->actingAs($teacher)->postJson("/api/v1/sections/{$section->id}/grade-items", [
        'title'     => 'Midterm Exam',
        'max_score' => 100,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.state', GradeItemState::Draft->value);
});

test('section-scoped teacher can publish grade item they created', function () {
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'section',
        'scope_id'   => $section->id,
    ]);

    $createResponse = $this->actingAs($teacher)->postJson("/api/v1/sections/{$section->id}/grade-items", [
        'title'     => 'Final Exam',
        'max_score' => 200,
    ]);

    $gradeItemId = $createResponse->json('data.id');

    $publishResponse = $this->actingAs($teacher)->postJson(
        "/api/v1/sections/{$section->id}/grade-items/{$gradeItemId}/publish"
    );

    $publishResponse->assertStatus(200)
        ->assertJsonPath('data.state', GradeItemState::Published->value);
});

test('student cannot create a grade item', function () {
    $student = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create(['term_id' => $term->id]);

    $this->actingAs($student)->postJson("/api/v1/sections/{$section->id}/grade-items", [
        'title'     => 'Blocked',
        'max_score' => 100,
    ])->assertForbidden();
});

test('out-of-scope teacher cannot create a grade item', function () {
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course1 = Course::factory()->for($term)->create();
    $course2 = Course::factory()->for($term)->create();
    $section1 = Section::factory()->for($course1)->create(['term_id' => $term->id]);
    $section2 = Section::factory()->for($course2)->create(['term_id' => $term->id]);

    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'section',
        'scope_id'   => $section1->id,
    ]);

    $this->actingAs($teacher)->postJson("/api/v1/sections/{$section2->id}/grade-items", [
        'title'     => 'Forbidden',
        'max_score' => 100,
    ])->assertForbidden();
});

test('out-of-scope teacher cannot update a grade item', function () {
    $teacher = User::factory()->create(['status' => AccountStatus::Active]);
    $admin   = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course1 = Course::factory()->for($term)->create();
    $course2 = Course::factory()->for($term)->create();
    $section1 = Section::factory()->for($course1)->create(['term_id' => $term->id]);
    $section2 = Section::factory()->for($course2)->create(['term_id' => $term->id]);

    UserRole::create([
        'user_id'    => $teacher->id,
        'role'       => RoleName::Teacher,
        'scope_type' => 'section',
        'scope_id'   => $section1->id,
    ]);

    // Admin creates the grade item in section2
    $create = $this->actingAs($admin)->postJson("/api/v1/sections/{$section2->id}/grade-items", [
        'title'     => 'Out of scope target',
        'max_score' => 100,
    ])->json('data.id');

    $this->actingAs($teacher)->patchJson("/api/v1/sections/{$section2->id}/grade-items/{$create}", [
        'title' => 'Hijack attempt',
    ])->assertForbidden();
});
