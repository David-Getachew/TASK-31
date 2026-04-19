<?php

namespace Database\Factories;

use App\Enums\NotificationCategory;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'user_id'    => User::factory(),
            'category'   => NotificationCategory::Announcements,
            'type'       => 'general.notice',
            'title'      => fake()->sentence(4),
            'body'       => fake()->paragraph(),
            'payload'    => [],
            'read_at'    => null,
            'created_at' => now(),
        ];
    }

    public function announcements(): static
    {
        return $this->state(['category' => NotificationCategory::Announcements]);
    }

    public function mentions(): static
    {
        return $this->state(['category' => NotificationCategory::Mentions]);
    }

    public function billing(): static
    {
        return $this->state(['category' => NotificationCategory::Billing]);
    }

    public function system(): static
    {
        return $this->state(['category' => NotificationCategory::System]);
    }
}