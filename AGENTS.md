<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3
- inertiajs/inertia-laravel (INERTIA_LARAVEL) - v3
- laravel/cashier (CASHIER) - v16
- laravel/fortify (FORTIFY) - v1
- laravel/framework (LARAVEL) - v13
- laravel/prompts (PROMPTS) - v0
- laravel/wayfinder (WAYFINDER) - v0
- larastan/larastan (LARASTAN) - v3
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- laravel/pail (PAIL) - v1
- laravel/pint (PINT) - v1
- laravel/sail (SAIL) - v1
- phpunit/phpunit (PHPUNIT) - v12
- @inertiajs/react (INERTIA_REACT) - v3
- react (REACT) - v19
- tailwindcss (TAILWINDCSS) - v4
- @laravel/vite-plugin-wayfinder (WAYFINDER_VITE) - v0
- eslint (ESLINT) - v9
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure. Create module subfolders inside established layer roots as needed; create a new global root folder only when genuinely shared code needs it. Do not create unrelated project-level folders.
- Do not change the application's dependencies without approval.

## Module and Component Organization

- Prefer small, reusable components and shared project patterns before building module-specific implementations from scratch. Inspect existing global components and analogous module hooks, services, models, requests, responses, policies, and actions first; extend a shared implementation backward-compatibly when its behavior is reusable.
- Keep frontend pages in `resources/js/pages/<module>/` and module-only React code in `resources/js/features/<module>/`. Create only needed subfolders such as `components/`, `hooks/`, `services/`, `utils/`, and `types/`; do not create empty scaffolding. Use lowercase module folder names, for example `resources/js/features/horarios/`.
- Put code that is shared across modules in the root of its global layer: `resources/js/components/`, `resources/js/hooks/`, `resources/js/services/`, `resources/js/utils/`, or the existing shared `resources/js/lib/` and `resources/js/types/` folders as appropriate. A new global layer such as `services/` or `utils/` may be added when genuinely shared code needs it. Do not place global code inside one module's feature folder.
- Follow the same logical frontend layout and shared interaction patterns for every module. Reuse global UI primitives, form utilities, filters, tables, pagination, dialogs, drawers, loaders, and permission-aware navigation before adding feature-local equivalents.
- Organize backend code by Laravel layer first, then module. Put module-specific classes in matching subfolders, for example `app/Http/Controllers/Horarios/`, `app/Http/Requests/Horarios/`, `app/Http/Responses/Horarios/`, `app/Models/Horarios/`, `app/Policies/Horarios/`, `app/Actions/Horarios/`, and `app/Services/Horarios/`. Add only layers the module needs; do not create a parallel top-level `app/Horarios/` structure.
- Put backend code intended for reuse across modules in the root of its existing layer, such as `app/Services/`, `app/Actions/`, or `app/Http/Responses/`. Add a new shared layer only when cross-module reuse justifies it. Match PHP namespaces and class names to directory paths and use Laravel generators where applicable.
- Keep Inertia pages under `resources/js/pages/<module>/`; keep Blade views, when required, under `resources/views/<module>/`. Keep module tests under `tests/Feature/<Module>/`. Preserve Laravel's canonical locations and loading conventions for routes, migrations, factories, and seeders rather than inventing parallel registries.
- Apply the same module structure and domain access rules consistently: named routes, server-side authorization, tenant scoping, seeded permissions, module entitlement, shared list/filter/export behavior, and matching loading, empty, error, and permission states. Navigation visibility never replaces backend authorization.
- When extending an existing module, follow this organization for new code and reuse existing patterns. Do not move large sets of existing files solely for cosmetic consistency unless that migration is part of the requested scope.

## New Module Standard

Every new module must use the existing application as its contract. Choose references by behavior, not only by name:

- Use `Puestos` for a simple catalog with active/inactive state, soft deletes, restore, search, filters, and pagination.
- Use `Tipos de documento de empleados` for a catalog with conditional fields, arrays, domain validation, archived records, and relationships that restrict deletion.
- Use `Usuarios` and `Roles` for permissions, protected records, role assignment, transactions, and queued email verification.
- Use `Empleados` for complex forms, private files, child records, atomic mutations, restore rules, and responsive tables.
- Reuse the smallest compatible pattern. Do not copy unrelated fields, rules, or abstractions from a reference module.

