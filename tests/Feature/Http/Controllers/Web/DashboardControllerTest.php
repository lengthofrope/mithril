<?php

declare(strict_types=1);

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Meeting;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\Team;
use App\Models\TeamMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

const USER_TZ = 'Europe/Amsterdam';

test('dashboard returns 200 for authenticated user', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertOk();
});

test('dashboard redirects unauthenticated user to login', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->get('/');

    $response->assertRedirect('/login');
});

test('dashboard renders the correct view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewIs('pages.dashboard');
});

test('dashboard passes greeting variable to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('greeting');
    expect($response->viewData('greeting'))->toBeString()->not->toBeEmpty();
});

test('dashboard passes counters array to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('counters');
    expect($response->viewData('counters'))->toBeArray()->toHaveKeys([
        'open_tasks',
        'urgent_tasks',
        'overdue_tasks',
        'overdue_follow_ups',
        'today_follow_ups',
        'meetings_this_week',
    ]);
});

test('dashboard passes todayTasks todayFollowUps todayMeetings to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('todayTasks');
    $response->assertViewHas('todayFollowUps');
    $response->assertViewHas('todayMeetings');
});

test('counters open_tasks counts non-done tasks only', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::InProgress]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Waiting]);
    Task::factory()->create(['user_id' => $user->id, 'status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('counters')['open_tasks'])->toBe(3);
});

test('counters urgent_tasks counts urgent non-done tasks only', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Urgent, 'status' => TaskStatus::Done]);
    Task::factory()->create(['user_id' => $user->id, 'priority' => Priority::Normal, 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('counters')['urgent_tasks'])->toBe(1);
});

test('counters overdue_follow_ups counts overdue non-done follow-ups', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDays(2)]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDay()]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('counters')['overdue_follow_ups'])->toBe(1);
});

test('counters today_follow_ups counts follow-ups due today', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString()]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDays(3)]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('counters')['today_follow_ups'])->toBe(1);
});

test('counters meetings_this_week counts meetings within the current week', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-04 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay()]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addWeeks(2)]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('counters')['meetings_this_week'])->toBe(2);
});

test('todayTasks contains tasks with deadline today and not done', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Done]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayTasks'))->toHaveCount(1);
});

test('todayTasks includes overdue tasks with past deadlines', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDays(3)->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDay()->toDateString(), 'status' => TaskStatus::InProgress]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDays(2)->toDateString(), 'status' => TaskStatus::Done]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayTasks'))->toHaveCount(3);
});

test('todayTasks orders overdue tasks before today tasks by deadline ascending', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    $todayTask = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open]);
    $oldOverdue = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDays(5)->toDateString(), 'status' => TaskStatus::Open]);
    $recentOverdue = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    $tasks = $response->viewData('todayTasks');
    expect($tasks->pluck('id')->all())->toBe([$oldOverdue->id, $recentOverdue->id, $todayTask->id]);
});

test('todayTasks excludes tasks without a deadline', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'deadline' => null, 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayTasks'))->toHaveCount(1);
});

test('counters overdue_tasks counts tasks with past deadlines that are not done', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDays(2)->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDay()->toDateString(), 'status' => TaskStatus::InProgress]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->subDays(3)->toDateString(), 'status' => TaskStatus::Done]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('counters')['overdue_tasks'])->toBe(2);
});

test('todayFollowUps contains overdue and today non-done follow-ups', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDays(3)]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->subDay()]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString()]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDay()]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayFollowUps'))->toHaveCount(3);
});

test('todayMeetings contains meetings scheduled for today', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->toDateString()]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->addDay()->toDateString()]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayMeetings'))->toHaveCount(1);
});

test('counters meetings_this_week excludes done meetings', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-04 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now()->addDay(), 'is_done' => true]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('counters')['meetings_this_week'])->toBe(1);
});

test('todayMeetings excludes done meetings', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->toDateString(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->toDateString(), 'is_done' => true]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayMeetings'))->toHaveCount(1);
});

