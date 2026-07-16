# Changelog

All notable changes to Ticket Quick Actions are documented here. The format follows Keep a Changelog and the project uses Semantic Versioning.

## [Unreleased]

## [1.0.3] - 2026-07-16

### Fixed

- Reused a native GLPI Ticket-page CSRF token at click time instead of generating tokens from the `POST_ITIL_INFO_SECTION` hook.
- Cancelled submission and restored the quick-action control when no non-empty native token is available.
- Added contract coverage for native token lookup, missing-token handling, and the absence of renderer-generated CSRF data.

## [1.0.2] - 2026-07-16

### Fixed

- Reused GLPI's shared current-page CSRF token for every rendered quick action so standalone POSTs pass session validation.
- Added contract coverage preventing standalone token generation and requiring one token generation before the action loop.

## [1.0.1] - 2026-07-15

### Fixed

- Replaced invalid nested quick-action forms with `type="button"` controls and a scoped JavaScript normal-POST bridge.
- Prevented duplicate handler registration and double-click action execution.
- Verified that GLPI asset hook paths are relative to the plugin's `public/` directory.

## [1.0.0] - 2026-07-15

### Added

- Dedicated Quick Actions panel for saved GLPI 11 Ticket pages.
- Assign to Me, Release Assignment, Pending, and Resume actions.
- POST-only endpoint with GLPI CSRF, central-interface, visibility, permission, and lifecycle enforcement.
- Native Ticket and Ticket_User mutations with no custom tables or SQL.
- Responsive light/dark-compatible scoped styling.
- Automated lint, contract tests, CI, release packaging, and manual QA documentation.

[Unreleased]: https://github.com/clintswartzlander/glpi-ticket-quick-actions/compare/v1.0.3...HEAD
[1.0.3]: https://github.com/clintswartzlander/glpi-ticket-quick-actions/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/clintswartzlander/glpi-ticket-quick-actions/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/clintswartzlander/glpi-ticket-quick-actions/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/clintswartzlander/glpi-ticket-quick-actions/releases/tag/v1.0.0
