# App Versioning

Single source of truth for app version, displayed in UI.

## Source of Truth
- `.env`: `APP_VERSION=v2.0.2`
- `config/app.php`: `'version' => env('APP_VERSION', 'v2.0.2')`
- Updated manually each release to match git tag.

## Backend
- `HandleInertiaRequests.php` shares `appVersion` via Inertia shared props.

## Frontend Display
- **Sidebar** (DashboardLayout): replace hardcoded `v2.0` with `{appVersion}`.
- **POSLayout**: small `v2.0.2` text in navbar right area.

## Files Changed
1. `.env.example` — add `APP_VERSION=v2.0.2`
2. `config/app.php` — add `'version'` key
3. `HandleInertiaRequests.php` — add `appVersion` to shared props
4. `Sidebar.jsx` — use prop
5. `POSLayout.jsx` — display in navbar
