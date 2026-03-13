<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Polymorphic link between a Jira issue and an application resource.
 *
 * Uses SET NULL FK on jira_issue_id so links survive issue pruning. The denormalized
 * issue_key and issue_summary preserve provenance for display when the issue record is gone.
 *
 * @property int         $id
 * @property int|null    $jira_issue_id
 * @property string      $issue_key
 * @property string|null $issue_summary
 * @property string      $linkable_type
 * @property int         $linkable_id
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class JiraIssueLink extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'jira_issue_id',
        'issue_key',
        'issue_summary',
        'linkable_type',
        'linkable_id',
    ];

    /**
     * Get the Jira issue this link belongs to.
     *
     * @return BelongsTo<JiraIssue, JiraIssueLink>
     */
    public function jiraIssue(): BelongsTo
    {
        return $this->belongsTo(JiraIssue::class);
    }

    /**
     * Get the linked resource (Task, FollowUp, Note, or Bila).
     *
     * @return MorphTo<Model, JiraIssueLink>
     */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }
}
