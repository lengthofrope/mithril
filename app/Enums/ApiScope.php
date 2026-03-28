<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Predefined API access tiers that map to sets of ApiAbility values.
 *
 * Each scope represents a convenience grouping: read-only, read-write,
 * or full-access. Tokens are created with individual abilities, but
 * scopes provide a UI-friendly way to select common presets.
 */
enum ApiScope: string
{
    case ReadOnly = 'read-only';
    case ReadWrite = 'read-write';
    case FullAccess = 'full-access';

    /**
     * Return the ApiAbility instances included in this scope.
     *
     * @return array<int, ApiAbility>
     */
    public function abilities(): array
    {
        return match ($this) {
            self::ReadOnly => ApiAbility::readAbilities(),
            self::ReadWrite => array_merge(ApiAbility::readAbilities(), ApiAbility::writeAbilities()),
            self::FullAccess => ApiAbility::cases(),
        };
    }

    /**
     * Return the ability string values included in this scope.
     *
     * @return array<int, string>
     */
    public function abilityValues(): array
    {
        return array_map(
            fn (ApiAbility $ability): string => $ability->value,
            $this->abilities(),
        );
    }
}
