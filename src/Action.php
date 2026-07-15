<?php

declare(strict_types=1);

namespace GlpiPlugin\Quickactions;

final class Action
{
    public const ASSIGN_TO_ME = 'assign_to_me';
    public const RELEASE = 'release';
    public const PENDING = 'pending';
    public const RESUME = 'resume';

    /** @return list<string> */
    public static function all(): array
    {
        return [self::ASSIGN_TO_ME, self::RELEASE, self::PENDING, self::RESUME];
    }

    public static function isValid(string $action): bool
    {
        return in_array($action, self::all(), true);
    }
}
