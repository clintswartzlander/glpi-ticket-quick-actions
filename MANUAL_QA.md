# Manual QA

Run these checks against a disposable GLPI 11.0.x environment using PHP 8.2 or newer. Install the repository as `plugins/quickactions`, then install and enable the plugin from GLPI.

For actor-preservation scenarios, begin with a ticket that has two assigned technicians and one assigned group. Keep the History tab visible in a second browser tab.

## Installation and rendering

1. Deploy version 1.0.4, clear the GLPI cache, and confirm the plugin reports version 1.0.4, GPL-3.0-or-later, with no database migration.
2. Open a new/unsaved Ticket form. Confirm the Quick Actions panel is absent.
3. Open a saved Ticket in the central interface with a user who can view it. Confirm the panel is present and its buttons reflect the ticket state and rights.
4. Inspect the Ticket form DOM. Confirm the panel renders once, contains no nested `form`, and each action is a `button[type="button"]`.
5. Switch to a Self-Service/helpdesk profile. Confirm the panel is absent.
6. Test GLPI's light and dark themes at desktop and mobile widths. Confirm text, borders, focus states, wrapping, and button labels remain usable.

## Submission architecture

1. Hard-refresh a saved Ticket page, open browser developer tools, and preserve the Network log.
2. Confirm the page contains native GLPI hidden inputs named `_glpi_csrf_token` within the Ticket or another GLPI form.
3. Click **Assign to Me** once. Confirm the button disables immediately and shows its busy state.
4. Confirm exactly one normal document POST is sent to `/plugins/quickactions/front/action.form.php` with `_glpi_csrf_token`, `tickets_id`, and `action` form fields.
5. Confirm the POST passes GLPI's automatic kernel CSRF listener and the legacy `action.form.php` controller executes without attempting a second CSRF validation.
6. Confirm the response does not return HTTP 403, redirects back to the canonical Ticket URL, and adds the current technician.
7. Confirm the existing assigned group remains assigned and `access-errors.log` contains no new CSRF failure.
8. Exercise Pending, Resume, and Release Assignment. Confirm each executes and uses the same standalone POST mechanism.
9. Confirm native History records the changes and no requester-visible followup is created.
10. Rapidly click or double-click every action. Confirm only one request is initiated per rendered button.
11. Temporarily remove or blank all native `_glpi_csrf_token` inputs, click an action, and confirm no POST occurs, the button is restored, and a concise console error appears.
12. Send crafted POSTs with a missing or invalid CSRF token. Confirm GLPI rejects each before the plugin controller executes.
13. Reload or dynamically refresh the Ticket panel multiple times where possible. Confirm one click still produces one POST, demonstrating that the JavaScript handler registered only once.
14. Confirm the browser console has no unexpected errors and the GLPI/PHP logs have no new warnings.

## Assign to Me

1. As a technician with OWN, open an unassigned New ticket. Allow New -> Processing (Assigned) in the profile lifecycle matrix. Click **Assign to Me**.
2. Confirm the current user is added as an assigned technician and the ticket becomes Processing (Assigned).
3. Confirm an existing assigned group remains assigned after the action.
4. Repeat with New -> Processing (Assigned) denied. Confirm assignment is added but status remains New.
5. On a ticket already assigned to another technician and a group, test with STEAL. Confirm the current user is added and all existing actors remain.
6. Repeat without STEAL. Confirm the action is hidden and a crafted POST is rejected.
7. Submit the same logical action again after reloading. Confirm no duplicate relation is created.
8. Confirm History contains native assignment/status entries and no requester-visible followup was created.

## Release Assignment

1. On a ticket assigned to the current user, another technician, and a group, click **Release Assignment**.
2. Confirm only the current user's assigned-technician relation is removed.
3. Confirm the other technician, assigned group, and status are unchanged.
4. Repeat when the current user is the only assignee. Confirm the status is still exactly unchanged after the request.
5. Confirm the native actor removal is visible in History and no followup was created.
6. Craft a release POST for a ticket where the current user is not assigned. Confirm no unrelated actor is removed.

## Pending and Resume

1. Permit the current status -> Pending transition and click **Pending**. Confirm only status changes and all assignments remain.
2. Deny that transition. Confirm the button is hidden and a crafted POST is rejected.
3. On Pending, permit Pending -> Processing (Assigned), click **Resume**, and confirm assignments remain.
4. Deny Pending -> Processing (Assigned). Confirm the button is hidden and a crafted POST is rejected.
5. Confirm status changes appear in native History with no followup records.

## Security and routing

1. Send GET to `/plugins/quickactions/front/action.form.php`. Confirm HTTP 405 and no mutation.
2. Send POST without `_glpi_csrf_token`, then with an invalid token. Confirm GLPI rejects both.
3. Send POST as a Self-Service user. Confirm central access is rejected.
4. Send POST for a ticket outside the user's visible entities or permissions. Confirm rejection.
5. Send an unknown action, malformed ticket ID, and nonexistent ticket ID. Confirm no mutation and no stack trace.
6. Confirm successful and expected-error redirects go only to the canonical GLPI ticket form or Ticket list.
7. Force an unexpected runtime error in a disposable environment. Confirm the user sees only the generic message and GLPI's PHP error log contains the exception details.

## Regression

1. Create and edit tickets normally with the plugin enabled and disabled.
2. Confirm Ticket actor notifications and History still behave normally.
3. Confirm no `glpi_plugin_quickactions_*` table exists and no GLPI core file changed.
