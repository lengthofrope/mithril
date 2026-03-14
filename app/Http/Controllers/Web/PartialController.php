<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\TaskGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\View;

/**
 * Serves HTML partials for dynamic page regions.
 *
 * Returns rendered Blade fragments with ETag-based conditional response
 * support to avoid redundant re-renders when content has not changed.
 */
class PartialController extends Controller
{
    /**
     * Map of URL type segments to their fully qualified model class names.
     *
     * @var array<string, class-string<Model>>
     */
    private array $modelMap = [
        'tasks'      => Task::class,
        'follow-ups' => FollowUp::class,
        'notes'      => Note::class,
        'bilas'      => Bila::class,
    ];

    /**
     * Return the activity feed partial for the given resource.
     *
     * Resolves the model from the type segment, retrieves its activity feed,
     * and returns a conditional response using ETag caching.
     *
     * @param Request $request
     * @param string $type
     * @param int $id
     * @return Response
     */
    public function activityFeed(Request $request, string $type, int $id): Response
    {
        if (!isset($this->modelMap[$type])) {
            abort(404);
        }

        $modelClass = $this->modelMap[$type];
        $model = $modelClass::findOrFail($id);

        $activities = $model->getActivityFeed();

        return $this->withETag(
            $request,
            'partials.activity-feed',
            ['activities' => $activities],
        );
    }

    /**
     * Return the tasks list partial for background polling.
     *
     * Loads all task groups with their tasks and all ungrouped tasks in default
     * sort order. Returns a conditional response using ETag caching.
     *
     * @param Request $request
     * @return Response
     */
    public function tasksList(Request $request): Response
    {
        $taskGroups = TaskGroup::orderBySortOrder()
            ->with(['tasks' => fn ($q) => $q->orderBySortOrder()->with(['teamMember', 'taskGroup', 'taskCategory', 'team'])])
            ->get();

        $ungroupedTasks = Task::query()
            ->whereNull('task_group_id')
            ->orderBySortOrder()
            ->with(['teamMember', 'taskGroup', 'taskCategory', 'team'])
            ->get();

        return $this->withETag(
            $request,
            'partials.tasks-list',
            [
                'taskGroups' => $taskGroups,
                'ungroupedTasks' => $ungroupedTasks,
            ],
        );
    }

    /**
     * Return the follow-ups list partial for background polling.
     *
     * Loads all follow-ups grouped by timeline category (overdue, today,
     * this week, later) in default date order. Returns a conditional response
     * using ETag caching.
     *
     * @param Request $request
     * @return Response
     */
    public function followUpsList(Request $request): Response
    {
        $baseQuery = fn () => FollowUp::query()->with(['teamMember', 'task']);

        $sections = [
            'overdue'   => $baseQuery()->overdue()->orderBy('follow_up_date')->get(),
            'today'     => $baseQuery()->dueToday()->orderBy('follow_up_date')->get(),
            'this_week' => $baseQuery()->dueThisWeek()->orderBy('follow_up_date')->get(),
            'later'     => $baseQuery()->upcoming()->orderBy('follow_up_date')->get(),
        ];

        return $this->withETag(
            $request,
            'partials.follow-ups-list',
            ['sections' => $sections],
        );
    }

    /**
     * Render a view and return a conditional response based on ETag matching.
     *
     * Returns a 304 Not Modified response when the client-supplied If-None-Match
     * header matches the computed ETag for the rendered HTML. Otherwise returns
     * a 200 response with the HTML content and the ETag header set.
     *
     * @param Request $request
     * @param string $viewName
     * @param array<string, mixed> $data
     * @return Response
     */
    private function withETag(Request $request, string $viewName, array $data): Response
    {
        $html = View::make($viewName, $data)->render();
        $etag = '"' . md5($html) . '"';

        if ($request->header('If-None-Match') === $etag) {
            return response('', 304);
        }

        return response($html, 200)
            ->header('Content-Type', 'text/html')
            ->header('ETag', $etag);
    }
}
