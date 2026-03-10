<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\Priority;
use App\Enums\TaskStatus;
use App\Models\Bila;
use App\Models\FollowUp;
use App\Models\Task;

/**
 * Calculates dashboard counter statistics for the authenticated user.
 */
class DashboardStatsService
{
    /**
     * Build the counter stats array.
     *
     * @return array<string, int>
     */
    public function buildStats(): array
    {
        $openTaskCount = Task::whereNotIn('status', [TaskStatus::Done->value])->count();
        $urgentTaskCount = Task::where('priority', Priority::Urgent->value)
            ->whereNotIn('status', [TaskStatus::Done->value])
            ->count();
        $overdueFollowUpCount = FollowUp::overdue()->count();
        $todayFollowUpCount = FollowUp::dueToday()->count();
        $upcomingBilaCount = Bila::where('is_done', false)
            ->whereBetween(
                'scheduled_date',
                [now()->startOfWeek()->toDateString(), now()->endOfWeek()->toDateString()]
            )->count();

        return [
            'open_tasks' => $openTaskCount,
            'urgent_tasks' => $urgentTaskCount,
            'overdue_follow_ups' => $overdueFollowUpCount,
            'today_follow_ups' => $todayFollowUpCount,
            'bilas_this_week' => $upcomingBilaCount,
        ];
    }
}
