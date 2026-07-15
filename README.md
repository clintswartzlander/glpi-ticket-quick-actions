# Ticket Quick Actions

Ticket Quick Actions is a small GLPI 11 plugin that adds a dedicated action panel to saved Ticket pages in the standard interface.

It provides four focused actions:

- **Assign to Me** adds the signed-in user as an assigned technician without replacing groups or other technicians. A New ticket moves to Processing (Assigned) only when the active profile's lifecycle matrix permits that transition.
- **Release Assignment** removes only the signed-in user's assigned-technician relation and preserves the ticket status.
- **Pending** moves the ticket to Pending when permitted by the lifecycle matrix.
- **Resume** moves a Pending ticket to Processing (Assigned) when permitted.

The plugin uses GLPI's object APIs and native history. It creates no tables, performs no direct SQL, changes no GLPI core files, and never creates `ITILFollowup` records.

## Requirements

- GLPI 11.0.x
- PHP 8.2 or newer
- A central-interface profile with permission to view the ticket and the applicable update/assignment rights

## Installation

1. Download or build a release archive.
2. Extract it so the plugin directory is exactly `GLPI_ROOT/plugins/quickactions`.
3. In GLPI, open **Setup > Plugins**.
4. Install and enable **Ticket Quick Actions**.

No database migration is performed.

## Security model

- The panel is not rendered in the Self-Service/helpdesk interface.
- Every action is POST-only and uses a one-time GLPI CSRF token.
- The endpoint re-loads the ticket and repeats visibility, interface, assignment, update, and lifecycle checks server-side.
- Redirects are built only with `Ticket::getFormURLWithID()` or `Ticket::getSearchURL()`.
- Expected denials become generic GLPI messages. Unexpected exceptions are written to GLPI's PHP error log and are not shown to users.

## Development

```bash
composer validate --strict
composer lint
composer test
composer package
```

The current machine must use PHP 8.2+ for a supported runtime. See [MANUAL_QA.md](MANUAL_QA.md) for GLPI verification scenarios.

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