### Global UI and Form Contract

Every new module must preserve the system UI contract. Before creating a component, inspect and reuse the global component that already owns the interaction:

- CRUD creation and editing use `ResourceFormDialog`; destructive confirmation uses `ConfirmDeleteDialog`.
- Listings use `ResourceTable` and `ResourcePagination`; page context uses `Breadcrumbs`.
- Search and visible filters use `ResourceSearch` and the global, composable `FiltrosBase`. Extend `resources/js/components/filtros-base.tsx` backward-compatibly when its API is insufficient; never implement a module-local duplicate of search or filter controls.
- Detail views use `ResourceDetailDrawer`; `EmpleadoDetailDrawer` is the behavior and layout reference. Long or complex create/edit flows use `ResourceFormDrawer` only when a dialog is unsuitable.
- Module-specific files may compose global components under `resources/js/features/<module>/`, but must not duplicate their layout, controls, accessibility behavior, loading states, or visual styles. Improve the global component with backward-compatible props or slots when a reusable capability is missing.

All modules must use same design system from `resources/css/app.css` and shared `resources/js/components/ui/` primitives. Reuse semantic theme tokens for background, foreground, primary, secondary, muted, accent, destructive, border, input, ring, sidebar, radius, and typography; do not introduce hard-coded hex, `oklch`, or unrelated Tailwind color values in pages or feature components. Reuse global buttons, inputs, labels, dialogs, drawers, table spacing, typography scale, icon sizing, focus states, responsive breakpoints, and dark-mode behavior. New screens must match global colors, fonts, alignment, spacing, positions, button variants, and interaction feedback; color alone must never convey state.

`resources/js/components/forms/form-utils.ts` is canonical client-side form normalization and format utility. Add reusable normalizers, formatters, and validation helpers there before using a new input rule; every applicable field must consume those helpers through `normalizeInput` or an equivalent shared API. Never duplicate regexes, `onChange` sanitization, or formatting rules inside a page or feature component.

- Integer fields use a shared digits-only normalizer, `inputMode="numeric"`, and a domain-defined maximum length. They reject letters, signs, decimal points, exponent notation, and other non-digits.
- Decimal and money fields use a shared decimal normalizer, `inputMode="decimal"`, no letters or extra separators, and at most two decimal places. Format to two decimals only when business contract requires fixed scale; preserve incomplete user entry while typing.
- Identifier, RFC, CURP, username, file, date, phone, email, currency, percentage, and other repeated formats must have a named global helper with explicit options for domain limits. Do not silently transform a valid, non-empty value beyond its stated contract.
- Client normalization provides immediate feedback only. Laravel Form Requests remain authoritative: validate type, requiredness, bounds, precision, uniqueness, permissions, and cross-field business rules server-side, and return accessible field errors.
- When a global helper changes, update or add focused tests for it and verify every affected form retains same accepted input, formatted output, and server validation contract.

### Multi-company and Platform Contract

This application is a multi-company platform, not a single-tenant application with an optional company field.

- The selected tenant is `EmpresaContext::SESSION_KEY` (`empresa_contexto_id`). `InicializarContextoPermisos` restores it, initializes Spatie's team with `setPermissionsTeamId($empresa->id)`, clears stale relations, and adds request context. Do not read, cache, or set a tenant from client input.
- `EstablecerEmpresaActiva` protects company routes. A regular user without an active eligible membership goes to `empresa-contexto.create`; a platform superadministrator can select any company or return to platform routes.
- Use `platform.admin` only for platform capabilities: plans, companies, modules, permissions, platform users, and logs. Use `empresa.activa` plus `modulo.habilitado:<clave>` for tenant modules. Never make a tenant route usable merely because its navigation item is hidden.
- Every tenant record and child record must be scoped by active `empresa_id` in queries, Form Requests, Policies, route bindings, exports, downloads, activity logs, and storage paths. Never trust an `empresa_id` submitted in a tenant form.
- Company users belong through `membresias_empresa`; roles and permissions are team-scoped. A shared user can have different roles in each company. Preserve the last active company administrator and protect cross-company updates, deletes, file access, and role assignment.
- Platform superadministrator is an explicit exception (`es_superadministrador_plataforma`), not a company role. Check this flag server-side and keep it out of tenant role synchronization.

