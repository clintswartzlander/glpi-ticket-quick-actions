<?php

declare(strict_types=1);

// Minimal constants let the pure lifecycle policy run without a GLPI checkout.
if (!class_exists('Ticket')) {
    final class Ticket
    {
        public const INCOMING = 1;
        public const ASSIGNED = 2;
    }
}

require_once dirname(__DIR__) . '/src/Action.php';
require_once dirname(__DIR__) . '/src/ActionPolicy.php';

use GlpiPlugin\Quickactions\Action;
use GlpiPlugin\Quickactions\ActionPolicy;

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    ++$assertions;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$policy = new ActionPolicy();
$allow = static fn (int $from, int $to): bool => $from === 1 && $to === 2;
$deny = static fn (int $from, int $to): bool => false;

$assert($policy->shouldMoveNewToAssigned(1, $allow), 'Allowed New-to-Assigned transition was rejected.');
$assert(!$policy->shouldMoveNewToAssigned(1, $deny), 'Denied New-to-Assigned transition was accepted.');
$assert(!$policy->shouldMoveNewToAssigned(4, $allow), 'Non-New assignment changed status.');
$assert($policy->canMove(1, 2, $allow), 'Allowed lifecycle move was rejected.');
$assert(!$policy->canMove(2, 2, $allow), 'No-op lifecycle move was accepted.');
$assert(Action::isValid(Action::RELEASE), 'Known action was rejected.');
$assert(!Action::isValid('delete_ticket'), 'Unknown action was accepted.');

$root = dirname(__DIR__);
$controller = file_get_contents($root . '/front/action.form.php');
$service = file_get_contents($root . '/src/QuickActionService.php');
$renderer = file_get_contents($root . '/src/PanelRenderer.php');
$statusPreservingRelation = file_get_contents($root . '/src/StatusPreservingTicketUser.php');
$javascript = file_get_contents($root . '/public/js/quickactions.js');
$setup = file_get_contents($root . '/setup.php');

$assert(strpos($controller, "\$_SERVER['REQUEST_METHOD']") !== false, 'Controller is not POST-only.');
$assert(strpos($controller, "!== 'POST'") !== false, 'Controller does not reject non-POST requests.');
$assert(strpos($controller, 'Session::checkCSRF($_POST)') !== false, 'Controller does not use GLPI CSRF validation.');
$assert(strpos($controller, 'Ticket::getFormURLWithID') !== false, 'Controller lacks canonical ticket redirect.');
$assert(strpos($controller, 'Ticket::getSearchURL') !== false, 'Controller lacks canonical list redirect.');
$assert(strpos($service, 'canAssignToMe()') !== false, 'Assignment does not use canAssignToMe().');
$assert(strpos($service, '\\Ticket::OWN') !== false, 'OWN semantics are absent.');
$assert(strpos($service, '\\Ticket::STEAL') !== false, 'STEAL semantics are absent.');
$assert(strpos($service, "'type'       => \\CommonITILActor::ASSIGN") !== false, 'Actor relation is not assignment-scoped.');
$assert(strpos($service, 'new StatusPreservingTicketUser()') !== false, 'Release does not use status-neutral relation deletion.');
$assert(strpos($statusPreservingRelation, '\\CommonDBRelation::post_deleteFromDB()') !== false, 'Release bypasses native relation history.');
$assert(strpos($statusPreservingRelation, "'status'") === false, 'Status-neutral relation deletion writes ticket status.');
$assert(strpos($renderer, "getCurrentInterface() === 'central'") === false, 'Renderer should delegate interface checks to the service.');
$assert(stripos($renderer, '<form') === false, 'PanelRenderer outputs a nested form.');
$assert(strpos($renderer, '<button type="button"') !== false, 'Quick actions are not non-submit buttons.');
$assert(strpos($renderer, 'data-quickactions-csrf-token') !== false, 'Renderer does not expose a server-generated CSRF token.');
$assert(strpos($renderer, 'data-quickactions-ticket-id') !== false, 'Renderer does not expose the ticket ID.');
$assert(strpos($renderer, 'data-quickactions-action') !== false, 'Renderer does not expose the action.');
$assert(strpos($setup, 'POST_ITIL_INFO_SECTION') !== false, 'GLPI 11 ITIL panel hook is not registered.');
$assert(strpos($setup, "Hooks::ADD_CSS]['quickactions'] = 'css/quickactions.css'") !== false, 'CSS hook path is incorrect.');
$assert(is_file($root . '/public/css/quickactions.css'), 'Registered CSS asset is missing from public/css.');
$assert(strpos($setup, "Hooks::ADD_JAVASCRIPT]['quickactions'] = 'js/quickactions.js'") !== false, 'JavaScript hook path is incorrect.');
$assert(is_file($root . '/public/js/quickactions.js'), 'Registered JavaScript asset is missing from public/js.');
$assert(strpos($javascript, "document.createElement('form')") !== false, 'JavaScript does not create a standalone form.');
$assert(strpos($javascript, "form.method = 'post'") !== false, 'Standalone form is not POST.');
$assert(strpos($javascript, 'document.body.appendChild(form)') !== false, 'Standalone form is not attached directly to body.');
$assert(strpos($javascript, '_glpi_csrf_token') !== false, 'Standalone form omits the CSRF token.');
$assert(strpos($javascript, 'tickets_id') !== false, 'Standalone form omits the ticket ID.');
$assert(strpos($javascript, 'form.submit()') !== false, 'Standalone form is not normally submitted.');
$assert(strpos($javascript, 'window[handlerFlag]') !== false, 'JavaScript lacks duplicate-handler protection.');
$assert(strpos($javascript, 'button.disabled = true') !== false, 'JavaScript does not prevent double-click execution.');
$assert(strpos($setup, "PLUGIN_QUICKACTIONS_VERSION', '1.0.1'") !== false, 'Plugin version is not 1.0.1.');

$runtimeFiles = [
    $root . '/setup.php',
    $root . '/hook.php',
    $root . '/src/QuickActionService.php',
    $root . '/src/PanelRenderer.php',
    $root . '/src/StatusPreservingTicketUser.php',
    $root . '/front/action.form.php',
];
$runtime = implode("\n", array_map('file_get_contents', $runtimeFiles));
$forbiddenTokens = ['ITIL' . 'Followup', '$' . 'DB', 'SELECT ', 'INSERT ', 'UPDATE glpi_', 'DELETE FROM'];
foreach ($forbiddenTokens as $token) {
    $assert(stripos($runtime, $token) === false, sprintf('Forbidden runtime token found: %s', $token));
}

echo sprintf("Passed %d assertions.%s", $assertions, PHP_EOL);
