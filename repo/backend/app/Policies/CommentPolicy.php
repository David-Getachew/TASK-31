<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Models\Comment;
use App\Models\Enrollment;
use App\Models\Post;
use App\Models\User;
use CampusLearn\Auth\ScopeContext;
use CampusLearn\Auth\ScopeResolutionService;
use CampusLearn\Moderation\EditWindowPolicy;

final class CommentPolicy
{
    public function __construct(
        private readonly EditWindowPolicy $editWindowPolicy,
        private readonly ScopeResolutionService $scopeService,
    ) {
    }

    public function create(User $user, ?Post $post = null): bool
    {
        if ($post === null) {
            return false;
        }
        $thread = $post->thread;
        if ($thread === null) {
            return false;
        }
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        $ancestry = [
            'course'  => $thread->course_id,
            'section' => $thread->section_id,
        ];
        if ($this->scopeService->canPerform(
            $user->id,
            RoleName::Teacher,
            ScopeContext::course($thread->course_id),
            $ancestry,
        )) {
            return true;
        }
        if ($thread->section_id !== null && $this->scopeService->canPerform(
            $user->id,
            RoleName::Teacher,
            ScopeContext::section($thread->section_id),
            $ancestry,
        )) {
            return true;
        }
        if ($thread->section_id !== null) {
            return Enrollment::where('user_id', $user->id)
                ->where('section_id', $thread->section_id)
                ->where('status', EnrollmentStatus::Enrolled)
                ->exists();
        }
        return Enrollment::where('user_id', $user->id)
            ->whereHas('section', fn ($q) => $q->where('course_id', $thread->course_id))
            ->where('status', EnrollmentStatus::Enrolled)
            ->exists();
    }

    public function update(User $user, Comment $comment): bool
    {
        if ($comment->author_id !== $user->id) {
            return false;
        }
        return $this->editWindowPolicy->canAuthorEdit(
            $comment->created_at->toDateTimeImmutable(),
            now()->toDateTimeImmutable(),
        );
    }

    public function moderate(User $user, Comment $comment): bool
    {
        if ($this->scopeService->canPerform($user->id, RoleName::Administrator, ScopeContext::global())) {
            return true;
        }
        $courseId = $comment->post?->thread?->course_id;
        if ($courseId === null) {
            return false;
        }
        return $this->scopeService->canPerform($user->id, RoleName::Teacher, ScopeContext::course($courseId));
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->moderate($user, $comment);
    }
}
