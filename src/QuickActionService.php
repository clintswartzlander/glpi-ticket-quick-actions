<?php

declare(strict_types=1);

namespace GlpiPlugin\Quickactions;

use DomainException;
use RuntimeException;

final class QuickActionService
{
    public function __construct(private ?ActionPolicy $policy = null)
    {
        $this->policy ??= new ActionPolicy();
    }

    /** @return list<string> */
    public function availableActions(\Ticket $ticket): array
    {
        if (!$this->isCentralSavedVisibleTicket($ticket)) {
            return [];
        }

        $userId = (int) \Session::getLoginUserID();
        $assigned = $this->findAssignedRelation((int) $ticket->getID(), $userId) !== null;
        $hasAssignment = $this->hasAnyAssignment($ticket);
        $actions = [];

        if (!$assigned && $ticket->canAssignToMe()) {
            $actions[] = Action::ASSIGN_TO_ME;
        }

        if ($assigned && $this->hasAssignmentMutationRight()) {
            $actions[] = Action::RELEASE;
        }

        $status = (int) $ticket->fields['status'];
        if ($ticket->canUpdateItem()) {
            if ($this->policy->canPend($status, [\Ticket::class, 'isAllowedStatus'])) {
                $actions[] = Action::PENDING;
            }
            if (
                $status === \Ticket::WAITING
                && $this->policy->canMove(
                    $status,
                    $this->policy->resumeTarget($hasAssignment),
                    [\Ticket::class, 'isAllowedStatus']
                )
            ) {
                $actions[] = Action::RESUME;
            }
        }

        if (
            $this->hasSolvePermission($ticket)
            && $this->policy->canSolve($status, [\Ticket::class, 'isAllowedStatus'])
            && $ticket->canSolve()
        ) {
            $actions[] = Action::SOLVE;
        }

        if (
            $ticket->canUpdateItem()
            && $this->policy->canClose($status, [\Ticket::class, 'isAllowedStatus'])
        ) {
            $actions[] = Action::CLOSE;
        }

        $reopenTarget = $this->policy->resumeTarget($hasAssignment);
        if (
            $this->hasReopenPermission($ticket)
            && $this->policy->canReopen($status, $reopenTarget, [\Ticket::class, 'isAllowedStatus'])
        ) {
            $actions[] = Action::REOPEN;
        }

        return $actions;
    }

    public function execute(int $ticketId, string $action): void
    {
        if (!Action::isValid($action)) {
            throw new DomainException(__('Unknown quick action.', 'quickactions'));
        }

        $ticket = $this->loadAuthorizedTicket($ticketId);

        match ($action) {
            Action::ASSIGN_TO_ME => $this->assignToMe($ticket),
            Action::RELEASE => $this->release($ticket),
            Action::PENDING => $this->pending($ticket),
            Action::RESUME => $this->resume($ticket),
            Action::SOLVE => $this->solve($ticket),
            Action::CLOSE => $this->close($ticket),
            Action::REOPEN => $this->reopen($ticket),
        };
    }

    private function assignToMe(\Ticket $ticket): void
    {
        $userId = (int) \Session::getLoginUserID();
        if ($this->findAssignedRelation((int) $ticket->getID(), $userId) !== null) {
            throw new DomainException(__('You are already assigned to this ticket.', 'quickactions'));
        }
        if (!$ticket->canAssignToMe()) {
            throw new DomainException(__('You cannot assign this ticket to yourself.', 'quickactions'));
        }

        $relation = new \Ticket_User();
        $relationId = $relation->add([
            'tickets_id'   => (int) $ticket->getID(),
            'users_id'     => $userId,
            'type'         => \CommonITILActor::ASSIGN,
            '_from_object' => true,
        ]);
        if (!$relationId) {
            throw new RuntimeException('GLPI did not create the assigned-technician relation.');
        }

        $status = (int) $ticket->fields['status'];
        if ($this->policy->shouldMoveNewToAssigned($status, [\Ticket::class, 'isAllowedStatus'])) {
            $this->updateTicketStatus($ticket, \Ticket::ASSIGNED);
        }
    }

