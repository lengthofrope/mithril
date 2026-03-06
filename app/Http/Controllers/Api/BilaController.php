<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\BilaRequest;
use App\Models\Bila;

/**
 * API controller for Bila resource CRUD operations.
 */
class BilaController extends AbstractResourceController
{
    /**
     * @var class-string<Bila>
     */
    protected string $modelClass = Bila::class;

    /**
     * @var class-string<BilaRequest>
     */
    protected string $requestClass = BilaRequest::class;
}
