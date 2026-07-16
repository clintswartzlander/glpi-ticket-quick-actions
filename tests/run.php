<?php

declare(strict_types=1);

// Minimal constants let the pure lifecycle policy run without a GLPI checkout.
if (!class_exists('Ticket')) {
    final class Ticket
    {
        public const INCOMING = 1;
        public const ASSIGNED = 2;
        public const WAITING = 4;
        public const SOLVED = 5;
        public const CLOSED = 6;
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
$allowAll = static fn (int $from, int $to): bool => $from !== $to;

$assert($policy->shouldMoveNewToAssigned(1, $allow), 'Allowed New-to-Assigned transition was rejected.');
$assert(!$policy->shouldMoveNewToAssigned(1, $deny), 'Denied New-to-Assigned transition was accepted.');
$assert(!$policy->shouldMoveNewToAssigned(4, $allow), 'Non-New assignment changed status.');
$assert($policy->canMove(1, 2, $allow), 'Allowed lifecycle move was rejected.');
$assert(!$policy->canMove(2, 2, $allow), 'No-op lifecycle move was accepted.');
$assert($policy->canPend(Ticket::INCOMING, $allowAll), 'Pending is hidden for an eligible active ticket.');
$assert(!$policy->canPend(Ticket::WAITING, $allowAll), 'Pending is visible for a Pending ticket.');
$assert(!$policy->canPend(Ticket::SOLVED, $allowAll), 'Pending is visible for a Solved ticket.');
$assert(!$policy->canPend(Ticket::CLOSED, $allowAll), 'Pending is visible for a Closed ticket.');
$assert($policy->canSolve(Ticket::INCOMING, $allowAll), 'Solve is hidden for an eligible active ticket.');
$assert($policy->canSolve(Ticket::WAITING, $allowAll), 'Solve is hidden for an eligible Pending ticket.');
$assert(!$policy->canSolve(Ticket::SOLVED, $allowAll), 'Solve is visible for a Solved ticket.');
$assert(!$policy->canSolve(Ticket::CLOSED, $allowAll), 'Solve is visible for a Closed ticket.');
$assert($policy->canClose(Ticket::SOLVED, $allowAll), 'Close is hidden for a Solved ticket.');
$assert(!$policy->canClose(Ticket::INCOMING, $allowAll), 'Close is visible for an active ticket.');
$assert(!$policy->canClose(Ticket::CLOSED, $allowAll), 'Close is visible for a Closed ticket.');
$assert($policy->canReopen(Ticket::SOLVED, Ticket::ASSIGNED, $allowAll), 'Reopen is hidden for a Solved ticket.');
$assert($policy->canReopen(Ticket::CLOSED, Ticket::INCOMING, $allowAll), 'Reopen is hidden for a Closed ticket.');
$assert(!$policy->canReopen(Ticket::INCOMING, Ticket::ASSIGNED, $allowAll), 'Reopen is visible for an active ticket.');
$assert(!$policy->canReopen(Ticket::CLOSED, Ticket::INCOMING, $deny), 'Denied Reopen transition was accepted.');
$assert($policy->resumeTarget(true) === Ticket::ASSIGNED, 'Assigned Reopen/Resume does not target Processing.');
$assert($policy->resumeTarget(false) === Ticket::INCOMING, 'Unassigned Reopen/Resume does not target New.');
$assert(
    Action::all() === [
        Action::ASSIGN_TO_ME,
        Action::RELEASE,
        Action::PENDING,
        Action::RESUME,
        Action::SOLVE,
        Action::CLOSE,
        Action::REOPEN,
    ],
    'Quick-action ordering is incorrect.'
);
$assert(Action::isValid(Action::RELEASE), 'Known action was rejected.');
$assert(Action::isValid(Action::SOLVE), 'Solve action was rejected.');
$assert(Action::isValid(Action::CLOSE), 'Close action was rejected.');
$assert(Action::isValid(Action::REOPEN), 'Reopen action was rejected.');
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
$assert(strpos($controller, 'Session::checkCSRF(') === false, 'Controller redundantly validates GLPI CSRF tokens.');
$assert(strpos($controller, 'Session::checkCentralAccess()') !== false, 'Controller does not enforce central access.');
$assert(strpos($controller, 'GLPI 11 validates CSRF automatically') !== false, 'Controller lacks the GLPI 11 CSRF listener explanation.');
$assert(strpos($controller, 'Ticket::getFormURLWithID') !== false, 'Controller lacks canonical ticket redirect.');
$assert(strpos($controller, 'Ticket::getSearchURL') !== false, 'Controller lacks canonical list redirect.');
$assert(strpos($service, 'canAssignToMe()') !== false, 'Assignment does not use canAssignToMe().');
$assert(strpos($service, '\\Ticket::OWN') !== false, 'OWN semantics are absent.');
$assert(strpos($service, '\\Ticket::STEAL') !== false, 'STEAL semantics are absent.');
$assert(strpos($service, "'type'       => \\CommonITILActor::ASSIGN") !== false, 'Actor relation is not assignment-scoped.');
$assert(strpos($service, 'new StatusPreservingTicketUser()') !== false, 'Release does not use status-neutral relation deletion.');
$assert(strpos($service, '$ticket->canSolve()') !== false, 'Solve does not use GLPI native solution permission checks.');
$assert(strpos($service, '$ticket->canReopen()') !== false, 'Closed-ticket Reopen does not use GLPI native reopen rights.');
$assert(strpos($service, '$ticket->countUsers(\\CommonITILActor::ASSIGN)') !== false, 'Assignment context ignores assigned technicians.');
$assert(strpos($service, '$ticket->countGroups(\\CommonITILActor::ASSIGN)') !== false, 'Assignment context ignores assigned groups.');
$assert(strpos($service, '\\ITILSolution::countFor') !== false, 'Solve does not check for an existing native solution.');
$assert(strpos($service, 'normal Add Solution workflow') !== false, 'Missing-solution rejection does not direct technicians to Add Solution.');
$assert(strpos($service, 'You do not have permission to solve this ticket.') !== false, 'Solve permission failure is not explicit.');
$assert(strpos($service, 'You do not have permission to close this ticket.') !== false, 'Close permission failure is not explicit.');
$assert(strpos($service, 'You do not have permission to reopen this ticket.') !== false, 'Reopen permission failure is not explicit.');
$assert(strpos($service, 'assertTransitionAllowed') !== false, 'Lifecycle actions do not enforce configured transitions.');
$serviceActionPositions = array_map(
    static fn (string $action): int|false => strpos($service, '$actions[] = Action::' . $action . ';'),
    ['ASSIGN_TO_ME', 'RELEASE', 'PENDING', 'RESUME', 'SOLVE', 'CLOSE', 'REOPEN']
);
$serviceActionsOrdered = true;
$previousActionPosition = -1;
foreach ($serviceActionPositions as $actionPosition) {
    if ($actionPosition === false || $actionPosition <= $previousActionPosition) {
        $serviceActionsOrdered = false;
        break;
    }
    $previousActionPosition = $actionPosition;
}
$assert($serviceActionsOrdered, 'Available actions are not assembled in the required logical order.');
$assert(strpos($statusPreservingRelation, '\\CommonDBRelation::post_deleteFromDB()') !== false, 'Release bypasses native relation history.');
$assert(strpos($statusPreservingRelation, "'status'") === false, 'Status-neutral relation deletion writes ticket status.');
$assert(strpos($renderer, "getCurrentInterface() === 'central'") === false, 'Renderer should delegate interface checks to the service.');
$assert(stripos($renderer, '<form') === false, 'PanelRenderer outputs a nested form.');
$assert(strpos($renderer, '<button type="button"') !== false, 'Quick actions are not non-submit buttons.');
$assert(strpos($renderer, 'getNewCSRFToken') === false, 'Renderer generates a CSRF token.');
$assert(strpos($renderer, 'data-quickactions-csrf-token') === false, 'Renderer exposes a CSRF data attribute.');
$assert(strpos($renderer, 'data-quickactions-ticket-id') !== false, 'Renderer does not expose the ticket ID.');
$assert(strpos($renderer, 'data-quickactions-action') !== false, 'Renderer does not expose the action.');
$assert(strpos($renderer, "Action::SOLVE => [__('Solve', 'quickactions'), 'ti ti-circle-check']") !== false, 'Solve label or icon is incorrect.');
$assert(strpos($renderer, "Action::CLOSE => [__('Close', 'quickactions'), 'ti ti-lock']") !== false, 'Close label or icon is incorrect.');
$assert(strpos($renderer, "Action::REOPEN => [__('Reopen', 'quickactions'), 'ti ti-refresh']") !== false, 'Reopen label or icon is incorrect.');
$assert(substr_count($renderer, 'data-quickactions-confirmation') === 1, 'Renderer confirmation data attribute is missing or duplicated.');
$assert(strpos($renderer, 'Mark this ticket as solved?') !== false, 'Solve confirmation is missing.');
$assert(strpos($renderer, 'Close this solved ticket?') !== false, 'Close confirmation is missing.');
$assert(strpos($renderer, 'Reopen this ticket?') !== false, 'Reopen confirmation is missing.');
$assert(strpos($setup, 'POST_ITIL_INFO_SECTION') !== false, 'GLPI 11 ITIL panel hook is not registered.');
$assert(strpos($setup, "Hooks::ADD_CSS]['quickactions'] = 'css/quickactions.css'") !== false, 'CSS hook path is incorrect.');
$assert(is_file($root . '/public/css/quickactions.css'), 'Registered CSS asset is missing from public/css.');
$assert(strpos($setup, "Hooks::ADD_JAVASCRIPT]['quickactions'] = 'js/quickactions.js'") !== false, 'JavaScript hook path is incorrect.');
$assert(is_file($root . '/public/js/quickactions.js'), 'Registered JavaScript asset is missing from public/js.');
$assert(strpos($javascript, "document.createElement('form')") !== false, 'JavaScript does not create a standalone form.');
$assert(strpos($javascript, "form.method = 'post'") !== false, 'Standalone form is not POST.');
$assert(strpos($javascript, 'document.body.appendChild(form)') !== false, 'Standalone form is not attached directly to body.');
$assert(strpos($javascript, "button.closest('#itil-object-container')") !== false, 'JavaScript does not scope the primary CSRF lookup.');
$assert(strpos($javascript, "container.querySelector('input[name=\"_glpi_csrf_token\"]')") !== false, 'JavaScript does not query the Ticket container for GLPI\'s CSRF input.');
$assert(strpos($javascript, "document.querySelector(") !== false, 'JavaScript lacks the fallback CSRF lookup.');
$assert(strpos($javascript, "'form input[name=\"_glpi_csrf_token\"]'") !== false, 'JavaScript does not query a fallback form for GLPI\'s CSRF input.');
$assert(strpos($javascript, "!csrfInput || csrfInput.value.trim() === ''") !== false, 'JavaScript does not reject a missing or blank CSRF token.');
$missingTokenPosition = strpos($javascript, "if (!csrfInput || csrfInput.value.trim() === '')");
$missingTokenReturnPosition = strpos($javascript, 'return;', $missingTokenPosition ?: 0);
$formPosition = strpos($javascript, "document.createElement('form')");
$assert(
    $missingTokenPosition !== false
    && $missingTokenReturnPosition !== false
    && $formPosition !== false
    && $missingTokenPosition < $missingTokenReturnPosition
    && $missingTokenReturnPosition < $formPosition,
    'JavaScript does not stop before form creation when the CSRF token is missing or blank.'
);
$assert(strpos($javascript, 'button.disabled = false') !== false, 'Missing-token handling does not re-enable the button.');
$assert(strpos($javascript, "button.removeAttribute('aria-busy')") !== false, 'Missing-token handling does not clear aria-busy.');
$assert(strpos($javascript, 'delete button.dataset.quickactionsBusy') !== false, 'Missing-token handling does not clear the busy flag.');
$assert(strpos($javascript, "console.error('Quick Actions: GLPI CSRF token not found.')") !== false, 'Missing-token handling does not log a concise console error.');
$assert(strpos($javascript, '_glpi_csrf_token: csrfInput.value') !== false, 'Standalone form does not use GLPI\'s existing CSRF token.');
$assert(strpos($javascript, 'tickets_id') !== false, 'Standalone form omits the ticket ID.');
$assert(strpos($javascript, 'form.submit()') !== false, 'Standalone form is not normally submitted.');
$assert(strpos($javascript, 'window[handlerFlag]') !== false, 'JavaScript lacks duplicate-handler protection.');
$assert(strpos($javascript, 'button.disabled = true') !== false, 'JavaScript does not prevent double-click execution.');
$assert(strpos($javascript, 'window.confirm(confirmation)') !== false, 'Destructive lifecycle actions do not require confirmation.');
$confirmationPosition = strpos($javascript, 'if (confirmation && !window.confirm(confirmation))');
$confirmationReturnPosition = strpos($javascript, 'return;', $confirmationPosition ?: 0);
$busyPosition = strpos($javascript, "button.dataset.quickactionsBusy = 'true'");
$assert(
    $confirmationPosition !== false
    && $confirmationReturnPosition !== false
    && $busyPosition !== false
    && $confirmationPosition < $confirmationReturnPosition
    && $confirmationReturnPosition < $busyPosition,
    'Cancelled confirmation does not stop before submission state begins.'
);
$assert(strpos($setup, "PLUGIN_QUICKACTIONS_VERSION', '1.1.0'") !== false, 'Plugin version is not 1.1.0.');

$methodSection = static function (string $source, string $method, string $nextMethod): string {
    $start = strpos($source, 'private function ' . $method . '(');
    $end = strpos($source, 'private function ' . $nextMethod . '(', $start ?: 0);
    if ($start === false || $end === false || $end <= $start) {
        return '';
    }

    return substr($source, $start, $end - $start);
};
$solveMethod = $methodSection($service, 'solve', 'close');
$closeMethod = $methodSection($service, 'close', 'reopen');
$reopenMethod = $methodSection($service, 'reopen', 'assertTransitionAllowed');
$lifecycleMethods = $solveMethod . $closeMethod . $reopenMethod;
$assert($solveMethod !== '' && $closeMethod !== '' && $reopenMethod !== '', 'Lifecycle action methods are missing.');
$assert(substr_count($lifecycleMethods, 'updateTicketStatus($ticket') === 3, 'Lifecycle actions do not use native Ticket status updates.');
foreach (['Ticket_User', 'Group_Ticket', 'StatusPreservingTicketUser'] as $actorMutation) {
    $assert(strpos($lifecycleMethods, $actorMutation) === false, sprintf('Lifecycle action mutates assignments through %s.', $actorMutation));
}
$assert(strpos($reopenMethod, 'ITILSolution') === false, 'Reopen modifies or removes the existing solution.');
$assert(strpos($reopenMethod, 'delete(') === false, 'Reopen deletes existing ticket data.');

$csrfEntryPoints = implode("\n", [$setup, file_get_contents($root . '/hook.php'), $controller]);
$csrfBypassMarkers = [
    'skip_csrf',
    'disable_csrf',
    'csrf_compliant',
    'preserve_token',
    'GLPI_SKIP_CSRF_CHECK',
    'CheckCsrfListener',
];
foreach ($csrfBypassMarkers as $marker) {
    $assert(stripos($csrfEntryPoints, $marker) === false, sprintf('GLPI CSRF bypass marker found: %s', $marker));
}

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
