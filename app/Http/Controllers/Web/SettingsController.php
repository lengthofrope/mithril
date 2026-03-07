<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
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
 * Manages theme preference, push notification toggle, and profile details.
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
            'pushEnabled' => (bool) ($user->push_enabled ?? false),
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
            'push_enabled' => ['boolean'],
            'current_password' => ['nullable', 'string'],
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
        $user->push_enabled = (bool) ($validated['push_enabled'] ?? false);

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('settings.index')->with('status', 'Profile updated successfully.');
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
            'categories' => TaskCategory::orderBySortOrder()->get(),
            'groups' => TaskGroup::orderBySortOrder()->get(),
        ]);
    }

    /**
     * Create a new task group.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeTaskGroup(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        TaskGroup::create($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a task group.
     *
     * @param TaskGroup $taskGroup
     * @return JsonResponse
     */
    public function destroyTaskGroup(TaskGroup $taskGroup): JsonResponse
    {
        $taskGroup->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Create a new task category.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeCategory(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:task_categories,name'],
        ]);

        TaskCategory::create($validated);

        return response()->json(['success' => true]);
    }

    /**
     * Delete a task category.
     *
     * @param TaskCategory $taskCategory
     * @return JsonResponse
     */
    public function destroyCategory(TaskCategory $taskCategory): JsonResponse
    {
        $taskCategory->delete();

        return response()->json(['success' => true]);
    }
}
