<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\FollowUpStatus;
use App\Enums\MemberStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Agreement;
use App\Models\Bila;
use App\Models\BilaPrepItem;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\NoteTag;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use App\Models\WeeklyReflection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds the database with representative sample data for development and testing.
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->createAdminUser();
        [$teamAlpha, $teamBeta] = $this->createTeams();
        $membersAlpha = $this->createTeamAlphaMembers($teamAlpha);
        $membersBeta = $this->createTeamBetaMembers($teamBeta);
        $categories = $this->createTaskCategories();
        $groups = $this->createTaskGroups();

        $allMembers = array_merge($membersAlpha, $membersBeta);
        $tasks = $this->createTasks($teamAlpha, $teamBeta, $allMembers, $groups, $categories);

        $this->createFollowUps($tasks, $allMembers);
        $this->createBilasWithPrepItems($allMembers);
        $this->createAgreements($allMembers);
        $this->createNotes($teamAlpha, $teamBeta, $allMembers);
        $this->createWeeklyReflection();
    }

    /**
     * Create the admin user.
     *
     * @return User
     */
    private function createAdminUser(): User
    {
        return User::create([
            'name' => 'Team Lead',
            'email' => 'admin@teamlead.test',
            'password' => Hash::make('password'),
            'theme_preference' => 'dark',
            'push_enabled' => false,
        ]);
    }

    /**
     * Create two teams and return them.
     *
     * @return array{0: Team, 1: Team}
     */
    private function createTeams(): array
    {
        $alpha = Team::create([
            'name' => 'Team Alpha',
            'description' => 'Core product development team.',
            'color' => '#3b82f6',
        ]);

        $beta = Team::create([
            'name' => 'Team Beta',
            'description' => 'Operations and infrastructure team.',
            'color' => '#10b981',
        ]);

        return [$alpha, $beta];
    }

    /**
     * Create members for Team Alpha.
     *
     * @param Team $team
     * @return list<TeamMember>
     */
    private function createTeamAlphaMembers(Team $team): array
    {
        return [
            TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Sarah Johnson',
                'role' => 'Frontend Developer',
                'email' => 'sarah@example.com',
                'status' => MemberStatus::Available,
                'bila_interval_days' => 14,
                'next_bila_date' => now()->addDays(7)->toDateString(),
            ]),
            TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Marcus Chen',
                'role' => 'Backend Developer',
                'email' => 'marcus@example.com',
                'status' => MemberStatus::Available,
                'bila_interval_days' => 14,
                'next_bila_date' => now()->addDays(3)->toDateString(),
            ]),
            TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Priya Patel',
                'role' => 'UX Designer',
                'email' => 'priya@example.com',
                'status' => MemberStatus::PartiallyAvailable,
                'bila_interval_days' => 21,
                'next_bila_date' => now()->addDays(14)->toDateString(),
            ]),
            TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Tom Williams',
                'role' => 'QA Engineer',
                'email' => 'tom@example.com',
                'status' => MemberStatus::Available,
                'bila_interval_days' => 14,
                'next_bila_date' => now()->addDays(5)->toDateString(),
            ]),
        ];
    }

    /**
     * Create members for Team Beta.
     *
     * @param Team $team
     * @return list<TeamMember>
     */
    private function createTeamBetaMembers(Team $team): array
    {
        return [
            TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Elena Vasquez',
                'role' => 'DevOps Engineer',
                'email' => 'elena@example.com',
                'status' => MemberStatus::Available,
                'bila_interval_days' => 14,
                'next_bila_date' => now()->addDays(2)->toDateString(),
            ]),
            TeamMember::create([
                'team_id' => $team->id,
                'name' => 'David Kim',
                'role' => 'Data Engineer',
                'email' => 'david@example.com',
                'status' => MemberStatus::Absent,
                'bila_interval_days' => 14,
                'next_bila_date' => now()->addDays(10)->toDateString(),
            ]),
            TeamMember::create([
                'team_id' => $team->id,
                'name' => 'Fatima Al-Hassan',
                'role' => 'Systems Analyst',
                'email' => 'fatima@example.com',
                'status' => MemberStatus::Available,
                'bila_interval_days' => 21,
                'next_bila_date' => now()->addDays(6)->toDateString(),
            ]),
        ];
    }

    /**
     * Create task categories and return them.
     *
     * @return list<TaskCategory>
     */
    private function createTaskCategories(): array
    {
        $names = ['Feature', 'Bug Fix', 'Tech Debt', 'Research', 'Documentation'];

        return array_map(
            fn (string $name) => TaskCategory::create(['name' => $name]),
            $names,
        );
    }

    /**
     * Create task groups and return them.
     *
     * @return list<TaskGroup>
     */
    private function createTaskGroups(): array
    {
        return [
            TaskGroup::create([
                'name' => 'Q1 2026 Sprint',
                'description' => 'First quarter sprint goals.',
                'color' => '#8b5cf6',
            ]),
            TaskGroup::create([
                'name' => 'Infrastructure Upgrade',
                'description' => 'Database and server migration tasks.',
                'color' => '#f59e0b',
            ]),
        ];
    }

    /**
     * Create tasks spread across teams, members, groups, and categories.
     *
     * @param Team $teamAlpha
     * @param Team $teamBeta
     * @param list<TeamMember> $members
     * @param list<TaskGroup> $groups
     * @param list<TaskCategory> $categories
     * @return list<Task>
     */
    private function createTasks(
        Team $teamAlpha,
        Team $teamBeta,
        array $members,
        array $groups,
        array $categories,
    ): array {
        $taskData = [
            [
                'title' => 'Implement dashboard greeting widget',
                'priority' => Priority::High,
                'status' => TaskStatus::InProgress,
                'team_id' => $teamAlpha->id,
                'team_member_id' => $members[0]->id,
                'task_group_id' => $groups[0]->id,
                'task_category_id' => $categories[0]->id,
                'deadline' => now()->addDays(5)->toDateString(),
            ],
            [
                'title' => 'Fix login page redirect bug',
                'priority' => Priority::Urgent,
                'status' => TaskStatus::Open,
                'team_id' => $teamAlpha->id,
                'team_member_id' => $members[1]->id,
                'task_category_id' => $categories[1]->id,
            ],
            [
                'title' => 'Refactor authentication service',
                'priority' => Priority::Normal,
                'status' => TaskStatus::Open,
                'team_id' => $teamAlpha->id,
                'team_member_id' => $members[1]->id,
                'task_group_id' => $groups[0]->id,
                'task_category_id' => $categories[2]->id,
            ],
            [
                'title' => 'Design onboarding flow screens',
                'priority' => Priority::High,
                'status' => TaskStatus::Waiting,
                'team_id' => $teamAlpha->id,
                'team_member_id' => $members[2]->id,
                'task_category_id' => $categories[3]->id,
                'deadline' => now()->addDays(10)->toDateString(),
            ],
            [
                'title' => 'Write API documentation for v2',
                'priority' => Priority::Low,
                'status' => TaskStatus::Open,
                'team_id' => $teamAlpha->id,
                'team_member_id' => $members[3]->id,
                'task_category_id' => $categories[4]->id,
            ],
            [
                'title' => 'Set up CI/CD pipeline for staging',
                'priority' => Priority::High,
                'status' => TaskStatus::InProgress,
                'team_id' => $teamBeta->id,
                'team_member_id' => $members[4]->id,
                'task_group_id' => $groups[1]->id,
                'task_category_id' => $categories[0]->id,
            ],
            [
                'title' => 'Migrate PostgreSQL to MariaDB',
                'priority' => Priority::Urgent,
                'status' => TaskStatus::Open,
                'team_id' => $teamBeta->id,
                'team_member_id' => $members[5]->id,
                'task_group_id' => $groups[1]->id,
                'task_category_id' => $categories[2]->id,
                'deadline' => now()->addDays(14)->toDateString(),
            ],
            [
                'title' => 'Analyse slow query log',
                'priority' => Priority::High,
                'status' => TaskStatus::Done,
                'team_id' => $teamBeta->id,
                'team_member_id' => $members[6]->id,
                'task_category_id' => $categories[3]->id,
            ],
            [
                'title' => 'Automate nightly backups',
                'priority' => Priority::Normal,
                'status' => TaskStatus::Open,
                'team_id' => $teamBeta->id,
                'team_member_id' => $members[4]->id,
                'task_group_id' => $groups[1]->id,
            ],
            [
                'title' => 'Spike: evaluate WebAuthn library',
                'priority' => Priority::Low,
                'status' => TaskStatus::Open,
                'team_id' => $teamAlpha->id,
                'task_category_id' => $categories[3]->id,
                'is_private' => true,
            ],
            [
                'title' => 'Update Node dependencies',
                'priority' => Priority::Low,
                'status' => TaskStatus::Done,
                'team_id' => $teamAlpha->id,
                'task_category_id' => $categories[2]->id,
            ],
            [
                'title' => 'Review and merge open PRs',
                'priority' => Priority::Normal,
                'status' => TaskStatus::Open,
                'team_id' => $teamAlpha->id,
                'team_member_id' => $members[0]->id,
            ],
            [
                'title' => 'Set up monitoring dashboards in Grafana',
                'priority' => Priority::High,
                'status' => TaskStatus::InProgress,
                'team_id' => $teamBeta->id,
                'team_member_id' => $members[4]->id,
                'task_group_id' => $groups[1]->id,
            ],
        ];

        return array_map(
            fn (array $data) => Task::create($data),
            $taskData,
        );
    }

    /**
     * Create follow-ups linked to tasks and members.
     *
     * @param list<Task> $tasks
     * @param list<TeamMember> $members
     * @return list<FollowUp>
     */
    private function createFollowUps(array $tasks, array $members): array
    {
        $followUpData = [
            [
                'task_id' => $tasks[3]->id,
                'team_member_id' => $members[2]->id,
                'description' => 'Check if design review feedback was incorporated.',
                'waiting_on' => 'Priya Patel',
                'follow_up_date' => now()->subDays(2)->toDateString(),
                'status' => FollowUpStatus::Open,
            ],
            [
                'task_id' => $tasks[1]->id,
                'team_member_id' => $members[1]->id,
                'description' => 'Verify login bug is fixed in staging.',
                'follow_up_date' => now()->toDateString(),
                'status' => FollowUpStatus::Open,
            ],
            [
                'team_member_id' => $members[0]->id,
                'description' => 'Discuss frontend performance goals for Q2.',
                'follow_up_date' => now()->addDays(3)->toDateString(),
                'status' => FollowUpStatus::Open,
            ],
            [
                'team_member_id' => $members[4]->id,
                'description' => 'Follow up on CI/CD rollout to production.',
                'follow_up_date' => now()->addDays(5)->toDateString(),
                'status' => FollowUpStatus::Open,
            ],
            [
                'team_member_id' => $members[5]->id,
                'description' => 'Check on migration progress after return from leave.',
                'follow_up_date' => now()->addDays(12)->toDateString(),
                'status' => FollowUpStatus::Snoozed,
                'snoozed_until' => now()->addDays(10)->toDateString(),
            ],
            [
                'task_id' => $tasks[6]->id,
                'team_member_id' => $members[5]->id,
                'description' => 'Confirm MariaDB migration is complete and tables verified.',
                'follow_up_date' => now()->addDays(14)->toDateString(),
                'status' => FollowUpStatus::Open,
            ],
            [
                'team_member_id' => $members[6]->id,
                'description' => 'Review slow query analysis results.',
                'follow_up_date' => now()->endOfWeek()->addDays(2)->toDateString(),
                'status' => FollowUpStatus::Open,
            ],
        ];

        return array_map(
            fn (array $data) => FollowUp::create($data),
            $followUpData,
        );
    }

    /**
     * Create bilas with prep items for a selection of members.
     *
     * @param list<TeamMember> $members
     * @return void
     */
    private function createBilasWithPrepItems(array $members): void
    {
        $bilaData = [
            [
                'member' => $members[0],
                'date' => now()->subDays(14)->toDateString(),
                'notes' => "# Sarah - Bila 1\n\nDiscussed frontend performance. Action: set up profiling tooling.\n\nMood: positive, engaged.",
                'items' => [
                    ['content' => 'Review sprint velocity', 'is_discussed' => true],
                    ['content' => 'Address feedback from design review', 'is_discussed' => true],
                    ['content' => 'Career growth goals for Q2', 'is_discussed' => false],
                ],
            ],
            [
                'member' => $members[1],
                'date' => now()->subDays(7)->toDateString(),
                'notes' => "# Marcus - Bila 1\n\nTalked through auth service refactor scope.\n\nAction: create task breakdown by Friday.",
                'items' => [
                    ['content' => 'Auth service scope and timeline', 'is_discussed' => true],
                    ['content' => 'Code review backlog', 'is_discussed' => true],
                ],
            ],
            [
                'member' => $members[4],
                'date' => now()->subDays(3)->toDateString(),
                'notes' => "# Elena - Bila 1\n\nCI/CD pipeline progress on track. Discussed staging rollout plan.",
                'items' => [
                    ['content' => 'CI/CD pipeline status update', 'is_discussed' => true],
                    ['content' => 'Grafana dashboard setup', 'is_discussed' => false],
                    ['content' => 'On-call rotation proposal', 'is_discussed' => false],
                ],
            ],
            [
                'member' => $members[2],
                'date' => now()->addDays(5)->toDateString(),
                'notes' => null,
                'items' => [
                    ['content' => 'Onboarding flow design progress', 'is_discussed' => false],
                    ['content' => 'Availability constraints this quarter', 'is_discussed' => false],
                ],
            ],
        ];

        foreach ($bilaData as $data) {
            $bila = Bila::create([
                'team_member_id' => $data['member']->id,
                'scheduled_date' => $data['date'],
                'notes' => $data['notes'],
            ]);

            foreach ($data['items'] as $itemData) {
                BilaPrepItem::create([
                    'team_member_id' => $data['member']->id,
                    'bila_id' => $bila->id,
                    'content' => $itemData['content'],
                    'is_discussed' => $itemData['is_discussed'],
                ]);
            }
        }
    }

    /**
     * Create agreements for several team members.
     *
     * @param list<TeamMember> $members
     * @return void
     */
    private function createAgreements(array $members): void
    {
        $agreementData = [
            [
                'team_member_id' => $members[0]->id,
                'description' => 'Will deliver a profiling report for the dashboard widgets by end of month.',
                'agreed_date' => now()->subDays(14)->toDateString(),
                'follow_up_date' => now()->addDays(7)->toDateString(),
            ],
            [
                'team_member_id' => $members[1]->id,
                'description' => 'Will create a full task breakdown for the auth service refactor.',
                'agreed_date' => now()->subDays(7)->toDateString(),
                'follow_up_date' => now()->addDays(3)->toDateString(),
            ],
            [
                'team_member_id' => $members[4]->id,
                'description' => 'Will document the CI/CD runbook before staging goes live.',
                'agreed_date' => now()->subDays(3)->toDateString(),
            ],
        ];

        foreach ($agreementData as $data) {
            Agreement::create($data);
        }
    }

    /**
     * Create notes with tags for teams and members.
     *
     * @param Team $teamAlpha
     * @param Team $teamBeta
     * @param list<TeamMember> $members
     * @return void
     */
    private function createNotes(Team $teamAlpha, Team $teamBeta, array $members): void
    {
        $noteData = [
            [
                'title' => 'Team Alpha — Working Agreements',
                'content' => "# Working Agreements\n\n- Daily standups at 09:15\n- PRs reviewed within 24 hours\n- No meeting Fridays after 14:00",
                'team_id' => $teamAlpha->id,
                'team_member_id' => null,
                'is_pinned' => true,
                'tags' => ['team', 'agreements', 'process'],
            ],
            [
                'title' => 'Architecture Decision: MariaDB over PostgreSQL',
                'content' => "# ADR: MariaDB\n\nChose MariaDB for hosting compatibility. Migration tasks tracked in Infrastructure Upgrade group.",
                'team_id' => $teamBeta->id,
                'team_member_id' => null,
                'is_pinned' => false,
                'tags' => ['architecture', 'database'],
            ],
            [
                'title' => 'Sarah — Personal Development Notes',
                'content' => "## Goals\n\n- Move toward senior role by EOY\n- Improve TypeScript proficiency\n- Present at internal tech talk",
                'team_id' => null,
                'team_member_id' => $members[0]->id,
                'is_pinned' => false,
                'tags' => ['development', 'career'],
            ],
            [
                'title' => 'WebAuthn Research Notes',
                'content' => "# WebAuthn Spike\n\nEvaluated `laragear/webauthn`. Seems solid. PRO: standards-based, no passwords. CON: complexity for mobile.",
                'team_id' => null,
                'team_member_id' => null,
                'is_pinned' => false,
                'tags' => ['research', 'security', 'auth'],
            ],
        ];

        foreach ($noteData as $data) {
            $note = Note::create([
                'title' => $data['title'],
                'content' => $data['content'],
                'team_id' => $data['team_id'],
                'team_member_id' => $data['team_member_id'],
                'is_pinned' => $data['is_pinned'],
            ]);

            foreach ($data['tags'] as $tag) {
                NoteTag::create([
                    'note_id' => $note->id,
                    'tag' => $tag,
                ]);
            }
        }
    }

    /**
     * Create a weekly reflection for the current week.
     *
     * @return void
     */
    private function createWeeklyReflection(): void
    {
        WeeklyReflection::create([
            'week_start' => now()->startOfWeek()->toDateString(),
            'week_end' => now()->endOfWeek()->toDateString(),
            'summary' => 'Focused on backend architecture setup and team onboarding. Most tasks are on track.',
            'reflection' => "Good energy in both teams this week. Need to keep an eye on Marcus's auth refactor scope — risk of expanding. Elena is doing great work on CI/CD.",
        ]);
    }
}
