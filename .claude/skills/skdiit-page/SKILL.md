---
name: skdiit-page
description: Add or build a new page or screen in the SKDIIT project, on the public site (Front) or the backend (Admin). Use it when the user asks to add a page, add a menu item, or turn an admin placeholder section into a real page.
---

# Adding a new SKDIIT page

Read `CLAUDE.md` for the project rules first. Then follow these steps in order.

## 1. Route — `routes/web.php`
- Public site: `Route::inertia('/path', 'Front/Name')->name('name');`
- Backend: put it inside the `Route::prefix('admin')` group as `Route::inertia('/path', 'Admin/Name')->name('name');`.
  - If the section is currently served by `Admin/Placeholder`, remove its name from the `whereIn('section', [...])` list.

## 2. Page — `resources/js/Pages/{Front|Admin}/Name.vue`
- No layout declaration is needed: `app.js` assigns one from the folder name.
- Start with `<Head :title="t('...')" />`.
- **Front** pages:
  - Use `Components/Front/PageHeader.vue` (props `icon`, `title`, `lead`).
  - Wrap content in `<section class="mx-auto max-w-[1600px] px-4 pb-12 sm:px-6 lg:px-8">`.
  - Cards look like `rounded-2xl border border-gold-400/20 bg-ink-900/80 p-6`.
- **Admin** pages:
  - Use `Components/Admin/Card.vue` and `Components/Admin/PageHeader.vue` (props `icon`, `title`, `subtitle`; the breadcrumb is built in).
  - Every colour needs a light/dark pair, e.g. `text-neutral-500 dark:text-neutral-400`.
- Content that changes within the page goes inside `<Transition name="fade" mode="out-in">` with a `:key` that changes.

## 3. Menu
- Front: add `{ href, key }` to `navItems` in `Layouts/FrontLayout.vue`.
- Admin: add `{ href, key, icon }` to `menu` in `Layouts/AdminLayout.vue`. For a former placeholder section, delete its entry from `icons` in `Pages/Admin/Placeholder.vue`.

## 4. Text — `resources/js/i18n/en.js` and `th.js`
- Add keys to **both files** with the same structure. Menu labels go under `nav.*` (front) or `admin.menu.*` (admin).

## 5. Icons — `resources/js/plugins/fontawesome.js`
- Import any new icon (e.g. `faXxx`) and add it to `library.add(...)`. Otherwise `<Fa icon="xxx">` renders nothing.

## 6. Data
- During the mock phase, put data in `resources/js/data/mock.js`, with bilingual fields as `{ en, th }`.
- For a table of registrants, reuse `useRegistrantTable()` + `Pagination.vue`.

## 7. Verify
- Run `npm run build` and it must pass.
- Then run `php artisan serve` and request the new route with curl. Expect 200, or 302 to `/?login=1` when an admin page is fetched without a session.
- Tell the user what you verified and what still needs a browser check.
