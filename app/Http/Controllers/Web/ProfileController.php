<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\BreadcrumbBuilder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

/**
 * Handles the user profile page rendering and update actions.
 *
 * Manages profile information, password changes, and avatar uploads.
 */
class ProfileController extends Controller
{
    /**
     * Display the profile edit page for the authenticated user.
     *
     * @param Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        return view('pages.profile.index', [
            'title' => 'Edit Profile',
            'breadcrumbs' => (new BreadcrumbBuilder())->forPage('Profile')->build(),
            'user' => $request->user(),
        ]);
    }

    /**
     * Update the authenticated user's profile information.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
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

        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }

        $user->save();

        return redirect()->route('profile.index')->with('status', 'Profile updated successfully.');
    }

    /**
     * Upload and store a new avatar for the authenticated user.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'image', 'max:2048'],
        ]);

        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
        }

        $path = $request->file('avatar')->store('avatars', 'public');

        $user->update(['avatar_path' => $path]);

        return redirect()->route('profile.index')->with('status', 'Avatar updated successfully.');
    }

    /**
     * Remove the avatar for the authenticated user.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function deleteAvatar(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->avatar_path) {
            Storage::disk('public')->delete($user->avatar_path);
            $user->update(['avatar_path' => null]);
        }

        return redirect()->route('profile.index')->with('status', 'Avatar removed.');
    }
}
