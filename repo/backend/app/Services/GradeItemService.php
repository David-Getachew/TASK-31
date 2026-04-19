<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\GradeItemState;
use App\Models\GradeItem;
use App\Models\Section;
use App\Models\User;
use App\Support\AuditLogger;
use CampusLearn\Support\Exceptions\ConflictException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class GradeItemService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly NotificationOrchestrator $notifications,
    ) {
    }

    public function list(Section $section): \Illuminate\Database\Eloquent\Collection
    {
        return GradeItem::where('section_id', $section->id)->orderBy('created_at')->get();
    }

    public function create(User $actor, Section $section, array $data): GradeItem
    {
        return DB::transaction(function () use ($actor, $section, $data): GradeItem {
            $item = GradeItem::create([
                'section_id'  => $section->id,
                'title'       => $data['title'],
                'max_score'   => $data['max_score'],
                'weight_bps'  => $data['weight_bps'] ?? 0,
                'state'       => GradeItemState::Draft,
                'published_at' => null,
            ]);

            $this->audit->record($actor->id, 'grade_item.created', 'grade_item', $item->id, [
                'section_id' => $section->id,
                'title'      => $item->title,
            ]);

            return $item;
        });
    }

    public function update(User $actor, GradeItem $item, array $data): GradeItem
    {
        return DB::transaction(function () use ($actor, $item, $data): GradeItem {
            if ($item->state === GradeItemState::Published) {
                throw new RuntimeException('Cannot edit a published grade item.');
            }

            $item->update(array_filter([
                'title'      => $data['title'] ?? null,
                'max_score'  => $data['max_score'] ?? null,
                'weight_bps' => $data['weight_bps'] ?? null,
            ], fn ($v) => $v !== null));

            $this->audit->record($actor->id, 'grade_item.updated', 'grade_item', $item->id, [
                'changes' => $data,
            ]);

            return $item->fresh();
        });
    }

    public function publish(User $actor, GradeItem $item): GradeItem
    {
        return DB::transaction(function () use ($actor, $item): GradeItem {
            if ($item->state === GradeItemState::Published) {
                throw new ConflictException('Grade item is already published.');
            }

            $item->update([
                'state'        => GradeItemState::Published,
                'published_at' => now(),
            ]);

            $this->audit->record($actor->id, 'grade_item.published', 'grade_item', $item->id, [
                'section_id' => $item->section_id,
            ]);

            $enrolledUserIds = \App\Models\Enrollment::where('section_id', $item->section_id)
                ->where('status', \App\Enums\EnrollmentStatus::Enrolled)
                ->pluck('user_id')
                ->all();

            if ($enrolledUserIds !== []) {
                $this->notifications->notify('grade.published', $enrolledUserIds, [
                    ':title'    => $item->title,
                    ':section'  => (string) $item->section_id,
                ]);
            }

            return $item->fresh();
        });
    }
}