### Adding or Extending a Module

Before coding, write the module contract and choose the smallest comparable module. Then implement every applicable item below in one coherent change:

1. Define platform or tenant ownership, module key, actors, permissions, enabled-module behavior, data ownership, lifecycle, audit events, filters, reports, files, and explicit `No verificable` business rules.
2. For a tenant module, register its key in `database/seeders/ModuloSeeder.php`, seed its permissions in `RolesAndPermissionsSeeder`, add `modulo.habilitado:<clave>` to routes, expose navigation only when active, and ensure newly created companies receive the entitlement. Platform-only features must not be added to this tenant registry by accident.
3. Create migration, model, factory, and relevant seeder; include `empresa_id`, foreign keys, composite uniqueness and indexes required by tenant-scoped search and ordering. Use `SoftDeletes` and restore rules when historical data must remain available.
4. Add named routes, Policy, separate Store/Update Form Requests, a focused Action or Service for atomic/domain work, and an Inertia response with typed props. Generate and consume Wayfinder actions/routes; never hand-code application URLs.
5. Build page as composition: shared header, `FiltrosBase`, table, pagination, dialogs/drawers, confirmation, loading, error, archived, empty, permission, keyboard, responsive and mobile states. Keep business-specific UI under `resources/js/features/<module>/` when reusable only inside that module.
6. Register exports in `RegistroReportes` when required. Exports must apply same authorization, active-company scope and normalized filters as listing; PDF and Excel must use active company's logo/name only. Sensitive tenant logo or file data must never fall back to an unrelated company's brand.
7. Update sidebar/dashboard visibility, shared TypeScript domain types, translations/messages, module entitlement tests, and all affected role/permission count expectations. Re-run `php artisan wayfinder:generate --with-form --no-interaction` through `npm run types:check` after route changes.

### Authentication, Email, and Two-Factor Contract

- User model implements `MustVerifyEmail`. Creation or email change queues `SendEmailVerificationEmail` after commit; verification uses Laravel's signed, expiring `verification.verify` route. Protected business routes require `verified`.
- Test new-user creation, resend, invalid/expired/tampered link, successful verification, email change revocation, and queued mail content. Use `Queue::fake()`/`Mail::fake()` in automated tests. Do not test real SMTP against a personal or customer mailbox without an explicitly authorized test recipient.
- Self-service two-factor setup must show QR/manual key, require a valid authenticator code before confirmation, expose recovery codes, and support authenticated login challenge/recovery code tests. An administrator must not mark another user's random secret as confirmed: forced enrollment needs a separately designed, user-completable flow.
- Password-reset sending is permission-gated and must remain tenant-scoped. Never expose secrets, recovery codes, signed URLs, passwords, or raw two-factor fields in Inertia props, logs, exports, or UI state.

### Release Validation Matrix

Every module or cross-cutting change must prove applicable paths for:

- guest, unverified user, normal company user, company administrator, platform superadministrator, one-company user, and user shared by multiple companies;
- create, read, update, delete, archive/restore, protected-record, invalid input, authorization denial, duplicate request, active/inactive catalog and dependent-record behavior;
- search, each visible filter, clearing filters, deterministic order, bounded pagination, second-page query preservation, empty/archived results, export filters and tenant isolation;
- desktop and mobile UI, keyboard/focus, label/error association, loading, success/error feedback, dark mode, and use of shared design primitives;
- tenant-safe PDFs/Excel files, authorized previews/downloads, image compression, rollback/cleanup, activity logs, mail/queue jobs, email verification, two-factor challenge, and recovery codes when those concerns apply.

