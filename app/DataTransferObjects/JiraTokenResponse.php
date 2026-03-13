<?php

declare(strict_types=1);

namespace App\DataTransferObjects;

use Carbon\CarbonInterface;

/**
 * Immutable data transfer object carrying an Atlassian OAuth token response.
 */
final readonly class JiraTokenResponse
{
    /**
     * @param string          $accessToken  OAuth access token issued by Atlassian.
     * @param string          $refreshToken OAuth refresh token for obtaining new access tokens.
     * @param CarbonInterface $expiresAt    Exact moment the access token becomes invalid.
     * @param string          $cloudId      Atlassian Cloud site identifier.
     * @param string          $siteUrl      Atlassian Cloud site URL (e.g. https://mysite.atlassian.net).
     * @param string          $accountId    Atlassian account identifier.
     */
    public function __construct(
        public string $accessToken,
        public string $refreshToken,
        public CarbonInterface $expiresAt,
        public string $cloudId,
        public string $siteUrl,
        public string $accountId,
    ) {}
}
