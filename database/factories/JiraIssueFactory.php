<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\JiraIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Factory for generating JiraIssue test instances.
 *
 * @extends Factory<JiraIssue>
 */
class JiraIssueFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<JiraIssue>
     */
    protected $model = JiraIssue::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $projectKey = strtoupper(fake()->lexify('???'));
        $issueNum   = fake()->numberBetween(1, 9999);

        return [
            'user_id'            => User::factory(),
            'jira_issue_id'      => fake()->uuid(),
            'issue_key'          => "{$projectKey}-{$issueNum}",
            'summary'            => fake()->sentence(6),
            'description_preview' => fake()->optional()->text(200),
            'project_key'        => $projectKey,
            'project_name'       => fake()->words(2, true),
            'issue_type'         => fake()->randomElement(['Task', 'Bug', 'Story', 'Epic']),
            'status_name'        => fake()->randomElement(['Open', 'In Progress', 'Done']),
            'status_category'    => fake()->randomElement(['new', 'indeterminate', 'done']),
            'priority_name'      => fake()->randomElement(['Highest', 'High', 'Medium', 'Low', 'Lowest']),
            'assignee_name'      => fake()->name(),
            'assignee_email'     => fake()->safeEmail(),
            'reporter_name'      => fake()->name(),
            'reporter_email'     => fake()->safeEmail(),
            'labels'             => null,
            'web_url'            => fake()->url(),
            'sources'            => ['assigned'],
            'updated_in_jira_at' => fake()->dateTimeBetween('-7 days', 'now'),
            'is_dismissed'       => false,
            'synced_at'          => now(),
        ];
    }

    /**
     * Configure the issue as dismissed.
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
     * Configure the issue with a specific status category.
     *
     * @param string $category The status category (new, indeterminate, done).
     * @return static
     */
    public function statusCategory(string $category): static
    {
        return $this->state([
            'status_category' => $category,
        ]);
    }
}
