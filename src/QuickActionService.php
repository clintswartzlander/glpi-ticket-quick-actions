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
        $actions = [];

        if (!$assigned && $ticket->canAssignToMe()) {
            $actions[] = Action::ASSIGN_TO_ME;
        }

        if ($assigned && $this->hasAssignmentMutationRight()) {
            $actions[] = Action::RELEASE;
        }

        $status = (int) $ticket->fields['status'];
        if ($ticket->canUpdateItem()) {
            if ($this->policy->canMove($status, \Ticket::WAITING, [\Ticket::class, 'isAllowedStatus'])) {
                $actions[] = Action::PENDING;
            }
            if (
                $status === \Ticket::WAITING
                && $this->policy->canMove($status, \Ticket::ASSIGNED, [\Ticket::class, 'isAllowedStatus'])
            ) {
                $actions[] = Action::RESUME;
            }
        }

        return $actions;
    }

    public function execute(int $ticketId, string $action): void
    {
        if (!Action::isValid($action)) {
            throw new DomainException(__('Unknown quick action.', 'quickactions'));
        }

        $ticket = $this->loadAuthorizedTicket($ticketId);
        if (!in_array($action, $this->availableActions($ticket), true)) {
            throw new DomainException(__('This quick action is not permitted for the ticket.', 'quickactions'));
        }

        match ($action) {
            Action::ASSIGN_TO_ME => $this->assignToMe($ticket),
            Action::RELEASE => $this->release($ticket),
            Action::PENDING => $this->changeStatus($ticket, \Ticket::WAITING),
            Action::RESUME => $this->changeStatus($ticket, \Ticket::ASSIGNED),
        };
    }

    private function assignToMe(\Ticket $ticket): void
    {
        $userId = (int) \Session::getLoginUserID();
        if ($this->findAssignedRelation((int) $ticket->getID(), $userId) !== null) {
            return;
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
            return;
        }

        $relation = new StatusPreservingTicketUser();
        if (!$relation->getFromDB($relationId) || !$relation->delete(['id' => $relationId])) {
            throw new RuntimeException('GLPI did not delete the assigned-technician relation.');
        }
    }

    private function changeStatus(\Ticket $ticket, int $targetStatus): void
    {
        $currentStatus = (int) $ticket->fields['status'];
        if (!$ticket->canUpdateItem()) {
            throw new DomainException(__('You cannot update this ticket.', 'quickactions'));
        }
        if (!$this->policy->canMove($currentStatus, $targetStatus, [\Ticket::class, 'isAllowedStatus'])) {
            throw new DomainException(__('The ticket lifecycle does not permit this status change.', 'quickactions'));
        }

        $this->updateTicketStatus($ticket, $targetStatus);
    }

    private function updateTicketStatus(\Ticket $ticket, int $status): void
    {
        if (!$ticket->update(['id' => (int) $ticket->getID(), 'status' => $status])) {
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
