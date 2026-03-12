<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Traits\BelongsToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Cached Jira issue metadata synced from Jira Cloud REST API.
 *
 * @property int                          $id
 * @property int                          $user_id
 * @property string                       $jira_issue_id
 * @property string                       $issue_key
 * @property string                       $summary
 * @property string|null                  $description_preview
 * @property string                       $project_key
 * @property string                       $project_name
 * @property string                       $issue_type
 * @property string                       $status_name
 * @property string                       $status_category
 * @property string|null                  $priority_name
 * @property string|null                  $assignee_name
 * @property string|null                  $assignee_email
 * @property string|null                  $reporter_name
 * @property string|null                  $reporter_email
 * @property array<int, string>|null      $labels
 * @property string                       $web_url
 * @property array<int, string>           $sources
 * @property \Illuminate\Support\Carbon   $updated_in_jira_at
 * @property bool                         $is_dismissed
 * @property \Illuminate\Support\Carbon   $synced_at
 * @property \Illuminate\Support\Carbon   $created_at
 * @property \Illuminate\Support\Carbon   $updated_at
 */
class JiraIssue extends Model
{
    use BelongsToUser;
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'jira_issue_id',
        'issue_key',
        'summary',
        'description_preview',
        'project_key',
        'project_name',
        'issue_type',
        'status_name',
        'status_category',
        'priority_name',
        'assignee_name',
        'assignee_email',
        'reporter_name',
        'reporter_email',
        'labels',
        'web_url',
        'sources',
        'updated_in_jira_at',
        'is_dismissed',
        'synced_at',
    ];

    /**
     * Get the casts for this model.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'labels'             => 'array',
            'sources'            => 'array',
            'updated_in_jira_at' => 'datetime',
            'is_dismissed'       => 'boolean',
            'synced_at'          => 'datetime',
        ];
    }

    /**
     * Get all resource links for this Jira issue.
     *
     * @return HasMany<JiraIssueLink>
     */
    public function jiraIssueLinks(): HasMany
    {
        return $this->hasMany(JiraIssueLink::class);
    }
}
