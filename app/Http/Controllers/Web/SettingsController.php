<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Services\BreadcrumbBuilder;
use App\Services\DataPruningService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

/**
 * Handles the settings page rendering and profile update actions.
 *
 * Manages theme preference and profile details.
 */
class SettingsController extends Controller
{
    /**
     * Display the settings page for the authenticated user.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('pages.settings.index', [
            'title' => 'Settings',
            'user' => $user,
        ]);
    }

    /**
     * Update the authenticated user's profile information.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'theme_preference' => ['required', 'string', 'in:light,dark'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

        if (isset($validated['current_password']) && $validated['current_password'] !== '') {
            if (!Hash::check($validated['current_password'], $user->password)) {
                return back()->withErrors(['current_password' => 'The current password is incorrect.']);
            }
        }

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->theme_preference = $validated['theme_preference'];

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('settings.index')->with('status', 'Profile updated successfully.');
    }

    /**
     * Update the authenticated user's prune retention period.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updatePruneAfterDays(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prune_after_days' => ['nullable', 'integer', 'min:30', 'max:365'],
        ]);

        $request->user()->update(['prune_after_days' => $validated['prune_after_days'] ?? null]);

        return response()->json(['success' => true]);
    }

    /**
     * Manually trigger data pruning for the authenticated user.
     *
     * @param Request $request
     * @param DataPruningService $service
     * @return RedirectResponse
     */
    public function prune(Request $request, DataPruningService $service): RedirectResponse
    {
        $user = $request->user();

        if ($user->prune_after_days === null) {
            return redirect()->route('settings.index')
                ->with('error', 'Pruning is not configured. Set a retention period first.');
        }

        $result = $service->pruneForUser($user);

        return redirect()->route('settings.index')
            ->with('status', "Removed {$result->tasksDeleted} task(s), {$result->followUpsDeleted} follow-up(s), and {$result->emailsDeleted} email(s).");
    }

    /**
     * Update the authenticated user's timezone preference.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateTimezone(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        $request->user()->update(['timezone' => $validated['timezone']]);

        return response()->json(['success' => true]);
    }

    /**
     * Update the authenticated user's dashboard widget upcoming-items settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateDashboardWidgets(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'dashboard_upcoming_tasks' => ['nullable', 'integer', 'min:0', 'max:20'],
            'dashboard_upcoming_follow_ups' => ['nullable', 'integer', 'min:0', 'max:20'],
            'dashboard_upcoming_bilas' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $request->user()->update([
            'dashboard_upcoming_tasks' => $validated['dashboard_upcoming_tasks'] ?? null,
            'dashboard_upcoming_follow_ups' => $validated['dashboard_upcoming_follow_ups'] ?? null,
            'dashboard_upcoming_bilas' => $validated['dashboard_upcoming_bilas'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Update the authenticated user's sidebar collapsed preference.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSidebarCollapsed(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'sidebar_collapsed' => ['required', 'boolean'],
        ]);

        $request->user()->update(['sidebar_collapsed' => $validated['sidebar_collapsed']]);

        return response()->json(['success' => true]);
    }

    /**
     * Display the task settings sub-page with categories and groups.
     *
     * @param Request $request
     * @return View
     */
    public function tasks(Request $request): View
    {
        return view('pages.settings.tasks', [
            'title' => 'Task Settings',
            'breadcrumbs' => (new BreadcrumbBuilder())->forPage('Settings', route('settings.index'))->addCrumb('Task Settings')->build(),
            'categories' => TaskCategory::orderBySortOrder()->get(),
            'groups' => TaskGroup::orderBySortOrder()->get(),
        ]);
    }

    /**
     * Create a new task group.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeTaskGroup(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        TaskGroup::create($validated);

        return redirect()->back();
    }

    /**
     * Delete a task group.
     *
     * @param TaskGroup $taskGroup
     * @return RedirectResponse
     */
    public function destroyTaskGroup(TaskGroup $taskGroup): RedirectResponse
    {
        $taskGroup->delete();

        return redirect()->back();
    }

    /**
     * Create a new task category.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function storeCategory(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_categories,name'],
        ]);

        TaskCategory::create($validated);

        return redirect()->back();
    }

    /**
     * Delete a task category.
     *
     * @param TaskCategory $taskCategory
     * @return RedirectResponse
     */
    public function destroyCategory(TaskCategory $taskCategory): RedirectResponse
    {
        $taskCategory->delete();

        return redirect()->back();
    }
}
