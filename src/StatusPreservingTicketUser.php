<?php

declare(strict_types=1);

namespace GlpiPlugin\Quickactions;

/**
 * Deletes a Ticket_User relation without CommonITILActor's automatic
 * last-assignee status reset, while retaining native relation history.
 */
final class StatusPreservingTicketUser extends \Ticket_User
{
    public static function getTable($classname = null)
    {
        return \Ticket_User::getTable();
    }

    public function post_deleteFromDB()
    {
        global $CFG_GLPI;

        $notificationsEnabled = !isset($this->input['_disablenotif'])
            && (bool) $CFG_GLPI['use_notifications'];
        $ticketId = (int) ($this->fields['tickets_id'] ?? 0);
        $ticket = new \Ticket();

        if ($ticketId > 0 && $ticket->getFromDB($ticketId)) {
            $ticket->updateDateMod($ticketId);
            if ($notificationsEnabled) {
                \NotificationEvent::raiseEvent('update', $ticket, ['_old_user' => $this->fields]);
            }
        }

        $currentLogOption = $this->_force_log_option;
        $this->_force_log_option = $this->getForceLogOption();
        try {
            \CommonDBRelation::post_deleteFromDB();
        } finally {
            $this->_force_log_option = $currentLogOption;
        }
    }
}
