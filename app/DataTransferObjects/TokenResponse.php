<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;

/**
 * Immutable data transfer object carrying a Microsoft OAuth token response.
 */
final readonly class TokenResponse
{
    /**
     * @param string         $accessToken  OAuth access token issued by Microsoft.
     * @param string         $refreshToken OAuth refresh token for obtaining new access tokens.
     * @param CarbonInterface $expiresAt   Exact moment the access token becomes invalid.
     * @param string         $microsoftId  Unique Microsoft user identifier (object ID).
     * @param string         $email        Primary email address from the Microsoft account.
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonInterface $expiresAt,
        public string $microsoftId,
        public string $email,
    ) {}
}
