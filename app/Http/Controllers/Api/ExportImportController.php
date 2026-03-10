<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
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
use App\Models\WeeklyReflection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Handles full data export and import for the dashboard.
 *
 * Export dumps all user data as a structured JSON payload.
 * Import accepts that payload and recreates the data, replacing existing records.
 */
class ExportImportController extends Controller
{
    use ApiResponse;

    /**
     * Export all application data as a JSON response.
     *
     * @return JsonResponse
     */
    public function export(): JsonResponse
    {
        $payload = [
            'exported_at' => now()->toIso8601String(),
            'version' => '1.0',
            'data' => [
                'teams' => Team::all()->toArray(),
                'team_members' => TeamMember::all()->toArray(),
                'task_categories' => TaskCategory::all()->toArray(),
                'task_groups' => TaskGroup::all()->toArray(),
                'tasks' => Task::all()->toArray(),
                'follow_ups' => FollowUp::all()->toArray(),
                'bilas' => Bila::all()->toArray(),
                'bila_prep_items' => BilaPrepItem::all()->toArray(),
                'agreements' => Agreement::all()->toArray(),
                'notes' => Note::all()->toArray(),
                'note_tags' => NoteTag::all()->toArray(),
                'weekly_reflections' => WeeklyReflection::all()->toArray(),
            ],
        ];

        return $this->successResponse($payload, 'Export successful.');
    }

    /**
     * Import data from a JSON payload, replacing all existing records within a transaction.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'data' => ['required', 'array'],
        ]);

        $data = $request->input('data');

        DB::transaction(function () use ($data): void {
            $this->truncateAllTables();
            $this->insertAll($data);
        });

        return $this->successResponse(null, 'Import successful.');
    }

    /**
     * Truncate all managed tables in reverse dependency order.
     *
     * @return void
     */
    private function truncateAllTables(): void
    {
        match (DB::getDriverName()) {
            'sqlite' => DB::statement('PRAGMA foreign_keys = OFF'),
            default => DB::statement('SET FOREIGN_KEY_CHECKS=0'),
        };

        NoteTag::truncate();
        Note::truncate();
        BilaPrepItem::truncate();
        Bila::truncate();
        Agreement::truncate();
        FollowUp::truncate();
        Task::truncate();
        TaskGroup::truncate();
        TaskCategory::truncate();
        TeamMember::truncate();
        Team::truncate();
        WeeklyReflection::truncate();

        match (DB::getDriverName()) {
            'sqlite' => DB::statement('PRAGMA foreign_keys = ON'),
            default => DB::statement('SET FOREIGN_KEY_CHECKS=1'),
        };
    }

    /**
     * Insert all records from the import payload, stamping each row with the authenticated user's ID.
     *
     * @param array<string, list<array<string, mixed>>> $data
     * @return void
     */
    private function insertAll(array $data): void
    {
        $userId = auth()->id();

        $this->insertIfPresent($data, 'teams', Team::class, $userId);
        $this->insertIfPresent($data, 'team_members', TeamMember::class, $userId);
        $this->insertIfPresent($data, 'task_categories', TaskCategory::class, $userId);
        $this->insertIfPresent($data, 'task_groups', TaskGroup::class, $userId);
        $this->insertIfPresent($data, 'tasks', Task::class, $userId);
        $this->insertIfPresent($data, 'follow_ups', FollowUp::class, $userId);
        $this->insertIfPresent($data, 'bilas', Bila::class, $userId);
        $this->insertIfPresent($data, 'bila_prep_items', BilaPrepItem::class, $userId);
        $this->insertIfPresent($data, 'agreements', Agreement::class, $userId);
        $this->insertIfPresent($data, 'notes', Note::class, $userId);
        $this->insertIfPresent($data, 'note_tags', NoteTag::class, $userId);
        $this->insertIfPresent($data, 'weekly_reflections', WeeklyReflection::class, $userId);
    }

    /**
     * Insert records for a given model class if the key exists in the payload.
     *
     * Each row is merged with the given user ID to enforce tenant ownership.
     *
     * @param array<string, list<array<string, mixed>>> $data
     * @param string $key
     * @param class-string $modelClass
     * @param int $userId
     * @return void
     */
    private function insertIfPresent(array $data, string $key, string $modelClass, int $userId): void
    {
        if (empty($data[$key])) {
            return;
        }

        $model = new $modelClass();
        $allowedFields = $model->getFillable();

        foreach (array_chunk($data[$key], 500) as $chunk) {
            $rows = array_map(function (array $row) use ($allowedFields, $userId): array {
                $filtered = array_intersect_key($row, array_flip($allowedFields));
                unset($filtered['id'], $filtered['user_id']);
                $filtered['user_id'] = $userId;

                return $filtered;
            }, $chunk);

            DB::table($model->getTable())->insert($rows);
        }
    }
}
