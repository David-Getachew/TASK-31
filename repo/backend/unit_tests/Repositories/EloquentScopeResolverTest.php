<?php

declare(strict_types=1);

use App\Enums\RoleName;
use App\Repositories\EloquentScopeResolver;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('scope resolver returns active grants for user', function () {
    $teacher = User::factory()->asTeacher()->create();

    $resolver = new EloquentScopeResolver();
    $grants = $resolver->activeGrantsFor($teacher->id);

    expect($grants)->not->toBeEmpty();
    expect($grants[0]->role)->toBe(RoleName::Teacher);
    expect($grants[0]->scopeType->value)->toBe('global');
});
