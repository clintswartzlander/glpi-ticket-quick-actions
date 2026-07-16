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
        $csrfToken = \Session::getNewCSRFToken();

        echo '<section class="quickactions-panel" aria-labelledby="quickactions-title">';
        echo '<div class="quickactions-panel__heading">';
        echo '<i class="ti ti-bolt" aria-hidden="true"></i>';
        echo '<h3 id="quickactions-title">' . htmlescape(__('Quick Actions', 'quickactions')) . '</h3>';
        echo '</div><div class="quickactions-panel__actions">';

        foreach ($actions as $action) {
            [$label, $icon] = $labels[$action];
            echo '<button type="button" class="btn btn-outline-secondary quickactions-panel__button"';
            echo ' data-quickactions-control="true"';
            echo ' data-quickactions-endpoint="' . htmlescape($endpoint) . '"';
            echo ' data-quickactions-csrf-token="'
                . htmlescape($csrfToken) . '"';
            echo ' data-quickactions-ticket-id="' . (int) $ticket->getID() . '"';
            echo ' data-quickactions-action="' . htmlescape($action) . '">';
            echo '<i class="' . htmlescape($icon) . '" aria-hidden="true"></i>';
            echo '<span class="spinner-border spinner-border-sm quickactions-panel__spinner"'
                . ' aria-hidden="true"></span>';
            echo '<span>' . htmlescape($label) . '</span></button>';
        }

        echo '</div></section>';
    }
}
