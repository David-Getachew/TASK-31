<?php

namespace Database\Factories;

use App\Enums\CourseStatus;
use App\Models\Course;
use App\Models\Term;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    protected $model = Course::class;

    public function definition(): array
    {
        return [
            'term_id'     => Term::factory(),
            'code'        => strtoupper(fake()->unique()->bothify('???###')),
            'title'       => fake()->catchPhrase(),
            'description' => fake()->sentence(),
            'status'      => CourseStatus::Active,
        ];
    }

    public function archived(): static
    {
        return $this->state(['status' => CourseStatus::Archived]);
    }
}
