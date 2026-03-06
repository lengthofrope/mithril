<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\TaskRequest;
use App\Models\Task;

/**
 * API controller for Task resource CRUD operations.
 */
class TaskController extends AbstractResourceController
{
    /**
     * @var class-string<Task>
     */
    protected string $modelClass = Task::class;

    /**
     * @var class-string<TaskRequest>
     */
    protected string $requestClass = TaskRequest::class;
}
