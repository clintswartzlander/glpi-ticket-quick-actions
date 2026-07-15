<?php

declare(strict_types=1);

namespace GlpiPlugin\Quickactions;

final class PanelRenderer
{
    /** @param array{item?: mixed, options?: array<mixed>} $params */
    public static function render(array $params): void
    {
        global $CFG_GLPI;

        $ticket = $params['item'] ?? null;
        if (!$ticket instanceof \Ticket) {
            return;
        }

        $service = new QuickActionService();
        $actions = $service->availableActions($ticket);
        if ($actions === []) {
            return;
        }

        $labels = [
            Action::ASSIGN_TO_ME => [__('Assign to Me', 'quickactions'), 'ti ti-user-check'],
            Action::RELEASE => [__('Release Assignment', 'quickactions'), 'ti ti-user-minus'],
            Action::PENDING => [__('Pending', 'quickactions'), 'ti ti-player-pause'],
            Action::RESUME => [__('Resume', 'quickactions'), 'ti ti-player-play'],
        ];
        $endpoint = $CFG_GLPI['root_doc'] . '/plugins/quickactions/front/action.form.php';

        echo '<section class="quickactions-panel" aria-labelledby="quickactions-title">';
        echo '<div class="quickactions-panel__heading">';
        echo '<i class="ti ti-bolt" aria-hidden="true"></i>';
        echo '<h3 id="quickactions-title">' . htmlescape(__('Quick Actions', 'quickactions')) . '</h3>';
        echo '</div><div class="quickactions-panel__actions">';

        foreach ($actions as $action) {
            [$label, $icon] = $labels[$action];
            echo '<form method="post" action="' . htmlescape($endpoint) . '">';
            echo '<input type="hidden" name="_glpi_csrf_token" value="'
                . htmlescape(\Session::getNewCSRFToken(true)) . '">';
            echo '<input type="hidden" name="tickets_id" value="' . (int) $ticket->getID() . '">';
            echo '<input type="hidden" name="action" value="' . htmlescape($action) . '">';
            echo '<button type="submit" class="btn btn-outline-secondary quickactions-panel__button">';
            echo '<i class="' . htmlescape($icon) . '" aria-hidden="true"></i>';
            echo '<span>' . htmlescape($label) . '</span></button></form>';
        }

        echo '</div></section>';
    }
}
