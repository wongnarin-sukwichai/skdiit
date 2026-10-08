# SKDIIT 2027 — Conference Registration & Paper Submission

Web app for registering to attend the SKDIIT 2027 academic conference and submitting academic papers.
The user communicates in Thai.

## Stack
- Laravel 12 (PHP 8.2, XAMPP, MySQL `skdiit_db`) + Inertia v3 (`inertiajs/inertia-laravel` ^3, `@inertiajs/vue3` ^3)
- Vue 3 `<script setup>`, Vite 6, Tailwind CSS v4 (config lives in `resources/css/app.css`, no tailwind.config.js)
- Font Awesome via `@fortawesome/vue-fontawesome`, registered globally as `<Fa>`; icons must be added to `resources/js/plugins/fontawesome.js`
- Font: Anuphan (Google Fonts, loaded in `resources/views/app.blade.php`)

## Commands
- `php artisan serve` + `npm run dev` — local dev
- `npm run build` — production build; run it after changes to confirm the build passes
- `php artisan migrate --seed` — seeds the admin `wongnarin.s@msu.ac.th` / `w123`

## Current phase: mock data first
- Lists and statistics come from `resources/js/data/mock.js` unless moved to real data. Real so far: login/registration/logout (`AuthController`) Settings (`Admin\SettingsController`: users & roles, tracks, reviewer tracks, submission deadline and upload limit), and paper submission (`Admin\MySubmissionController` for authors, `Admin\SubmissionController` for editors, `Admin\ReviewerAssignmentController` + `Admin\ReviewController` for peer review; every workflow action is logged to `submission_events` via `Submission::log()`; files on the private disk under `submissions/`, statuses in `App\Enums\SubmissionStatus`). App timezone is Asia/Bangkok.
- Backend specifics beyond the registrant list are still to be discussed. Ask the user instead of inventing them. A reference image is at `public/data/workflow_backend.jpg`.

## Structure
- `resources/js/Pages/Front/*` → automatically wrapped in `Layouts/FrontLayout.vue` (public site)
- `resources/js/Pages/Admin/*` → automatically wrapped in `Layouts/AdminLayout.vue` (backend, `auth` + `module:{key}` middleware)
- Roles → modules live in `config/modules.php`; one map drives both the sidebar (`auth.modules` prop, `useModules()`) and route access (`EnsureModule`). Access levels: `full` / `view` / `own`. Demo accounts per role (`config/demo.php`, `*@skdiit.test` / `1234`) are seeded and listed in the login modal during testing; remove before go-live.
- System settings: `App\Models\Setting::get()` / `put()` (defaults in `Setting::DEFAULTS`); tracks in `tracks`, reviewer↔track in `reviewer_track`.
- The layout is assigned in `resources/js/app.js` and is persistent: header and sidebar stay mounted, only the content fades.
- `resources/js/Components/` shared components (Modal, Pagination, StatusBadge, CheckMark, FormField, LangSwitch, Logo); `Components/Front/`, `Components/Admin/` hold area-specific ones
- `resources/js/composables/` — `useRegistrantTable`, `useTheme`, `useAuthModal`, `useModules`
- Admin routes not yet built go through `Admin/Placeholder` (`/admin/{section}` in `routes/web.php`)

## Rules
- **Transitions**: content that swaps (pages, table pages, modals, menus) uses `<Transition name="fade">`. The `fade` and `modal` classes live in `app.css`.
- **i18n**: English is primary, Thai is secondary. Never hard-code UI text. Add the key to both `resources/js/i18n/en.js` and `th.js`, then use `t('key')` (`useI18n()`) or `$t()`.
  - Bilingual data uses `{ en, th }` and is shown with `tr()`.
  - Dates use `formatDate()` (Thai output uses the Buddhist calendar).
  - Server validation errors are translation keys such as `validation.required` (see `AuthController::MESSAGES`).
- **Theme**:
  - The public site is always dark: black with gold (`gold-*` and `ink-*` colours in `@theme`). Design reference: `public/img/theme/fontend.png`.
  - The backend has light mode (default) and dark mode, toggled with the `.dark` class on `<html>`. Design references: `public/img/theme/light.png` and `dark.png`.
  - Backend styles must cover both modes, e.g. `bg-white dark:bg-ink-900`.
  - Areas that are always dark (the public site, the admin headbar, modals) carry a `dark` class so shared components switch to their dark styles.
- **Cursor**: a global rule in `app.css` already gives every clickable element a pointer cursor. No need to add `cursor-pointer` to each element.
- **Width**: public-site content uses `mx-auto max-w-[1600px] px-4 sm:px-6 lg:px-8`.
- **Background**: `public/img/bg/bg.png`, set in `FrontLayout.vue`. Mobile uses a dimmed band at the top; desktop uses the full page.
- **Tailwind v4**: use canonical class names (`bg-linear-to-r`, `h-130`, `bg-top-right`) rather than the v3 or arbitrary forms.