    private function release(\Ticket $ticket): void
    {
        if (!$this->hasAssignmentMutationRight()) {
            throw new DomainException(__('You cannot release this assignment.', 'quickactions'));
        }

        $relationId = $this->findAssignedRelation(
            (int) $ticket->getID(),
            (int) \Session::getLoginUserID()
        );
        if ($relationId === null) {
            throw new DomainException(__('You are not assigned to this ticket.', 'quickactions'));
        }

        $relation = new StatusPreservingTicketUser();
        if (!$relation->getFromDB($relationId) || !$relation->delete(['id' => $relationId])) {
            throw new RuntimeException('GLPI did not delete the assigned-technician relation.');
        }
    }

    private function pending(\Ticket $ticket): void
    {
        $currentStatus = (int) $ticket->fields['status'];
        if (!$ticket->canUpdateItem()) {
            throw new DomainException(__('You cannot update this ticket.', 'quickactions'));
        }
        if (in_array($currentStatus, [\Ticket::WAITING, \Ticket::SOLVED, \Ticket::CLOSED], true)) {
            throw new DomainException(__('Only an active non-pending ticket can be marked Pending.', 'quickactions'));
        }
        $this->assertTransitionAllowed($currentStatus, \Ticket::WAITING);

        $this->updateTicketStatus($ticket, \Ticket::WAITING);
    }

    private function resume(\Ticket $ticket): void
    {
        $currentStatus = (int) $ticket->fields['status'];
        if ($currentStatus !== \Ticket::WAITING) {
            throw new DomainException(__('This ticket is not Pending.', 'quickactions'));
        }
        if (!$ticket->canUpdateItem()) {
            throw new DomainException(__('You do not have permission to resume this ticket.', 'quickactions'));
        }

        $targetStatus = $this->policy->resumeTarget($this->hasAnyAssignment($ticket));
        $this->assertTransitionAllowed($currentStatus, $targetStatus);

        $this->updateTicketStatus($ticket, $targetStatus);
    }

    private function solve(\Ticket $ticket): void
    {
        $currentStatus = (int) $ticket->fields['status'];
        if ($currentStatus === \Ticket::SOLVED) {
            throw new DomainException(__('This ticket is already solved.', 'quickactions'));
        }
        if ($currentStatus === \Ticket::CLOSED) {
            throw new DomainException(__('A closed ticket must be reopened before it can be solved.', 'quickactions'));
        }
        if (!$this->hasSolvePermission($ticket)) {
            throw new DomainException(__('You do not have permission to solve this ticket.', 'quickactions'));
        }

        $this->assertTransitionAllowed($currentStatus, \Ticket::SOLVED);
        if (!$ticket->canSolve()) {
            throw new DomainException(__('You do not have permission to solve this ticket.', 'quickactions'));
        }
        if (!$ticket->checkRequiredFieldsFilled()) {
            throw new DomainException(
                __('Complete the ticket\'s required fields before adding a solution.', 'quickactions')
            );
        }
        try {
            $this->updateTicketStatus($ticket, \Ticket::SOLVED);
        } catch (RuntimeException $exception) {
            if (!$this->hasSolution($ticket)) {
                throw new DomainException(
                    __('This ticket must have a solution before it can be solved. Use GLPI\'s normal Add Solution workflow.', 'quickactions'),
                    previous: $exception
                );
            }

            throw $exception;
        }
    }

    private function close(\Ticket $ticket): void
    {
        $currentStatus = (int) $ticket->fields['status'];
        if ($currentStatus !== \Ticket::SOLVED) {
            throw new DomainException(__('Only a solved ticket can be closed.', 'quickactions'));
        }
        if (!$ticket->canUpdateItem()) {
            throw new DomainException(__('You do not have permission to close this ticket.', 'quickactions'));
        }

        $this->assertTransitionAllowed($currentStatus, \Ticket::CLOSED);
        $this->updateTicketStatus($ticket, \Ticket::CLOSED);
    }

