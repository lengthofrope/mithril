<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

/**
 * Handles enabling, confirming, and disabling two-factor authentication.
 */
class TwoFactorController extends Controller
{
    /**
     * Show the two-factor challenge form.
     *
     * @return View
     */
    public function showChallenge(): View
    {
        return view('auth.two-factor-challenge', ['title' => 'Two-Factor Challenge']);
    }

    /**
     * Verify the two-factor challenge code or recovery code.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function verifyChallenge(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($request->filled('recovery_code')) {
            return $this->verifyRecoveryCode($request, $user);
        }

        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->input('code'))) {
            return redirect()->route('two-factor.challenge')
                ->withErrors(['code' => 'The provided two-factor code is invalid.']);
        }

        $request->session()->put('two_factor_authenticated', true);

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Generate a new two-factor secret and show the QR code for the user to scan.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        $google2fa = new Google2FA();

        $secret = $google2fa->generateSecretKey();

        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_recovery_codes' => encrypt(json_encode($this->generateRecoveryCodes())),
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->route('profile.index')->with('two_factor_setup', true);
    }

    /**
     * Confirm two-factor authentication by validating a TOTP code from the authenticator app.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();

        if (!$user->two_factor_secret) {
            return redirect()->route('profile.index')->withErrors(['code' => 'Two-factor authentication has not been enabled.']);
        }

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        if (!$google2fa->verifyKey($secret, $request->input('code'))) {
            return redirect()->route('profile.index')
                ->with('two_factor_setup', true)
                ->withErrors(['code' => 'The provided code is invalid.']);
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
        ]);

        return redirect()->route('profile.index')->with('status', 'Two-factor authentication enabled successfully.');
    }

    /**
     * Disable two-factor authentication for the authenticated user.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
        ]);

        $request->user()->update([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ]);

        return redirect()->route('profile.index')->with('status', 'Two-factor authentication has been disabled.');
    }

    /**
     * Generate the QR code SVG string for the user's two-factor secret.
     *
     * @param User $user
     * @return string
     */
    public static function generateQrCodeSvg(User $user): string
    {
        if (!$user->two_factor_secret) {
            return '';
        }

        $google2fa = new Google2FA();
        $secret = decrypt($user->two_factor_secret);

        $qrCodeUrl = $google2fa->getQRCodeUrl(
            config('app.name'),
            $user->email,
            $secret
        );

        $renderer = new ImageRenderer(
            new RendererStyle(192),
            new SvgImageBackEnd()
        );

        $writer = new Writer($renderer);

        return $writer->writeString($qrCodeUrl);
    }

    /**
     * Generate a set of recovery codes.
     *
     * @return array<int, string>
     */
    private function generateRecoveryCodes(): array
    {
        return Collection::times(8, fn () => Str::random(10) . '-' . Str::random(10))->all();
    }

    /**
     * Verify a recovery code and consume it if valid.
     *
     * @param Request $request
     * @param User $user
     * @return RedirectResponse
     */
    private function verifyRecoveryCode(Request $request, User $user): RedirectResponse
    {
        $recoveryCodes = json_decode(decrypt($user->two_factor_recovery_codes), true);
        $recoveryCode = $request->input('recovery_code');

        if (!in_array($recoveryCode, $recoveryCodes, true)) {
            return redirect()->route('two-factor.challenge')
                ->withErrors(['code' => 'The provided recovery code is invalid.']);
        }

        $remainingCodes = array_values(array_filter(
            $recoveryCodes,
            fn (string $code) => $code !== $recoveryCode,
        ));

        $user->update([
            'two_factor_recovery_codes' => encrypt(json_encode($remainingCodes)),
        ]);

        $request->session()->put('two_factor_authenticated', true);

        return redirect()->intended(route('dashboard'));
    }
}
