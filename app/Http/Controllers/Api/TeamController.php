<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\TeamRequest;
use App\Models\Team;

/**
 * API controller for Team resource CRUD operations.
 */
class TeamController extends AbstractResourceController
{
    /**
     * @var class-string<Team>
     */
    protected string $modelClass = Team::class;

    /**
     * @var class-string<TeamRequest>
     */
    protected string $requestClass = TeamRequest::class;
}