test('dashboard counters are zero when no data exists', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $counters = $response->viewData('counters');
    expect($counters['open_tasks'])->toBe(0);
    expect($counters['urgent_tasks'])->toBe(0);
    expect($counters['overdue_tasks'])->toBe(0);
    expect($counters['overdue_follow_ups'])->toBe(0);
    expect($counters['today_follow_ups'])->toBe(0);
    expect($counters['meetings_this_week'])->toBe(0);
});

test('dashboard passes teamOptions and memberOptions to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('teamOptions');
    $response->assertViewHas('memberOptions');
    expect($response->viewData('teamOptions'))->toBeArray();
    expect($response->viewData('memberOptions'))->toBeArray();
});

test('dashboard passes categoryOptions to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('categoryOptions');
    expect($response->viewData('categoryOptions'))->toBeArray();
});

test('dashboard passes groups to view', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('groups');
});

test('dashboard does not render quick-add task form', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertDontSee('Quick-add task');
    $response->assertDontSee('id="quick-task-title"', false);
});

test('dashboard renders quick-create buttons for all entity types', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertSee('New task');
    $response->assertSee('New follow-up');
    $response->assertSee('New note');
    $response->assertSee('Schedule meeting');
});

test('dashboard includes create modal partials', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/');

    $response->assertSee('Create a new task');
    $response->assertSee('Create a new follow-up');
    $response->assertSee('Create a new note');
    $response->assertSee('Schedule a new meeting');
});

test('dashboard passes upcomingTasks as empty collection when setting is null', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['dashboard_upcoming_tasks' => null]);

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('upcomingTasks');
    expect($response->viewData('upcomingTasks'))->toHaveCount(0);
});

test('dashboard passes upcomingFollowUps as empty collection when setting is null', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['dashboard_upcoming_follow_ups' => null]);

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('upcomingFollowUps');
    expect($response->viewData('upcomingFollowUps'))->toHaveCount(0);
});

test('dashboard passes upcomingMeetings as empty collection when setting is null', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['dashboard_upcoming_meetings' => null]);

    $response = $this->actingAs($user)->get('/');

    $response->assertViewHas('upcomingMeetings');
    expect($response->viewData('upcomingMeetings'))->toHaveCount(0);
});

test('upcomingTasks fetches future tasks limited to configured amount', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_tasks' => 2]);

    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDay()->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDays(2)->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDays(3)->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayTasks'))->toHaveCount(1);
    expect($response->viewData('upcomingTasks'))->toHaveCount(2);
});

test('upcomingTasks excludes done tasks', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_tasks' => 5]);

    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDay()->toDateString(), 'status' => TaskStatus::Open]);
    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDays(2)->toDateString(), 'status' => TaskStatus::Done]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('upcomingTasks'))->toHaveCount(1);
});

test('upcomingFollowUps fetches future follow-ups limited to configured amount', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_follow_ups' => 1]);

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->toDateString()]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDay()->toDateString()]);
    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDays(2)->toDateString()]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayFollowUps'))->toHaveCount(1);
    expect($response->viewData('upcomingFollowUps'))->toHaveCount(1);
});

test('upcomingMeetings fetches future meetings limited to configured amount', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_meetings' => 2]);

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->toDateString(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->addDay()->toDateString(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->addDays(2)->toDateString(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->addDays(3)->toDateString(), 'is_done' => false]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('todayMeetings'))->toHaveCount(1);
    expect($response->viewData('upcomingMeetings'))->toHaveCount(2);
});

test('upcomingMeetings excludes done meetings', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_meetings' => 5]);

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->addDay()->toDateString(), 'is_done' => false]);
    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->addDays(2)->toDateString(), 'is_done' => true]);

    $response = $this->actingAs($user)->get('/');

    expect($response->viewData('upcomingMeetings'))->toHaveCount(1);
});

test('upcomingTasks are ordered by deadline ascending', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_tasks' => 5]);

    $farTask = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDays(5)->toDateString(), 'status' => TaskStatus::Open]);
    $nearTask = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    $upcoming = $response->viewData('upcomingTasks');
    expect($upcoming->first()->id)->toBe($nearTask->id);
    expect($upcoming->last()->id)->toBe($farTask->id);
});

