<?php

declare(strict_types=1);

use App\Enums\AccountStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Thread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('POST /admin/threads/{thread}/posts/{post}/hide returns 404 when post does not belong to thread', function () {
    $admin   = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $author  = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $thread1 = Thread::factory()->for($author, 'author')->create();
    $thread2 = Thread::factory()->for($author, 'author')->create();
    $post    = Post::factory()->for($thread2)->for($author, 'author')->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/threads/{$thread1->id}/posts/{$post->id}/hide", ['reason' => 'cross-thread test'])
        ->assertStatus(404);
});

test('POST /admin/threads/{thread}/posts/{post}/restore returns 404 when post does not belong to thread', function () {
    $admin   = User::factory()->asAdmin()->create(['status' => AccountStatus::Active]);
    $author  = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $thread1 = Thread::factory()->for($author, 'author')->create();
    $thread2 = Thread::factory()->for($author, 'author')->create();
    $post    = Post::factory()->for($thread2)->for($author, 'author')->create();

    $this->actingAs($admin)
        ->postJson("/api/v1/admin/threads/{$thread1->id}/posts/{$post->id}/restore", ['reason' => 'cross-thread restore'])
        ->assertStatus(404);
});

test('POST /posts/{post}/comments/{comment}/reports returns 404 when comment does not belong to post', function () {
    $reporter = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $author   = User::factory()->asStudent()->create(['status' => AccountStatus::Active]);
    $thread   = Thread::factory()->for($author, 'author')->create();
    $post1    = Post::factory()->for($thread)->for($author, 'author')->create();
    $post2    = Post::factory()->for($thread)->for($author, 'author')->create();
    $comment  = Comment::factory()->for($post2)->for($author, 'author')->create();

    $this->actingAs($reporter)
        ->postJson("/api/v1/posts/{$post1->id}/comments/{$comment->id}/reports", [
            'reason'  => 'spam',
            'details' => 'unrelated comment',
        ])
        ->assertStatus(404);
});
