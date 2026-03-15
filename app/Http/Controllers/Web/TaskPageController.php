<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\FollowUpStatus;
use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\FollowUp;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;
use App\Services\BreadcrumbBuilder;
use App\Services\MetadataTransferService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * Handles task list and kanban page rendering.
 *
 * Supports filter-based querying through the Filterable trait
 * and groups tasks per status column for the kanban board.
 */
class TaskPageController extends Controller
{
    /**
     * Display the task list view with optional filters applied.
     *
     * Returns only the tasks-list partial for AJAX requests (used by filterManager).
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $filters = $request->only([
            'priority',
            'status',
            'team_id',
            'team_member_id',
            'task_group_id',
            'task_category_id',
            'is_private',
            'deadline',
        ]);

        $query = Task::query()
            ->applyFilters($filters)
            ->orderBySortOrder()
            ->with(['teamMember', 'taskGroup', 'taskCategory', 'team']);

        $groupById = (int) $request->get('group_by_task_group', 0);

        if ($groupById) {
            $tasks = $query->get()->groupBy('task_group_id');
        } else {
            $tasks = $query->get();
        }

        $allGroups = TaskGroup::orderBySortOrder()
            ->with(['tasks' => fn ($q) => $q->applyFilters($filters)->orderBySortOrder()->with(['teamMember', 'taskGroup', 'taskCategory', 'team'])])
            ->get();

        $ungroupedTasks = Task::query()
            ->whereNull('task_group_id')
            ->applyFilters($filters)
            ->orderBySortOrder()
            ->with(['teamMember', 'taskGroup', 'taskCategory', 'team'])
            ->get();

        if ($request->ajax()) {
            return view('partials.tasks-list', [
                'taskGroups' => $allGroups,
                'ungroupedTasks' => $ungroupedTasks,
            ]);
        }

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();
        $allCategories = TaskCategory::all();

        return view('pages.tasks.index', [
            'title' => 'Tasks',
            'tasks' => $tasks,
            'filters' => $filters,
            'groupByTaskGroup' => (bool) $groupById,
            'groups' => $allGroups,
            'taskGroups' => $allGroups,
            'ungroupedTasks' => $ungroupedTasks,
            'statuses' => TaskStatus::cases(),
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name, 'team_id' => $m->team_id])->all(),
            'categoryOptions' => $allCategories->map(fn (TaskCategory $c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'groupOptions' => $allGroups->map(fn (TaskGroup $g) => ['value' => $g->id, 'label' => $g->name])->all(),
        ]);
    }

    /**
     * Store a new task from the quick-add or inline form.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'title'            => ['required', 'string', 'max:255'],
            'priority'         => ['nullable', 'string', 'in:urgent,high,normal,low'],
            'task_group_id'    => ['nullable', 'integer', Rule::exists('task_groups', 'id')->where('user_id', auth()->id())],
            'team_id'          => ['nullable', 'integer', Rule::exists('teams', 'id')->where('user_id', auth()->id())],
            'team_member_id'   => ['nullable', 'integer', Rule::exists('team_members', 'id')->where('user_id', auth()->id())],
            'task_category_id' => ['nullable', 'integer', Rule::exists('task_categories', 'id')->where('user_id', auth()->id())],
            'deadline'         => ['nullable', 'date'],
        ]);

        $task = Task::create([
            'user_id'          => $request->user()->id,
            'title'            => $validated['title'],
            'priority'         => $validated['priority'] ?? 'normal',
            'task_group_id'    => $validated['task_group_id'] ?? null,
            'team_id'          => $validated['team_id'] ?? null,
            'team_member_id'   => $validated['team_member_id'] ?? null,
            'task_category_id' => $validated['task_category_id'] ?? null,
            'deadline'         => $validated['deadline'] ?? null,
        ]);

        return redirect()->route('tasks.show', $task);
    }

    /**
     * Display a single task detail page.
     *
     * @param Task $task
     * @return View
     */
    public function show(Task $task): View
    {
        $task->load(['teamMember.team', 'taskGroup', 'taskCategory', 'team', 'followUps']);

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();
        $allCategories = TaskCategory::all();
        $allGroups = TaskGroup::orderBySortOrder()->get();

        return view('pages.tasks.show', [
            'title' => $task->title,
            'task' => $task,
            'breadcrumbs' => (new BreadcrumbBuilder())->forTask($task)->build(),
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => (string) $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => (string) $m->id, 'label' => $m->name, 'team_id' => (string) $m->team_id])->all(),
            'categoryOptions' => $allCategories->map(fn (TaskCategory $c) => ['value' => (string) $c->id, 'label' => $c->name])->all(),
            'groupOptions' => $allGroups->map(fn (TaskGroup $g) => ['value' => (string) $g->id, 'label' => $g->name])->all(),
            'priorityOptions' => collect(Priority::cases())->map(fn (Priority $p) => ['value' => $p->value, 'label' => ucfirst($p->value)])->all(),
            'statusOptions' => collect(TaskStatus::cases())->map(fn (TaskStatus $s) => ['value' => $s->value, 'label' => ucfirst(str_replace('_', ' ', $s->value))])->all(),
        ]);
    }

    /**
     * Display the kanban board with tasks grouped by status columns.
     *
     * @param Request $request
     * @return View
     */
    public function kanban(Request $request): View
    {
        $filters = $request->only([
            'priority',
            'team_id',
            'team_member_id',
            'task_group_id',
            'task_category_id',
        ]);

        $tasks = Task::query()
            ->applyFilters($filters)
            ->where(function ($query): void {
                $query->where('status', '!=', TaskStatus::Done)
                    ->orWhere('updated_at', '>=', now()->subWeek());
            })
            ->orderBySortOrder()
            ->with(['teamMember', 'taskCategory'])
            ->get();

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();
        $allCategories = TaskCategory::all();
        $allGroups = TaskGroup::orderBySortOrder()->get();

        return view('pages.tasks.kanban', [
            'title' => 'Kanban',
            'breadcrumbs' => (new BreadcrumbBuilder())->forPage('Tasks', route('tasks.index'))->addCrumb('Kanban')->build(),
            'tasks' => $tasks,
            'filters' => $filters,
            'statuses' => TaskStatus::cases(),
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name, 'team_id' => $m->team_id])->all(),
            'categoryOptions' => $allCategories->map(fn (TaskCategory $c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'groups' => $allGroups,
        ]);
    }

    /**
     * Bulk-update a set of tasks with the given field values.
     *
     * Accepts a list of task IDs and a map of fields to update on each.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkUpdate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'task_ids'               => ['required', 'array'],
            'task_ids.*'             => ['integer', Rule::exists('tasks', 'id')->where('user_id', auth()->id())],
            'fields'                 => ['required', 'array'],
            'fields.status'          => ['sometimes', 'string', 'in:' . implode(',', array_column(TaskStatus::cases(), 'value'))],
            'fields.priority'        => ['sometimes', 'string', 'in:' . implode(',', array_column(Priority::cases(), 'value'))],
            'fields.task_group_id'   => ['sometimes', 'nullable', 'integer', Rule::exists('task_groups', 'id')->where('user_id', auth()->id())],
            'fields.task_category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('task_categories', 'id')->where('user_id', auth()->id())],
            'fields.team_id'         => ['sometimes', 'nullable', 'integer', Rule::exists('teams', 'id')->where('user_id', auth()->id())],
            'fields.team_member_id'  => ['sometimes', 'nullable', 'integer', Rule::exists('team_members', 'id')->where('user_id', auth()->id())],
            'fields.deadline'        => ['sometimes', 'nullable', 'date'],
            'fields.is_private'      => ['sometimes', 'boolean'],
        ]);

        Task::whereIn('id', $validated['task_ids'])
            ->each(fn (Task $task) => $task->update($validated['fields']));

        return response()->json(['success' => true]);
    }

    /**
     * Move a task to a new kanban status column or task group.
     *
     * Accepts a task ID and either a target status value, a target task_group_id, or both.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function move(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id'            => ['required', 'integer', Rule::exists('tasks', 'id')->where('user_id', auth()->id())],
            'status'        => ['nullable', 'string', 'in:' . implode(',', array_column(TaskStatus::cases(), 'value'))],
            'task_group_id' => ['nullable', 'integer', Rule::exists('task_groups', 'id')->where('user_id', auth()->id())],
            'clear_group'   => ['nullable', 'boolean'],
        ]);

        $task = Task::findOrFail($validated['id']);
        $updates = [];

        if (isset($validated['status'])) {
            $updates['status'] = $validated['status'];
        }

        if (!empty($validated['clear_group'])) {
            $updates['task_group_id'] = null;
        } elseif (isset($validated['task_group_id'])) {
            $updates['task_group_id'] = $validated['task_group_id'];
        }

        $task->update($updates);

        return response()->json(['success' => true]);
    }

    /**
     * Convert a task into a follow-up, marking the task as done.
     *
     * @param Request $request
     * @param Task $task
     * @return JsonResponse|RedirectResponse
     */
    public function convertToFollowUp(Request $request, Task $task, MetadataTransferService $metadataTransfer): JsonResponse|RedirectResponse
    {
        $followUp = $this->createFollowUpFromTask($request, $task);

        $metadataTransfer->transfer($task, $followUp);
        $task->update(['status' => TaskStatus::Done]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => ['follow_up_url' => route('follow-ups.show', $followUp)],
            ]);
        }

        return redirect()->route('follow-ups.show', $followUp);
    }

    /**
     * Create a follow-up linked to a task without changing the task status.
     *
     * @param Request $request
     * @param Task $task
     * @return JsonResponse|RedirectResponse
     */
    public function createFollowUp(Request $request, Task $task): JsonResponse|RedirectResponse
    {
        $followUp = $this->createFollowUpFromTask($request, $task);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => ['follow_up_url' => route('follow-ups.show', $followUp)],
            ]);
        }

        return redirect()->route('follow-ups.show', $followUp);
    }

    /**
     * Create a follow-up from task data with the task linked.
     *
     * @param Request $request
     * @param Task $task
     * @return FollowUp
     */
    private function createFollowUpFromTask(Request $request, Task $task): FollowUp
    {
        return FollowUp::create([
            'user_id' => $request->user()->id,
            'task_id' => $task->id,
            'description' => $task->title,
            'team_member_id' => $task->team_member_id,
            'follow_up_date' => $task->deadline ?? now()->toDateString(),
            'status' => FollowUpStatus::Open->value,
        ]);
    }
}