Do not use fixed permission totals in assertions. Assert named permissions or derive expected counts from the seeded definitions so adding a valid permission cannot silently break unrelated coverage.

### Define the Module Contract First

Before implementation, enumerate:

- screens, CRUD operations, special actions, and expected redirects;
- fields, nullable values, defaults, normalization, enums, dates, money, and files;
- relationships, deletion behavior, archive/restore rules, and concurrency risks;
- actors, permissions, visibility rules, and protected records;
- table columns, search fields, filters, default sort, page size, empty state, and mobile presentation;
- success, validation, authorization, empty, loading, error, and archived states;
- required side effects such as activity logs, email, queues, or external APIs.

Unknown business rules must be marked as `No verificable` and confirmed before persisting data or changing compatibility.

### Backend Requirements

- Create Laravel files with `php artisan make:* --no-interaction` when applicable.
- New persisted domain models normally require a migration, model, factory, and useful seeder or explicit decision that a seeder is not applicable.
- Migrations must define correct types, precision, nullability, defaults, foreign keys, delete rules, unique constraints, and indexes matching real filters and sorts.
- Models must define mass-assignment protection, casts, inverse relationships, soft deletes when required, and safe activity-log fields.
- Use separate Form Requests for create and update when rules differ. Normalize input in `prepareForValidation()`, authorize server-side, and consume only `validated()` data.
- Defaults apply only when a field is omitted. Never overwrite a non-empty client value during normalization.
- Controllers authorize, call an Action or Service, flash feedback, and build the response. Transactions, file storage, relation synchronization, locks, and non-trivial domain rules belong in focused Actions or Services.
- Use database transactions for atomic mutations. Coordinate stored-file cleanup because a database rollback does not delete files.
- Private or sensitive files stay on a private disk and are served only through authorized, scoped routes. Validate extension, MIME type, size, generated storage name, and download ownership.
- Toda imagen adjunta debe pasar por `App\Services\ImageCompressor::compressIfImage()` antes de guardarse en la base de datos o en almacenamiento. Mantener compresión centralizada y usar `config/media.php`; archivos no gráficos conservan su flujo normal.
- Protect every list, create, update, delete, restore, download, and special action with a Policy, Gate, or authorized Form Request.
- Add permissions to `RolesAndPermissionsSeeder`; permission names must match Policies, shared Inertia props, navigation, and frontend visibility.
- Use named routes, implicit binding, and scoped bindings for child resources.

### Table, Search, Filter, and Pagination Requirements

Every index table must:

- select only required columns, eager-load displayed relationships, avoid N+1 queries, and use deterministic ordering;
- normalize search text and explicitly define searchable columns;
- validate or safely coerce every filter accepted from the URL;
- use a bounded page size, normally `min(max($request->integer('per_page', 15), 1), 100)`;
- use `paginate($perPage)->withQueryString()` and return a typed Laravel paginator;
- return normalized `filters` props matching frontend TypeScript names and nullability;
- keep search, filters, archived state, `per_page`, and `page` in the URL;
- preserve allowed listing context after successful create, update, delete, and restore through `Controller::redirectToResourceIndex()` plus a per-controller allowlist;
- expose every user-facing backend filter through a labeled UI control. A backend-only filter is allowed only when intentionally reserved and documented;
- include a clear-filter action and show an appropriate empty state for filtered and archived results;
- use `ResourceTable` and `ResourcePagination` unless the module has a documented incompatible requirement;
- provide an accessible mobile alternative, accessible names for icon-only actions and pagination controls, visible focus, and feedback not based only on color.

Pagination is not considered verified by checking `per_page` alone. Tests must create enough records for multiple pages, navigate to another page, and assert that active search/filter query parameters remain in paginator links and responses.

### Inertia React Requirements

