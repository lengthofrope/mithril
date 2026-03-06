<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Abstract base controller providing generic CRUD operations for Eloquent models.
 *
 * Concrete controllers must define $modelClass and $requestClass.
 * Override individual methods to customize behavior per resource.
 */
abstract class AbstractResourceController extends Controller
{
    use ApiResponse;

    /**
     * The fully qualified Eloquent model class name.
     *
     * @var class-string<Model>
     */
    protected string $modelClass;

    /**
     * The fully qualified Form Request class name for store/update.
     *
     * @var class-string<FormRequest>
     */
    protected string $requestClass;

    /**
     * Return a paginated or full list of the resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = $this->modelClass::query();

        if (method_exists($this->modelClass, 'scopeOrderBySortOrder')) {
            $query->orderBySortOrder();
        }

        $data = $query->get();

        return $this->successResponse($data);
    }

    /**
     * Store a newly created resource.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $this->resolveRequest($request)->validated();

        $model = $this->modelClass::create($validated);

        return $this->successResponse($model, 'Created successfully.', 201);
    }

    /**
     * Update an existing resource by ID.
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $model = $this->modelClass::findOrFail($id);

        $validated = $this->resolveRequest($request)->validated();
        $model->update($validated);

        return $this->successResponse($model->fresh(), 'Updated successfully.', 200, true);
    }

    /**
     * Delete a resource by ID.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy(int $id): JsonResponse
    {
        $model = $this->modelClass::findOrFail($id);
        $model->delete();

        return $this->successResponse(null, 'Deleted successfully.');
    }

    /**
     * Resolve and validate the incoming request using the configured request class.
     *
     * @param Request $request
     * @return FormRequest
     */
    private function resolveRequest(Request $request): FormRequest
    {
        return app($this->requestClass);
    }
}
