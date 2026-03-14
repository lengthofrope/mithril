/**
 * Task priority levels, ordered from highest to lowest urgency.
 */
type Priority = 'urgent' | 'high' | 'normal' | 'low';

/**
 * Task lifecycle status.
 */
type TaskStatus = 'open' | 'in_progress' | 'waiting' | 'done';

/**
 * Available recurrence intervals for recurring tasks.
 */
type RecurrenceInterval = 'daily' | 'weekly' | 'biweekly' | 'monthly' | 'custom';

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
    is_recurring: boolean;
    recurrence_interval: RecurrenceInterval | null;
    recurrence_custom_days: number | null;
    recurrence_series_id: string | null;
    recurrence_parent_id: number | null;
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
    is_done: boolean;
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

/**
 * Available chart types for analytics widgets.
 */
type ChartType = 'donut' | 'bar' | 'bar_horizontal' | 'stacked_bar' | 'line';

/**
 * Available data sources for analytics widgets.
 */
type DataSource =
    | 'tasks_by_status'
    | 'tasks_by_priority'
    | 'tasks_by_category'
    | 'tasks_by_group'
    | 'tasks_by_member'
    | 'tasks_by_deadline'
    | 'follow_ups_by_status'
    | 'follow_ups_by_urgency'
    | 'tasks_over_time'
    | 'task_activity'
    | 'follow_ups_over_time';

/**
 * Time range options for time-series widgets.
 */
type TimeRange = '7d' | '30d' | '90d';

/**
 * Mirrors the `analytics_widgets` Eloquent model.
 */
interface AnalyticsWidget {
    id: number;
    data_source: DataSource;
    chart_type: ChartType;
    title: string | null;
    column_span: number;
    show_on_analytics: boolean;
    show_on_dashboard: boolean;
    sort_order_analytics: number;
    sort_order_dashboard: number;
    time_range: TimeRange | null;
}

/**
 * Chart data response from the analytics widget-data endpoint (point-in-time).
 */
interface ChartData {
    labels: string[];
    series: number[];
    colors: string[];
}

/**
 * Chart data response from the analytics widget-data endpoint (time-series).
 */
interface TimeSeriesChartData {
    labels: string[];
    series: Array<{ name: string; data: number[] }>;
    colors: string[];
}

/**
 * Calendar event status from Microsoft Graph.
 */
type CalendarEventStatus = 'free' | 'tentative' | 'busy' | 'oof' | 'workingElsewhere';

/**
 * Mirrors the `calendar_events` Eloquent model.
 */
interface CalendarEvent {
    id: number;
    microsoft_event_id: string;
    subject: string;
    start_at: string;
    end_at: string;
    is_all_day: boolean;
    location: string | null;
    status: CalendarEventStatus;
    is_online_meeting: boolean;
    online_meeting_url: string | null;
    organizer_name: string | null;
    organizer_email: string | null;
    attendees: Array<{ email: string; name: string | null }> | null;
    links?: CalendarEventLink[];
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `calendar_event_links` Eloquent model.
 */
interface CalendarEventLink {
    id: number;
    calendar_event_id: number;
    linkable_type: string;
    linkable_id: number;
    linkable?: Bila | Task | FollowUp | Note;
    created_at: string;
}

/**
 * Email importance levels from Microsoft Graph.
 */
type EmailImportance = 'low' | 'normal' | 'high';

/**
 * Mirrors the `emails` Eloquent model.
 */
interface Email {
    id: number;
    microsoft_message_id: string;
    subject: string;
    sender_name: string | null;
    sender_email: string | null;
    body_preview: string | null;
    received_at: string;
    is_read: boolean;
    is_flagged: boolean;
    flag_due_date: string | null;
    importance: EmailImportance;
    categories: string[];
    sources: string[];
    web_link: string | null;
    links?: EmailLink[];
    sender_is_team_member?: boolean;
    sender_avatar_url?: string | null;
    sender_initials?: string;
    sender_display_name?: string;
    sender_avatar_color?: string;
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `email_links` Eloquent model.
 */
interface EmailLink {
    id: number;
    email_id: number | null;
    email_subject: string | null;
    linkable_type: string;
    linkable_id: number;
    linkable?: Bila | Task | FollowUp | Note;
    created_at: string;
}

/**
 * Mirrors the `jira_issues` Eloquent model.
 */
interface JiraIssue {
    id: number;
    jira_issue_id: string;
    issue_key: string;
    summary: string;
    description_preview: string | null;
    project_key: string;
    project_name: string;
    issue_type: string;
    status_name: string;
    status_category: string;
    priority_name: string | null;
    assignee_name: string | null;
    assignee_email: string | null;
    reporter_name: string | null;
    reporter_email: string | null;
    labels: string[] | null;
    web_url: string;
    sources: string[];
    updated_in_jira_at: string;
    is_dismissed: boolean;
    synced_at: string;
    jira_issue_links?: JiraIssueLink[];
    created_at: string;
    updated_at: string;
}

/**
 * Mirrors the `jira_issue_links` Eloquent model.
 */
interface JiraIssueLink {
    id: number;
    jira_issue_id: number | null;
    issue_key: string;
    issue_summary: string | null;
    linkable_type: string;
    linkable_id: number;
    linkable?: Bila | Task | FollowUp | Note;
    created_at: string;
    updated_at: string;
}

/**
 * Visual variant for system notifications.
 */
type NotificationVariant = 'info' | 'warning' | 'success' | 'error';

/**
 * Mirrors the `system_notifications` Eloquent model.
 */
interface SystemNotification {
    id: number;
    title: string;
    message: string;
    variant: NotificationVariant;
    link_url: string | null;
    link_text: string | null;
    is_active: boolean;
    expires_at: string | null;
    created_at: string;
    updated_at: string;
}

/**
 * Activity types in the activity feed.
 */
type ActivityType = 'comment' | 'attachment' | 'link' | 'system';

/**
 * Mirrors the `activities` Eloquent model.
 */
interface Activity {
    id: number;
    user_id: number;
    activityable_type: string;
    activityable_id: number;
    type: ActivityType;
    body: string | null;
    metadata: Record<string, unknown> | null;
    created_at: string;
    updated_at: string;
    attachments?: Attachment[];
}

/**
 * Mirrors the `attachments` Eloquent model.
 */
interface Attachment {
    id: number;
    user_id: number;
    activity_id: number;
    filename: string;
    path: string;
    disk: string;
    mime_type: string;
    size: number;
    download_url?: string;
    created_at: string;
    updated_at: string;
}

export type { Priority, TaskStatus, RecurrenceInterval, FollowUpStatus, MemberStatus, ChartType, DataSource, TimeRange, CalendarEventStatus, EmailImportance, NotificationVariant, ActivityType };
export type { Team, TeamMember, Task, TaskGroup, FollowUp, Bila, BilaPrepItem, Agreement, Note, WeeklyReflection, AnalyticsWidget, ChartData, TimeSeriesChartData, CalendarEvent, CalendarEventLink, Email, EmailLink, JiraIssue, JiraIssueLink, SystemNotification, Activity, Attachment };
