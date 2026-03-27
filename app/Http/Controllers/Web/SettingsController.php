<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\ApiAbility;
use App\Enums\ApiScope;
use App\Enums\SpeechServiceMode;
use App\Http\Controllers\Controller;
use App\Models\Attachment;
use App\Models\MeetingRecording;
use App\Models\TaskCategory;
use App\Models\TaskGroup;
use App\Services\BreadcrumbBuilder;
use App\Services\DataPruningService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Handles the settings page rendering and profile update actions.
 *
 * Manages theme preference and profile details.
 */
class SettingsController extends Controller
{
    /**
     * Maximum number of personal access tokens a user may have.
     */
    private const int MAX_TOKENS_PER_USER = 25;
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
            'current_password' => ['required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ]);

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
            'prune_after_days' => ['required', 'integer', 'min:30', 'max:365'],
        ]);

        $request->user()->update(['prune_after_days' => $validated['prune_after_days']]);

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
            'dashboard_upcoming_meetings' => ['nullable', 'integer', 'min:0', 'max:20'],
        ]);

        $request->user()->update([
            'dashboard_upcoming_tasks' => $validated['dashboard_upcoming_tasks'] ?? null,
            'dashboard_upcoming_follow_ups' => $validated['dashboard_upcoming_follow_ups'] ?? null,
            'dashboard_upcoming_meetings' => $validated['dashboard_upcoming_meetings'] ?? null,
        ]);

        return response()->json(['success' => true]);
    }

    /**
     * Update the authenticated user's speech service settings.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function updateSpeechService(Request $request): JsonResponse
    {
        $customUrlEnabled = config('meetings.custom_url_enabled', false);
        $mode = $request->input('speech_service_mode');

        $rules = [
            'speech_service_mode' => ['required', Rule::enum(SpeechServiceMode::class)],
            'speech_service_token' => ['nullable', 'string'],
        ];

        if (!$customUrlEnabled && $mode !== SpeechServiceMode::Server->value) {
            $rules['speech_service_mode'][] = Rule::in([SpeechServiceMode::Server->value]);
        }

        if ($mode === 'local') {
            $rules['speech_service_url'] = ['required', 'string', 'url'];
        } else {
            $rules['speech_service_url'] = ['nullable', 'string'];
        }

        $validated = $request->validate($rules);

        $request->user()->update([
            'speech_service_mode' => $validated['speech_service_mode'],
            'speech_service_url' => $validated['speech_service_url'] ?? $request->user()->speech_service_url,
            'speech_service_token' => array_key_exists('speech_service_token', $validated)
                ? $validated['speech_service_token']
                : $request->user()->speech_service_token,
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
     * Display the storage management sub-page with usage and attachment list.
     *
     * @param Request $request
     * @return View
     */
    public function storage(Request $request): View
    {
        $user = $request->user();
        $attachmentBytes = (int) Attachment::where('user_id', $user->id)->sum('size');
        $recordingBytes = (int) MeetingRecording::where('user_id', $user->id)->sum('size_bytes');
        $usedBytes = $attachmentBytes + $recordingBytes;
        $maxBytes = config('attachments.max_storage_mb') * 1024 * 1024;

        $attachments = Attachment::where('user_id', $user->id)
            ->with('activity.activityable')
            ->orderByDesc('created_at')
            ->get();

        $recordings = MeetingRecording::where('user_id', $user->id)
            ->with('meeting')
            ->orderByDesc('created_at')
            ->get();

        $orphaned = $attachments->filter(
            fn (Attachment $a) => !$a->activity || !$a->activity->activityable,
        );

        return view('pages.settings.storage', [
            'title' => 'Storage',
            'breadcrumbs' => (new BreadcrumbBuilder())
                ->forPage('Settings', route('settings.index'))
                ->addCrumb('Storage')
                ->build(),
            'usedBytes' => $usedBytes,
            'maxBytes' => $maxBytes,
            'attachments' => $attachments,
            'recordings' => $recordings,
            'orphanedBytes' => (int) $orphaned->sum('size'),
            'orphanedCount' => $orphaned->count(),
        ]);
    }

    /**
     * Delete all attachments whose parent resource no longer exists.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function purgeOrphaned(Request $request): RedirectResponse
    {
        $user = $request->user();

        $attachments = Attachment::where('user_id', $user->id)
            ->with('activity.activityable')
            ->get();

        $deleted = 0;

        foreach ($attachments as $attachment) {
            if (!$attachment->activity || !$attachment->activity->activityable) {
                $activity = $attachment->activity;
                $attachment->delete();
                $deleted++;

                if ($activity && $activity->attachments()->count() === 0) {
                    $activity->delete();
                }
            }
        }

        return redirect()->route('settings.storage')
            ->with('status', $deleted > 0
                ? "Removed {$deleted} orphaned " . ($deleted === 1 ? 'file' : 'files') . '.'
                : 'No orphaned files found.');
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
            'color' => ['nullable', 'string', 'regex:/^#[0-9A-Fa-f]{3,6}$/'],
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
            'name' => ['required', 'string', 'max:255', Rule::unique('task_categories', 'name')->where('user_id', auth()->id())],
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

    /**
     * Display the API settings sub-page with token list and creation form.
     *
     * @param Request $request
     * @return View
     */
    public function api(Request $request): View
    {
        $user = $request->user();
        $tokens = $user->tokens()->orderByDesc('created_at')->get();

        $scopeAbilityMap = [];
        foreach (ApiScope::cases() as $scope) {
            $scopeAbilityMap[$scope->value] = $scope->abilityValues();
        }

        return view('pages.settings.api', [
            'title' => 'API',
            'breadcrumbs' => (new BreadcrumbBuilder())
                ->forPage('Settings', route('settings.index'))
                ->addCrumb('API')
                ->build(),
            'tokens' => $tokens,
            'groupedAbilities' => ApiAbility::groupedByResource(),
            'scopes' => ApiScope::cases(),
            'scopeAbilityMap' => $scopeAbilityMap,
        ]);
    }

    /**
     * Create a new personal access token with the given name and abilities.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function storeToken(Request $request): JsonResponse
    {
        if ($request->user()->tokens()->count() >= self::MAX_TOKENS_PER_USER) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum number of tokens (' . self::MAX_TOKENS_PER_USER . ') reached. Please revoke an existing token first.',
            ], 422);
        }

        $validAbilities = array_map(
            fn (ApiAbility $a): string => $a->value,
            ApiAbility::cases(),
        );

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'scope' => ['nullable', 'string', Rule::in(array_map(fn (ApiScope $s) => $s->value, ApiScope::cases()))],
            'abilities' => ['nullable', 'array', 'min:1'],
            'abilities.*' => ['string', Rule::in($validAbilities)],
        ]);

        $scope = isset($validated['scope']) ? ApiScope::from($validated['scope']) : null;
        $abilities = $scope
            ? $scope->abilityValues()
            : ($validated['abilities'] ?? []);

        if (empty($abilities) || in_array('*', $abilities, true)) {
            return response()->json([
                'success' => false,
                'message' => 'At least one scope or ability is required.',
                'errors' => ['scope' => ['At least one scope or ability is required.']],
            ], 422);
        }

        $token = $request->user()->createToken($validated['name'], $abilities);

        return response()->json([
            'success' => true,
            'data' => [
                'plaintext_token' => $token->plainTextToken,
                'token' => [
                    'id' => $token->accessToken->id,
                    'name' => $token->accessToken->name,
                    'abilities' => $token->accessToken->abilities,
                    'created_at' => $token->accessToken->created_at?->toIso8601String(),
                ],
            ],
        ]);
    }

    /**
     * Revoke a specific personal access token owned by the authenticated user.
     *
     * @param Request $request
     * @param int $tokenId
     * @return JsonResponse
     */
    public function destroyToken(Request $request, int $tokenId): JsonResponse
    {
        $deleted = $request->user()->tokens()->where('id', $tokenId)->delete();

        if ($deleted === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Token not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Token revoked.',
        ]);
    }

    /**
     * Revoke all personal access tokens for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function destroyAllTokens(Request $request): JsonResponse
    {
        $request->user()->tokens()->delete();

        return response()->json([
            'success' => true,
            'message' => 'All tokens revoked.',
        ]);
    }
}
