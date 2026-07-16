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

    /**
     * @param callable(int, int): bool $isAllowed
     */
    public function canPend(int $currentStatus, callable $isAllowed): bool
    {
        return !in_array($currentStatus, [\Ticket::WAITING, \Ticket::SOLVED, \Ticket::CLOSED], true)
            && $this->canMove($currentStatus, \Ticket::WAITING, $isAllowed);
    }

    /**
     * @param callable(int, int): bool $isAllowed
     */
    public function canSolve(int $currentStatus, callable $isAllowed): bool
    {
        return !in_array($currentStatus, [\Ticket::SOLVED, \Ticket::CLOSED], true)
            && $this->canMove($currentStatus, \Ticket::SOLVED, $isAllowed);
    }

    /**
     * @param callable(int, int): bool $isAllowed
     */
    public function canClose(int $currentStatus, callable $isAllowed): bool
    {
        return $currentStatus === \Ticket::SOLVED
            && $this->canMove($currentStatus, \Ticket::CLOSED, $isAllowed);
    }

    /**
     * @param callable(int, int): bool $isAllowed
     */
    public function canReopen(int $currentStatus, int $targetStatus, callable $isAllowed): bool
    {
        return in_array($currentStatus, [\Ticket::SOLVED, \Ticket::CLOSED], true)
            && $this->canMove($currentStatus, $targetStatus, $isAllowed);
    }

    public function resumeTarget(bool $hasAssignment): int
    {
        return $hasAssignment ? \Ticket::ASSIGNED : \Ticket::INCOMING;
    }
}
