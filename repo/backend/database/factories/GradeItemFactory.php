<?php

namespace Database\Factories;

use App\Enums\GradeItemState;
use App\Models\GradeItem;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GradeItem>
 */
class GradeItemFactory extends Factory
{
    protected $model = GradeItem::class;

    public function definition(): array
    {
        return [
            'section_id'   => Section::factory(),
            'title'        => fake()->sentence(3),
            'max_score'    => 100,
            'weight_bps'   => 1000,
            'state'        => GradeItemState::Draft,
            'published_at' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'state'        => GradeItemState::Published,
            'published_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'state'        => GradeItemState::Draft,
            'published_at' => null,
        ]);
    }
}
