<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\MeetingRequest;
use App\Models\Meeting;

/**
 * API controller for Meeting resource CRUD operations.
 */
class MeetingController extends AbstractResourceController
{
    /**
     * @var class-string<Meeting>
     */
    protected string $modelClass = Meeting::class;

    /**
     * @var class-string<MeetingRequest>
     */
    protected string $requestClass = MeetingRequest::class;
}
