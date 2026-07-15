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
$assert(strpos($setup, 'POST_ITIL_INFO_SECTION') !== false, 'GLPI 11 ITIL panel hook is not registered.');

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
