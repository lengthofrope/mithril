<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Note;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\NoteTag>
 */
class NoteTagFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'note_id' => Note::factory(),
            'tag' => fake()->word(),
        ];
    }
}
