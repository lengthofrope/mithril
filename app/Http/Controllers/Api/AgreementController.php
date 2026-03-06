<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\AgreementRequest;
use App\Models\Agreement;

/**
 * API controller for Agreement resource CRUD operations.
 */
class AgreementController extends AbstractResourceController
{
    /**
     * @var class-string<Agreement>
     */
    protected string $modelClass = Agreement::class;

    /**
     * @var class-string<AgreementRequest>
     */
    protected string $requestClass = AgreementRequest::class;
}