    private function reopen(\Ticket $ticket): void
    {
        $currentStatus = (int) $ticket->fields['status'];
        if (!in_array($currentStatus, [\Ticket::SOLVED, \Ticket::CLOSED], true)) {
            throw new DomainException(__('Only a solved or closed ticket can be reopened.', 'quickactions'));
        }
        if (!$this->hasReopenPermission($ticket)) {
            throw new DomainException(__('You do not have permission to reopen this ticket.', 'quickactions'));
        }

        $targetStatus = $this->policy->resumeTarget($this->hasAnyAssignment($ticket));
        $this->assertTransitionAllowed($currentStatus, $targetStatus);

        $this->updateTicketStatus($ticket, $targetStatus);
    }

    private function assertTransitionAllowed(int $currentStatus, int $targetStatus): void
    {
        if ($this->policy->canMove($currentStatus, $targetStatus, [\Ticket::class, 'isAllowedStatus'])) {
            return;
        }

        throw new DomainException(sprintf(
            __('The transition from %1$s to %2$s is not allowed by the current lifecycle configuration.', 'quickactions'),
            \Ticket::getStatus($currentStatus),
            \Ticket::getStatus($targetStatus)
        ));
    }

    private function updateTicketStatus(\Ticket $ticket, int $status): void
    {
        $ticketId = (int) $ticket->getID();
        if (
            !$ticket->update(['id' => $ticketId, 'status' => $status])
            || !$ticket->getFromDB($ticketId)
            || (int) $ticket->fields['status'] !== $status
        ) {
            throw new RuntimeException('GLPI did not update the ticket status.');
        }
    }

    private function loadAuthorizedTicket(int $ticketId): \Ticket
    {
        if (\Session::getCurrentInterface() !== 'central') {
            throw new DomainException(__('Quick actions are unavailable in the Self-Service interface.', 'quickactions'));
        }
        if ($ticketId <= 0) {
            throw new DomainException(__('Invalid ticket.', 'quickactions'));
        }

        $ticket = new \Ticket();
        if (!$ticket->getFromDB($ticketId) || $ticket->isNewItem() || !$ticket->canViewItem()) {
            throw new DomainException(__('The ticket does not exist or is not visible.', 'quickactions'));
        }

        return $ticket;
    }

    private function isCentralSavedVisibleTicket(\Ticket $ticket): bool
    {
        return \Session::getCurrentInterface() === 'central'
            && !$ticket->isNewItem()
            && (int) $ticket->getID() > 0
            && $ticket->canViewItem();
    }

    private function hasAssignmentMutationRight(): bool
    {
        return \Session::haveRightsOr(\Ticket::$rightname, [
            UPDATE,
            \Ticket::ASSIGN,
            \Ticket::OWN,
            \Ticket::STEAL,
        ]);
    }

    private function hasSolvePermission(\Ticket $ticket): bool
    {
        $userId = (int) \Session::getLoginUserID();
        $groups = isset($_SESSION['glpigroups']) && is_array($_SESSION['glpigroups'])
            ? $_SESSION['glpigroups']
            : [];

        return \Session::haveRight(\Ticket::$rightname, UPDATE)
            || $ticket->isUser(\CommonITILActor::ASSIGN, $userId)
            || $ticket->haveAGroup(\CommonITILActor::ASSIGN, $groups);
    }

    private function hasReopenPermission(\Ticket $ticket): bool
    {
        if (!$ticket->canUpdateItem()) {
            return false;
        }

        return (int) $ticket->fields['status'] === \Ticket::SOLVED || $ticket->canReopen();
    }

    private function hasAnyAssignment(\Ticket $ticket): bool
    {
        return $ticket->countUsers(\CommonITILActor::ASSIGN) > 0
            || $ticket->countGroups(\CommonITILActor::ASSIGN) > 0;
    }

    private function hasSolution(\Ticket $ticket): bool
    {
        return \ITILSolution::countFor(\Ticket::class, (int) $ticket->getID()) > 0;
    }

    private function findAssignedRelation(int $ticketId, int $userId): ?int
    {
        if ($ticketId <= 0 || $userId <= 0) {
            return null;
        }

        $relation = new \Ticket_User();
        $rows = $relation->find([
            'tickets_id' => $ticketId,
            'users_id'   => $userId,
            'type'       => \CommonITILActor::ASSIGN,
        ]);
        if ($rows === []) {
            return null;
        }

        return (int) array_key_first($rows);
    }
}
