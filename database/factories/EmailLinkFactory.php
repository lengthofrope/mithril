<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Bila;
use App\Models\Email;
use App\Models\EmailLink;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating EmailLink test instances.
 *
 * @extends Factory<EmailLink>
 */
class EmailLinkFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EmailLink>
     */
    protected $model = EmailLink::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email_id'      => Email::factory(),
            'email_subject' => fake()->sentence(4),
            'linkable_type' => Task::class,
            'linkable_id'   => Task::factory(),
        ];
    }

    /**
     * Configure the link to point to a specific Task.
     *
     * @param Task $task The task to link to.
     * @return static
     */
    public function forTask(Task $task): static
    {
        return $this->state([
            'linkable_type' => Task::class,
            'linkable_id'   => $task->id,
        ]);
    }

    /**
     * Configure the link to point to a specific FollowUp.
     *
     * @param FollowUp $followUp The follow-up to link to.
     * @return static
     */
    public function forFollowUp(FollowUp $followUp): static
    {
        return $this->state([
            'linkable_type' => FollowUp::class,
            'linkable_id'   => $followUp->id,
        ]);
    }

    /**
     * Configure the link to point to a specific Note.
     *
     * @param Note $note The note to link to.
     * @return static
     */
    public function forNote(Note $note): static
    {
        return $this->state([
            'linkable_type' => Note::class,
            'linkable_id'   => $note->id,
        ]);
    }

    /**
     * Configure the link to point to a specific Bila.
     *
     * @param Bila $bila The bila to link to.
     * @return static
     */
    public function forBila(Bila $bila): static
    {
        return $this->state([
            'linkable_type' => Bila::class,
            'linkable_id'   => $bila->id,
        ]);
    }
}
