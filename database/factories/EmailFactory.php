<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EmailImportance;
use App\Models\Email;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating Email test instances.
 *
 * @extends Factory<Email>
 */
class EmailFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Email>
     */
    protected $model = Email::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id'              => User::factory(),
            'microsoft_message_id' => fake()->uuid(),
            'subject'              => fake()->sentence(4),
            'sender_name'          => fake()->name(),
            'sender_email'         => fake()->safeEmail(),
            'received_at'          => fake()->dateTimeBetween('-7 days', 'now'),
            'body_preview'         => fake()->optional()->text(200),
            'is_read'              => fake()->boolean(),
            'is_flagged'           => false,
            'flag_due_date'        => null,
            'categories'           => null,
            'importance'           => EmailImportance::Normal,
            'has_attachments'      => false,
            'web_link'             => fake()->url(),
            'sources'              => ['flagged'],
            'is_dismissed'         => false,
            'synced_at'            => now(),
        ];
    }

    /**
     * Configure the email as flagged with an optional due date.
     *
     * @param string|null $dueDate The flag due date.
     * @return static
     */
    public function flagged(?string $dueDate = null): static
    {
        return $this->state([
            'is_flagged'    => true,
            'flag_due_date' => $dueDate,
            'sources'       => ['flagged'],
        ]);
    }

    /**
     * Configure the email as dismissed.
     *
     * @return static
     */
    public function dismissed(): static
    {
        return $this->state([
            'is_dismissed' => true,
        ]);
    }

    /**
     * Configure the email with high importance.
     *
     * @return static
     */
    public function highImportance(): static
    {
        return $this->state([
            'importance' => EmailImportance::High,
        ]);
    }
}
