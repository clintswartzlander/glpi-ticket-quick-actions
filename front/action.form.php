<?php

declare(strict_types=1);

require_once dirname(__DIR__, 3) . '/inc/includes.php';

use GlpiPlugin\Quickactions\QuickActionService;

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit;
}

Session::checkCentralAccess();
Session::checkCSRF($_POST);

$ticketId = filter_var($_POST['tickets_id'] ?? null, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
$redirect = $ticketId ? Ticket::getFormURLWithID((int) $ticketId) : Ticket::getSearchURL();

try {
    (new QuickActionService())->execute((int) $ticketId, $action);
    Session::addMessageAfterRedirect(__('Ticket quick action completed.', 'quickactions'), true, INFO);
} catch (DomainException $exception) {
    Session::addMessageAfterRedirect($exception->getMessage(), true, ERROR);
} catch (Throwable $exception) {
    Toolbox::logInFile(
        'php-errors',
        sprintf(
            "[quickactions] Unexpected %s for ticket %d: %s\n%s\n",
            $exception::class,
            (int) $ticketId,
            $exception->getMessage(),
            $exception->getTraceAsString()
        )
    );
    Session::addMessageAfterRedirect(
        __('The quick action could not be completed. Check the GLPI logs.', 'quickactions'),
        true,
        ERROR
    );
}

Html::redirect($redirect);
