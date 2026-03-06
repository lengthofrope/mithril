<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Placeholder for WebAuthn authentication endpoints.
 *
 * Full implementation requires laragear/webauthn to be installed:
 *   composer require laragear/webauthn
 *
 * Once installed, replace this placeholder with the full implementation
 * using Laragear\WebAuthn\Http\Requests\AssertionRequest,
 * Laragear\WebAuthn\Http\Requests\AttestedRequest, etc.
 */
class WebAuthnController extends Controller
{
    /**
     * Return the WebAuthn registration challenge for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function registrationOptions(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'WebAuthn is not yet enabled. Install laragear/webauthn to activate this feature.',
        ], 501);
    }

    /**
     * Verify and store a new WebAuthn credential for the authenticated user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'WebAuthn is not yet enabled. Install laragear/webauthn to activate this feature.',
        ], 501);
    }

    /**
     * Return the WebAuthn authentication challenge.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticationOptions(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'WebAuthn is not yet enabled. Install laragear/webauthn to activate this feature.',
        ], 501);
    }

    /**
     * Verify a WebAuthn assertion and authenticate the user.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function authenticate(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'WebAuthn is not yet enabled. Install laragear/webauthn to activate this feature.',
        ], 501);
    }
}
