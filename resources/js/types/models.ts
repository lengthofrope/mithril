/**
 * Task priority levels, ordered from highest to lowest urgency.
 */
type Priority = 'urgent' | 'high' | 'normal' | 'low';

/**
 * Task lifecycle status.
 */
type TaskStatus = 'open' | 'in_progress' | 'waiting' | 'done';

/**
 * Follow-up lifecycle status.
 */
type FollowUpStatus = 'open' | 'snoozed' | 'done';

/**
 * Team member availability status.
 */
type MemberStatus = 'available' | 'absent' | 'partially_available';

/**
 * Mirrors the `teams` Eloquent model.
 */
interface Team {
    id: number;
    name: string;
    description: string | null;
    color: string;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `team_members` Eloquent model.
 */
interface TeamMember {
    id: number;
    team_id: number;
    name: string;
    role: string;
    email: string | null;
    notes: string | null;
    status: MemberStatus;
    avatar_path: string | null;
    bila_interval_days: number | null;
    next_bila_date: string | null;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `tasks` Eloquent model.
 */
interface Task {
    id: number;
    title: string;
    description: string | null;
    priority: Priority;
    category: string | null;
    status: TaskStatus;
    deadline: string | null;
    team_id: number | null;
    team_member_id: number | null;
    task_group_id: number | null;
    task_category_id: number | null;
    is_private: boolean;
    sort_order: number;
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `task_groups` Eloquent model.
 */
interface TaskGroup {
    id: number;
    name: string;
    description: string | null;
    color: string;
    sort_order: number;
}

/**
 * Mirrors the `follow_ups` Eloquent model.
 */
interface FollowUp {
    id: number;
    task_id: number | null;
    team_member_id: number | null;
    description: string;
    waiting_on: string | null;
    follow_up_date: string;
    snoozed_until: string | null;
    status: FollowUpStatus;
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `bilas` Eloquent model.
 */
interface Bila {
    id: number;
    team_member_id: number;
    scheduled_date: string;
    notes: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `bila_prep_items` Eloquent model.
 */
interface BilaPrepItem {
    id: number;
    team_member_id: number;
    bila_id: number | null;
    content: string;
    is_discussed: boolean;
    sort_order: number;
}

/**
 * Mirrors the `agreements` Eloquent model.
 */
interface Agreement {
    id: number;
    team_member_id: number;
    description: string;
    agreed_date: string;
    follow_up_date: string | null;
}

/**
 * Mirrors the `notes` Eloquent model.
 */
interface Note {
    id: number;
    title: string;
    content: string | null;
    team_id: number | null;
    team_member_id: number | null;
    is_pinned: boolean;
    tags?: string[];
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `weekly_reflections` Eloquent model.
 */
interface WeeklyReflection {
    id: number;
    week_start: string;
    week_end: string;
    summary: string | null;
    reflection: string | null;
}

export type { Priority, TaskStatus, FollowUpStatus, MemberStatus };
export type { Team, TeamMember, Task, TaskGroup, FollowUp, Bila, BilaPrepItem, Agreement, Note, WeeklyReflection };