- Use Wayfinder imports from `@/actions` or `@/routes`; never hardcode application URLs.
- Use Inertia v3 `<Form>`, `useForm`, `useHttp`, `<Link>`, or `router` patterns. Do not introduce Axios.
- Reuse shared building blocks before creating module-specific JSX: `ResourceHeader`, `ResourceSearch`, `ResourceTable`, `ResourcePagination`, `ResourceFormDialog`, `ConfirmDeleteDialog`, `ArchivedRecordsToggle`, and `RestoreButton`.
- Every Inertia page runs inside `GlobalLoaderProvider`, which displays a full-page loader automatically for Inertia visits, CRUD, filters, pagination, exports, same-origin `fetch`, `XMLHttpRequest`, and `useHttp` requests. Keep button-level spinners as supplementary feedback, never as the only loading state.
- For asynchronous work not visible to the global request listeners, use `useGlobalLoading(isLoading, message)` or `useGlobalLoader().withLoader(task, message)`. Manual handles use `showLoader(message)` and `hideLoader(id)` in a `try/finally` block.
- Keep pages as composition layers. Put reusable domain-specific form or table parts under `resources/js/features/<module>/` or the established compatible module folder.
- Keep server data in Inertia props and transient UI state in React. Do not duplicate server truth in local state.
- Type props, paginators, nullable fields, relations, filters, permissions, and validation errors accurately.
- Forms must show field errors, prevent duplicate submission, communicate processing, close only on success, and preserve relevant listing context.
- Cover loading, empty, validation, network/HTTP error, disabled, success, archived, and permission states when applicable.
- Follow Tailwind CSS v4 and existing dark-mode/responsive conventions. Do not add parallel CSS or new UI dependencies without approval.

### Test Requirements Per Module

Use PHPUnit feature tests and factories. At minimum cover:

- authorized happy paths for create, update, delete, restore, and special actions;
- forbidden access for users missing each relevant permission;
- required, conditional, unique, normalized, boundary, and invalid relation inputs;
- protected-record and last-administrator rules when applicable;
- search, every exposed filter, archived records, deterministic ordering, page-size bounds, multi-page navigation, and query-string preservation;
- database state, relationships, files, responses, flash data, redirects, and essential Inertia props;
- file MIME/size rejection, scoped download authorization, missing files, replacement cleanup, and rollback behavior when files apply;
- queued notifications with `Queue::fake()` and the actual queued Job contract when the application does not use Laravel's default Notification;
- edge cases for null relations, deleted parents, restore prerequisites, duplicate requests, and real concurrency risks.

### Definition of Done

A module is complete only when:

- route, validation, authorization, Action or Service, model, database, Inertia response, TypeScript, UI state, and tests form one verified contract;
- affected PHPUnit tests pass;
- `vendor/bin/pint --dirty --format agent` has been run after PHP changes;
- PHPStan passes for backend changes;
- `npm run types:check`, lint, and format checks pass for frontend changes;
- `npm run build` passes when imports, JSX, styles, routes, or assets change;
- full `composer ci:check` passes before using the module as a reference for future modules;
- no filter exists only accidentally in backend, no route is hardcoded, and no sensitive file is publicly exposed.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-react-development` when working with Inertia client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== inertia-react/core rules ===

# Inertia + React

- IMPORTANT: Activate `inertia-react-development` when working with Inertia React client-side patterns.

</laravel-boost-guidelines>

## Caveman

Respond terse like smart caveman. All technical substance stay. Only fluff die.

Rules:
- Drop: articles (a/an/the), filler (just/really/basically), pleasantries, hedging
- Fragments OK. Short synonyms. Technical terms exact. Code unchanged.
- Pattern: [thing] [action] [reason]. [next step].
- Not: "Sure! I'd be happy to help you with that."
- Yes: "Bug in auth middleware. Fix:"

Switch level: /caveman lite|full|ultra|wenyan
Stop: "stop caveman" or "normal mode"

Auto-Clarity: drop caveman for security warnings, irreversible actions, user confused. Resume after.

Boundaries: code/commits/PRs written normal.

- Usa siempre la skill `$caveman` desde el inicio de cada sesión.
- Usa Caveman en modo `full` por defecto.
- Mantén Caveman activo durante toda la conversación.
- No anuncies que Caveman fue activado.
- Solo cambia a otro modo cuando yo lo solicite explícitamente.
- Conserva intactos código, comandos, rutas, mensajes de error y términos técnicos.
