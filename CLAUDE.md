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
- `php artisan db:seed --class=DemoSubmissionSeeder` — 20 sample papers covering every status, with sample authors/reviewers (`*@skdiit.test` / `1234`); re-running replaces them

## Current phase: mock data first
- Lists and statistics come from `resources/js/data/mock.js` unless moved to real data. Real so far: login/registration/logout (`AuthController`; sign-up emails a random 6-digit password, see `config/registration.php`), account pages (`Admin\StaffController` for admin/editor/finance, `Admin\ReviewerController` for reviewers and their tracks, both via `Admin\Concerns\ManagesAccounts`; `Admin\TrackController` for track names and the reviewer board; `Admin\AccountController` for "My account"), Settings (`Admin\SettingsController`: submission opening date, deadline and upload limit; `Submission::window()` gives the phase upcoming/open/closed; academic titles via `AdminAcademicTitleController`, shared to every backend page as the `academicTitles` prop and read with `useAcademicTitles()`, users link them through `users.academic_title_id`; affiliations via `AdminAffiliationController` (table `affiliations`, users.affiliation keeps the code, `other` + `users.affiliation_other` for a typed name), shared as the `affiliations` prop and read with `useAffiliations()`), and paper submission (`Admin\MySubmissionController` for authors, `Admin\SubmissionController` for editors, `Admin\ReviewerAssignmentController` + `Admin\ReviewController` for peer review; every workflow action is logged to `submission_events` via `Submission::log()`; files on the private disk under `submissions/`, statuses in `App\Enums\SubmissionStatus`). App timezone is Asia/Bangkok.
- Backend specifics beyond the registrant list are still to be discussed. Ask the user instead of inventing them. A reference image is at `public/data/workflow_backend.jpg`.

## Structure
- `resources/js/Pages/Front/*` → automatically wrapped in `Layouts/FrontLayout.vue` (public site)
- `resources/js/Pages/Admin/*` → automatically wrapped in `Layouts/AdminLayout.vue` (backend, `auth` + `module:{key}` middleware)
- Roles → modules live in `config/modules.php`; one map drives both the sidebar (`auth.modules` prop, `useModules()`) and route access (`EnsureModule`). Access levels: `full` / `view` / `own`. Demo accounts per role (`config/demo.php`, `*@skdiit.test` / `1234`) are seeded and listed in the login modal during testing; remove before go-live.
- System settings: `App\Models\Setting::get()` / `put()` (defaults in `Setting::DEFAULTS`); tracks in `tracks`, reviewer↔track in `reviewer_track`.
- Impersonation ("view as this user", admin only, read only, 30 min): `AppSupportImpersonation`, `ImpersonationController`, `GuardImpersonation` middleware (blocks every non-GET request, turns logout into "back to admin"); shared as the `impersonation` prop and shown by `ImpersonationBanner`. Start/stop are logged to laravel.log until the Activity log exists.
- Accounts: `users.suspended_at` (suspended accounts cannot sign in; `SignOutSuspended` middleware), `users.password_by_admin` (signing in with an admin-set password does not confirm the email). Write actions on view-only modules use `module:{key},full`.
- Still mock data: the registrant management page (`Admin/Registrants`, `adminRegistrants` in mock.js) and Payments (`Admin/Payments`, `adminPayments` for finance/admin, `myPayments` for authors; fees in `paymentFees` are placeholders). Also mock: Publication overview (`Admin/Publication`, `publicationQueue`; the publication work itself is real on each paper page) and the article archive (`Admin/Articles`, `articleArchive`, several conference years). Conference event menus are mock too: `Admin/Presentations` (`presentationSessions`, `unscheduledPapers`) and `Admin/Schedule`, which edits the same `importantDates` / `program` the public Schedule page reads. Notifications are mock: `Admin/Notifications` + the header `NotificationBell` share `useNotifications()` (`notificationsByRole`; admin also gets the `emailLog` tab). Editor work queues are real (`Submission::QUEUES`, `scopeQueue`, `queueCounts()`; Submissions tabs `?queue=`), and `AdminDashboardController` feeds real paper numbers to the editor/admin home. Editors double as coordinators (no secretariat role): reviewer reminders are real (`ReviewerAssignmentController::remind`, logged as `review_reminded` with who sent it), and history labels/icons are shared in `resources/js/utils/submissionEvents.js`. Paper files are versioned (`submission_files`; replacing a file never deletes the old one; `Submission::addManuscript()`, `versionsFor(staff|author|reviewer)`), revisions carry the author's answers per reviewer (`revision_responses`) or a response letter, and editors keep internal notes (`submission_notes`, `SubmissionNoteController`, staff only, writer may delete within 15 min). Reviewers can save a draft (editors never see an unsent evaluation: `Review::toClient(withReviewer: true)` hides it), add a confidential comment to the editors (`comment_to_editor`, never in `reviewsForAuthor`), must declare no conflict (`conflict_declared_at`) and may say they cannot review (`unavailable_at`, queue `unavailable`, waitingOn `replaceReviewer`). The reviewer home page is real (`DashboardController::reviewerWork`). So is the author home (`authorPapers`); authors see the next step in their own terms (`Submission::nextForAuthor()`, `AuthorNextStep`), and `scopeWithReviewCounts` feeds `waitingOn()` for both lists. Dashboard (other parts, per role) and Reports are mock too (`dashboardData`, `reportData`); charts are `Components/Admin/Charts/BarList` and `TrendChart` (one series, gold, hover tooltip), and sample-data pages (registrants, payments, report tabs, activity log) export through `POST /admin/export` (`AdminExcelExportController`, staff full/view access only) with a `name`. The Activity log page is mock too (`activityLog`).
- The layout is assigned in `resources/js/app.js` and is persistent: header and sidebar stay mounted, only the content fades.
- `resources/js/Components/` shared components (Modal, Pagination, StatusBadge, CheckMark, FormField, LangSwitch, Logo); `Components/Front/`, `Components/Admin/` hold area-specific ones
- `resources/js/composables/` — `useRegistrantTable`, `useTheme`, `useAuthModal`, `useModules`, `useAcademicTitles`
- Every admin section has a page now; for a new module without one, re-enable the commented `Admin/Placeholder` route in `routes/web.php`

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
