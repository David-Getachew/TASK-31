<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Enums\RoleName;
use App\Models\Course;
use App\Models\Section;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

test('registrar can import roster CSV', function () {
    $user    = User::factory()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create([
        'term_id'      => $term->id,
        'section_code' => 'CS101-A',
    ]);

    \App\Models\UserRole::create([
        'user_id'    => $user->id,
        'role'       => RoleName::Registrar,
        'scope_type' => null,
        'scope_id'   => null,
    ]);

    $csv = "email,name,section_code\nnewstudent@example.com,New Student,CS101-A\n";
    $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

    $response = $this->actingAs($user)->postJson("/api/v1/terms/{$term->id}/roster-imports", [
        'file' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.success_count', 1)
        ->assertJsonPath('data.error_count', 0);
});

test('non-registrar cannot import roster', function () {
    $user = User::factory()->create(['status' => AccountStatus::Active]);
    $term = Term::factory()->create();

    $csv  = "email,name,section_code\ntest@example.com,Test,CS101\n";
    $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

    $response = $this->actingAs($user)->postJson("/api/v1/terms/{$term->id}/roster-imports", [
        'file' => $file,
    ]);

    $response->assertStatus(403);
});

test('scoped registrar can import roster for their assigned term', function () {
    $user    = User::factory()->create(['status' => AccountStatus::Active]);
    $term    = Term::factory()->create();
    $course  = Course::factory()->for($term)->create();
    $section = Section::factory()->for($course)->create([
        'term_id'      => $term->id,
        'section_code' => 'CS202-A',
    ]);

    \App\Models\UserRole::create([
        'user_id'    => $user->id,
        'role'       => RoleName::Registrar,
        'scope_type' => 'term',
        'scope_id'   => $term->id,
    ]);

    $csv = "email,name,section_code\nstudent@example.com,Scoped Student,CS202-A\n";
    $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

    $response = $this->actingAs($user)->postJson("/api/v1/terms/{$term->id}/roster-imports", [
        'file' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonPath('data.success_count', 1);
});

test('scoped registrar CANNOT import roster for a different term (scope bypass closed)', function () {
    $user     = User::factory()->create(['status' => AccountStatus::Active]);
    $ownTerm  = Term::factory()->create();
    $otherTerm = Term::factory()->create();

    \App\Models\UserRole::create([
        'user_id'    => $user->id,
        'role'       => RoleName::Registrar,
        'scope_type' => 'term',
        'scope_id'   => $ownTerm->id,
    ]);

    $csv  = "email,name,section_code\nstudent@example.com,Cross Term,X-1\n";
    $file = UploadedFile::fake()->createWithContent('roster.csv', $csv);

    $response = $this->actingAs($user)->postJson("/api/v1/terms/{$otherTerm->id}/roster-imports", [
        'file' => $file,
    ]);

    $response->assertStatus(403);
});

test('registrar service canImport refuses cross-term execution even if controller bypassed', function () {
    $user     = User::factory()->create(['status' => AccountStatus::Active]);
    $ownTerm  = Term::factory()->create();
    $otherTerm = Term::factory()->create();

    \App\Models\UserRole::create([
        'user_id'    => $user->id,
        'role'       => RoleName::Registrar,
        'scope_type' => 'term',
        'scope_id'   => $ownTerm->id,
    ]);

    $service = app(\App\Services\RosterImportService::class);

    $csvPath = tempnam(sys_get_temp_dir(), 'roster');
    file_put_contents($csvPath, "email,name,section_code\na@b.c,A,X\n");

    expect(fn () => $service->import($user, $otherTerm, 'roster.csv', $csvPath))
        ->toThrow(\RuntimeException::class, 'not authorized');

    @unlink($csvPath);
});
