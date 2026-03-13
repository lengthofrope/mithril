<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\NotificationVariant;
use App\Models\SystemNotification;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for SystemNotification model.
 *
 * @extends Factory<SystemNotification>
 */
class SystemNotificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title'      => fake()->sentence(4),
            'message'    => fake()->paragraph(),
            'variant'    => NotificationVariant::Info,
            'is_active'  => true,
            'expires_at' => null,
        ];
    }

    /**
     * Set the notification as inactive.
     */
    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    /**
     * Set the notification as expired.
     */
    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }

    /**
     * Set a specific variant.
     */
    public function variant(NotificationVariant $variant): static
    {
        return $this->state(['variant' => $variant]);
    }

    /**
     * Add a link to the notification.
     */
    public function withLink(string $url = '/settings', string $text = 'Learn more'): static
    {
        return $this->state([
            'link_url'  => $url,
            'link_text' => $text,
        ]);
    }
}
