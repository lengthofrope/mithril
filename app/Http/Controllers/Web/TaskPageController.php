<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\TaskStatus;
use App\Http\Controllers\Controller;
use App\Models\Task;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Models\Team;
use App\Models\TeamMember;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

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

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();
        $allGroups = TaskGroup::orderBySortOrder()->get();
        $allCategories = TaskCategory::all();

        return view('pages.tasks.index', [
            'title' => 'Tasks',
            'tasks' => $tasks,
            'filters' => $filters,
            'groupByTaskGroup' => (bool) $groupById,
            'groups' => $allGroups,
            'taskGroups' => $allGroups,
            'statuses' => TaskStatus::cases(),
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name])->all(),
            'categoryOptions' => $allCategories->map(fn (TaskCategory $c) => ['value' => $c->id, 'label' => $c->name])->all(),
            'groupOptions' => $allGroups->map(fn (TaskGroup $g) => ['value' => $g->id, 'label' => $g->name])->all(),
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
            ->orderBySortOrder()
            ->with(['teamMember', 'taskCategory'])
            ->get();

        $allTeams = Team::orderBySortOrder()->get();
        $allMembers = TeamMember::orderBySortOrder()->get();

        return view('pages.tasks.kanban', [
            'title' => 'Kanban',
            'tasks' => $tasks,
            'filters' => $filters,
            'statuses' => TaskStatus::cases(),
            'teamOptions' => $allTeams->map(fn (Team $t) => ['value' => $t->id, 'label' => $t->name])->all(),
            'memberOptions' => $allMembers->map(fn (TeamMember $m) => ['value' => $m->id, 'label' => $m->name])->all(),
        ]);
    }
}
