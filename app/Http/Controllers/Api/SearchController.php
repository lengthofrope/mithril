<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\FollowUp;
use App\Models\Note;
use App\Models\Task;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Handles global search across all major searchable entities.
 *
 * Uses the Searchable trait's scopeSearch on each model and returns
 * results grouped by entity type.
 */
class SearchController extends Controller
{
    use ApiResponse;

    /**
     * Minimum character length required to trigger a search.
     */
    private const int MIN_SEARCH_LENGTH = 2;

    /**
     * Maximum number of results returned per entity type.
     */
    private const int RESULTS_PER_TYPE = 10;

    /**
     * Perform a global search across tasks, notes, follow-ups, and team members.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function search(Request $request): JsonResponse
    {
        $term = (string) $request->get('q', '');

        if (mb_strlen($term) < self::MIN_SEARCH_LENGTH) {
            return $this->errorResponse(
                'Search term must be at least ' . self::MIN_SEARCH_LENGTH . ' characters.',
                [],
                422
            );
        }

        $results = [
            'tasks' => $this->searchTasks($term),
            'notes' => $this->searchNotes($term),
            'follow_ups' => $this->searchFollowUps($term),
            'team_members' => $this->searchTeamMembers($term),
        ];

        return $this->successResponse($results);
    }

    /**
     * Search tasks by title and description.
     *
     * @param string $term
     * @return \Illuminate\Support\Collection
     */
    private function searchTasks(string $term): \Illuminate\Support\Collection
    {
        return Task::search($term)
            ->with(['teamMember', 'taskCategory'])
            ->limit(self::RESULTS_PER_TYPE)
            ->get(['id', 'title', 'status', 'priority', 'deadline', 'team_member_id', 'task_category_id']);
    }

    /**
     * Search notes by title and content.
     *
     * @param string $term
     * @return \Illuminate\Support\Collection
     */
    private function searchNotes(string $term): \Illuminate\Support\Collection
    {
        return Note::search($term)
            ->limit(self::RESULTS_PER_TYPE)
            ->get(['id', 'title', 'is_pinned', 'updated_at']);
    }

    /**
     * Search follow-ups by description and waiting_on fields.
     *
     * @param string $term
     * @return \Illuminate\Support\Collection
     */
    private function searchFollowUps(string $term): \Illuminate\Support\Collection
    {
        return FollowUp::search($term)
            ->with('teamMember')
            ->limit(self::RESULTS_PER_TYPE)
            ->get(['id', 'description', 'status', 'follow_up_date', 'team_member_id']);
    }

    /**
     * Search team members by name, role, and email.
     *
     * @param string $term
     * @return \Illuminate\Support\Collection
     */
    private function searchTeamMembers(string $term): \Illuminate\Support\Collection
    {
        return TeamMember::search($term)
            ->with('team')
            ->limit(self::RESULTS_PER_TYPE)
            ->get(['id', 'name', 'role', 'status', 'team_id']);
    }
}
