<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\FollowUpRequest;
use App\Models\FollowUp;

/**
 * API controller for FollowUp resource CRUD operations.
 */
class FollowUpController extends AbstractResourceController
{
    /**
     * @var class-string<FollowUp>
     */
    protected string $modelClass = FollowUp::class;

    /**
     * @var class-string<FollowUpRequest>
     */
    protected string $requestClass = FollowUpRequest::class;
}
