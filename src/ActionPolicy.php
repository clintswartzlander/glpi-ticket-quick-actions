<?php

declare(strict_types=1);

namespace GlpiPlugin\Quickactions;

final class ActionPolicy
{
    /**
     * @param callable(int, int): bool $isAllowed
     */
    public function shouldMoveNewToAssigned(int $currentStatus, callable $isAllowed): bool
    {
        return $currentStatus === \Ticket::INCOMING
            && $isAllowed(\Ticket::INCOMING, \Ticket::ASSIGNED);
    }

    /**
     * @param callable(int, int): bool $isAllowed
     */
    public function canMove(int $currentStatus, int $targetStatus, callable $isAllowed): bool
    {
        return $currentStatus !== $targetStatus && $isAllowed($currentStatus, $targetStatus);
    }
}
