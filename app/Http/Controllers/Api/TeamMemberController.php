<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\TeamMemberRequest;
use App\Models\TeamMember;

/**
 * API controller for TeamMember resource CRUD operations.
 */
class TeamMemberController extends AbstractResourceController
{
    /**
     * @var class-string<TeamMember>
     */
    protected string $modelClass = TeamMember::class;

    /**
     * @var class-string<TeamMemberRequest>
     */
    protected string $requestClass = TeamMemberRequest::class;
}
