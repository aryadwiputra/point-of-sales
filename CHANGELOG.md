# Changelog

All notable application releases are listed here. Git tags using the same
`vMAJOR.MINOR.PATCH` version are the authoritative release identifiers.

## [v2.10.5] - 2026-09-12

### Fixed

- Fixed cashier shift closing to persist only columns present in the shift schema.
- Added a full demo seeder that runs the required seeders in dependency order.
- Ensured demo products are linked to the primary warehouse before stock transfers.

### Improved

- Improved transaction customer selection responsiveness and desktop overflow handling.
- Reorganized order details and cash payment controls for faster checkout.
- Added dynamic quick cash amounts, exact-payment action, and clearer change feedback.

## [v2.10.4] - 2026-09-12

### Fixed

- Reused the seeded primary warehouse during first-install setup instead of attempting to create a duplicate `PUSAT` warehouse.
- Fixed setup wizard submission when transforming form data before posting.
- Added setup warehouse coverage for reuse, rename, duplicate validation, and versioned setup state.

## [v2.10.3] - 2026-09-12

### Fixed

- Stabilized the first-install setup wizard, localization, root redirect, and migration rollback behavior.

## [v2.10.2] - 2026-09-12

### Fixed

- Fixed guided-tour behavior and replay handling.

## [v2.10.1] - 2026-09-12

### Fixed

- Fixed walk-in checkout behavior and stabilized the related documentation.

## [v2.10.0] - 2026-09-12

### Added

- Added automatic receipt printing and ESC/POS WebUSB support.

## [v2.9.0] - 2026-09-12

### Added

- Added cashier shift cash movements, X/Z reports, and order types.

## [v2.8.0] - 2026-09-12

### Added

- Added offline transaction synchronization and dynamic QRIS support.

## [v2.7.0] - 2026-09-12

### Added

- Added tour replay and the setup checklist.

## [v2.6.0] - 2026-09-12

### Added

- Added guided tours for onboarding.

## [v2.5.0] - 2026-09-12

### Added

- Added the first-install setup wizard and unified local development command.

## [v2.4.0] - 2026-09-12

### Added

- Added dine-in QR menu and customer self-order workflow.
