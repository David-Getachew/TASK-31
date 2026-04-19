<?php

use App\Models\User;
use App\Models\Section;
use App\Models\GradeItem;

// Grade item publication — teacher-scope enforcement.

it('teacher can publish grade item in assigned section', function () {
    $teacher = User::factory()->asTeacher()->create();
    $section = Section::factory()->create();
    \App\Models\UserRole::create(['user_id' => $teacher->id, 'role' => 'teacher', 'scope_type' => 'section', 'scope_id' => $section->id]);
    $item = GradeItem::factory()->for($section)->draft()->create();

    $this->actingAs($teacher)
        ->postJson("/api/v1/sections/{$section->id}/grade-items/{$item->id}/publish", [])
        ->assertOk()
        ->assertJsonPath('data.state', 'published');
});

it('teacher cannot publish grade item in unassigned section', function () {
    $teacher  = User::factory()->asTeacher()->create();
    $section  = Section::factory()->create();
    $other    = Section::factory()->create();
    \App\Models\UserRole::create(['user_id' => $teacher->id, 'role' => 'teacher', 'scope_type' => 'section', 'scope_id' => $section->id]);
    $item = GradeItem::factory()->for($other)->draft()->create();

    $this->actingAs($teacher)
        ->postJson("/api/v1/sections/{$other->id}/grade-items/{$item->id}/publish", [])
        ->assertForbidden();
});

it('admin can publish grade item in any section', function () {
    $admin   = User::factory()->asAdmin()->create();
    $section = Section::factory()->create();
    $item    = GradeItem::factory()->for($section)->draft()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/sections/{$section->id}/grade-items/{$item->id}/publish", [])
        ->assertOk()
        ->assertJsonPath('data.state', 'published');
});

it('student cannot publish grade items', function () {
    $student = User::factory()->asStudent()->create();
    $section = Section::factory()->create();
    $item    = GradeItem::factory()->for($section)->draft()->create();

    $this->actingAs($student)
        ->postJson("/api/v1/sections/{$section->id}/grade-items/{$item->id}/publish", [])
        ->assertForbidden();
});

it('already published grade item cannot be published again', function () {
    $admin   = User::factory()->asAdmin()->create();
    $section = Section::factory()->create();
    $item    = GradeItem::factory()->for($section)->published()->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/sections/{$section->id}/grade-items/{$item->id}/publish", [])
        ->assertStatus(409)
        ->assertJsonPath('error.code', 'INTERNAL_ERROR');
});