test('dashboard shows dynamic title when upcoming tasks are configured and exist', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_tasks' => 3]);

    Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->addDay()->toDateString(), 'status' => TaskStatus::Open]);

    $response = $this->actingAs($user)->get('/');

    $response->assertSee('Upcoming tasks');
    $response->assertDontSee('Tasks due today');
});

test('dashboard shows today-only title when upcoming tasks is null', function () {
    /** @var \Tests\TestCase $this */
    $user = User::factory()->create(['dashboard_upcoming_tasks' => null]);

    $response = $this->actingAs($user)->get('/');

    $response->assertSee('Tasks needing attention');
});

test('dashboard shows dynamic title when upcoming follow-ups are configured and exist', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_follow_ups' => 3]);

    FollowUp::factory()->create(['user_id' => $user->id, 'follow_up_date' => now()->addDay()->toDateString()]);

    $response = $this->actingAs($user)->get('/');

    $response->assertSee('Upcoming follow-ups');
});

test('dashboard shows dynamic title when upcoming meetings are configured and exist', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_meetings' => 3]);

    Meeting::factory()->create(['user_id' => $user->id, 'scheduled_at' => now(USER_TZ)->addDay()->toDateString(), 'is_done' => false]);

    $response = $this->actingAs($user)->get('/');

    $response->assertSee('Upcoming meetings');
});

test('todayTasks sorts by deadline then priority within same day', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();

    $lowToday = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open, 'priority' => Priority::Low]);
    $urgentToday = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open, 'priority' => Priority::Urgent]);
    $highToday = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open, 'priority' => Priority::High]);
    $normalToday = Task::factory()->create(['user_id' => $user->id, 'deadline' => now(USER_TZ)->toDateString(), 'status' => TaskStatus::Open, 'priority' => Priority::Normal]);

    $response = $this->actingAs($user)->get('/');

    $tasks = $response->viewData('todayTasks');
    expect($tasks->pluck('id')->all())->toBe([
        $urgentToday->id,
        $highToday->id,
        $normalToday->id,
        $lowToday->id,
    ]);
});

test('upcomingTasks sorts by deadline then priority within same day', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create(['dashboard_upcoming_tasks' => 10]);

    $tomorrow = now(USER_TZ)->addDay()->toDateString();
    $lowTomorrow = Task::factory()->create(['user_id' => $user->id, 'deadline' => $tomorrow, 'status' => TaskStatus::Open, 'priority' => Priority::Low]);
    $urgentTomorrow = Task::factory()->create(['user_id' => $user->id, 'deadline' => $tomorrow, 'status' => TaskStatus::Open, 'priority' => Priority::Urgent]);
    $normalTomorrow = Task::factory()->create(['user_id' => $user->id, 'deadline' => $tomorrow, 'status' => TaskStatus::Open, 'priority' => Priority::Normal]);

    $response = $this->actingAs($user)->get('/');

    $tasks = $response->viewData('upcomingTasks');
    expect($tasks->pluck('id')->all())->toBe([
        $urgentTomorrow->id,
        $normalTomorrow->id,
        $lowTomorrow->id,
    ]);
});

test('todayTasks eager-loads teamMember and team relationships', function () {
    /** @var \Tests\TestCase $this */
    $this->travelTo(Carbon::parse('2026-03-11 12:00:00', USER_TZ));
    $user = User::factory()->create();
    $team = Team::factory()->create(['user_id' => $user->id, 'name' => 'Alpha Squad']);
    $member = TeamMember::factory()->create(['user_id' => $user->id, 'team_id' => $team->id, 'name' => 'Jane Doe']);

    Task::factory()->create([
        'user_id' => $user->id,
        'deadline' => now(USER_TZ)->toDateString(),
        'status' => TaskStatus::Open,
        'team_id' => $team->id,
        'team_member_id' => $member->id,
    ]);

    $response = $this->actingAs($user)->get('/');

    $task = $response->viewData('todayTasks')->first();
    expect($task->relationLoaded('teamMember'))->toBeTrue();
    expect($task->relationLoaded('team'))->toBeTrue();
    expect($task->teamMember->name)->toBe('Jane Doe');
    expect($task->team->name)->toBe('Alpha Squad');
});
