# Donna — repo guide

Donna is Josh's personal AI: one user, self-hosted. `docs/vision.md` is the source of truth. Read the relevant section before building anything, and push back on work that conflicts with it rather than quietly diverging.

## Layout

- The repo is one Laravel app: the JSON API at `/api/v1/*` (pure JSON, no other views) plus the React web app in `resources/js`, shown by a single Blade view (`resources/views/app.blade.php`) at every non-API URL. The API never depends on the web app; the native app (not started; it arrives with Presence) will use the API the same way, from its own repository or folder when it begins
- `resources/js` — the web app: `pages/`, `components/`, `hooks/` (state: messages, polling, keyboard, session), `lib/` (pure logic, each with its `*.test.ts` beside it), `api/` (the fetch client). Styles are one file, `resources/css/app.css`, using the tokens in `docs/design.md`. `public/sw.js` is the hand-written service worker (push only, no caching)
- `tests/e2e` — a few Playwright flows through the built web app with the API faked in the browser
- `.github/` — CI (Pint, the full suite on Postgres, then the web app's type-check, Vitest and build) and Dependabot
- `docs/` — `vision.md` (what and why), `design.md` (palette, icon, type, components), `domain.md` (concepts and invariants), `phases.md` (build order), `tech/phase-N.md` (one per phase: user stories, design, mockups; written with Josh, approved, then implemented in one shot), `landscape.md` (dated facts), `instructions/` (how to set up everything outside the repo); update the relevant doc when a decision changes it

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel — the API returns JSON only; the one view is the web app's shell |
| Web client | Browser app, installable to the iPhone Home Screen; chat only; dark mode only. The first front end (Phase 2): React + TypeScript + Vite inside the Laravel app (`resources/js`) |
| Native client | Swift / SwiftUI — iOS, iPadOS, macOS, watchOS (capture only); dark mode only. Arrives with Presence (Phase 7) |
| Home Node | Mac mini — headless Swift agent, outbound-only over Tailscale, EventKit, Messages database, Foundation Models framework |
| Queue + scheduler | Laravel database queue (no Redis) and the Laravel scheduler; durable workflows arrive with Orchestration (Phase 6) |
| Durable workflows | `durable-workflow/workflow` — Presence and Orchestration jobs |
| AI SDK | Laravel AI SDK (`laravel/ai`) — provider-agnostic, Anthropic by default; the Batch API (daily extraction) uses Anthropic's official PHP SDK, which `laravel/ai` doesn't cover |
| LLM provider | Anthropic (Haiku / Sonnet / Opus by task), called directly; Anthropic server-side web search; Apple on-device model for Home Node triage |
| Embeddings | Voyage AI, with a key from voyageai.com (Anthropic has no embeddings model) |
| Skills | Agent Skills standard (`SKILL.md`), stored in Spaces and indexed in Postgres |
| Integrations | Direct APIs via Laravel HTTP client, packaged as skills; Laravel MCP client only as an escape hatch |
| Code worker | Claude Code (headless) + Orc plugin, on a DigitalOcean droplet |
| Hosting | One DigitalOcean droplet, provisioned and deployed with Laravel Forge |
| Database | Postgres on the droplet itself (one server, managed by Forge); full-text + pgvector (hybrid retrieval) |
| File storage | DigitalOcean Spaces |
| Auth | Laravel Sanctum — cookie sessions for the web app, signed in by an emailed code or link (no passwords); tokens for the terminal |
| Mail | Resend, over its API (sign-in codes and the new-sign-in notice only) |
| Backups | Droplet backups + database dumps to Spaces |
| Voice | Apple SpeechAnalyzer (dictation) + App Intents (Siri), in the native app |
| Context signals | Calendar, location, Core Motion (driving), device activity — reported from the apps |
| Push | Web Push for the web app; APNs with the native app |

## Working with Josh

- **Josh owns the what, Claude owns the how.** Josh defines what Donna is and does: user stories, the vision, and (later) UI mockups he approves. Every implementation decision — architecture, schema, packages, patterns, naming — is Claude's to make. Don't ask Josh about code choices; decide, follow best practices, and note the decision in the PR. Josh does want to discuss the design while a phase doc is being written — that's where his input on the how happens, not during implementation.
- **Build it the best way.** Idiomatic Laravel and Swift, well-tested, optimized where it matters. Use Boost, the skills, and these instruction files rather than shortcuts.

- Josh is an expert in Laravel — don't explain Laravel basics
- Josh is learning Swift/SwiftUI — briefly explain non-obvious Swift choices and platform gotchas
- Default to the established stack; propose alternatives only with a compelling reason

## Builds

- **Small changes go straight to `main`.** Commit and push to `main`, with no branch and no PR. Josh's rule; "small" here means the size of a doc or instruction-file correction or a contained fix. A phase build, or anything large enough to want a checklist or a review, still gets a branch and a draft PR.
- The draft PR's checklist is the resume point if a session stops, so it must always match the branch. Updating it is part of every push: commit, push, then tick the finished items in the PR description before starting the next area. Never push a finished area with its box unticked.

## Instructions

- `docs/instructions/` is the step-by-step setup for everything outside the repo: accounts and API keys, Forge, DNS, environment variables, backups, devices. Josh follows it by hand, so it has to be complete and current.
- Any change that needs something outside the repo adds or updates the matching file in the same PR: a new environment variable or config key, an API key or account, a Forge daemon, cron or firewall rule, a DNS record, a device setup. A new `env()` in `config/donna.php` or `config/ai.php` needs a row in `environment-variables.md`; a test fails without one.
- The PR's "Before deploying" section links the instruction files the change touched.
- Every file in `docs/instructions/` gets a row in its `README.md` and ends with a link back to it, so Josh can work through them in order.
- Write each step so it can be followed without reading code. Mark anything you couldn't verify *(unverified)* rather than stating it as fact.

## Testing

Tests exist to protect what Donna does, not to maximize coverage. The `testing-best-practices` skill applies; these rules come first where they're stricter.

- **Every test protects something nameable** — a user story in the phase doc, an invariant in `docs/domain.md`, or a bug it reproduces. If you can't name it, don't write it.
- **Test at the boundary** — feature tests through the HTTP endpoint, artisan command, or queued job. Unit tests only for pure logic with real branching (retrieval scoring, cost pricing).
- **One test per behavior** — variations go in Pest datasets, not copies.
- **Don't test** framework behavior, relationships, casts, config values, migrations, simple accessors, or each validation rule one by one.
- **Don't test prompt wording or model output** — assert what the code does with a response (saved, priced, linked), never the exact prompt text. Whether Donna's answers are good is the eval set's job.
- **No real network** — every model and external API is faked.
- **Fast** — runs in parallel against Postgres with transactions. No fixed time budget, but a test slower than a second needs a reason, and runtime growing faster than features is a signal to look.
- **Prune before the PR** — remove tests that catch the same defect as another. The PR lists test count and suite runtime.
- **Every bug fix adds one regression test.**
- **When to run** — after each change, only the tests for that change (file or `--filter`); the full suite after finishing each larger area of work; the full suite green before opening a PR. Never save all testing for the end.
- **CI is the final check** — GitHub Actions runs the full suite on every PR; a PR isn't ready until CI is green.
- **The web app follows the same rules** — pure logic and components in Vitest (`npm test`), a few whole flows in Playwright against the built app with the API faked (`npm run build && npm run test:e2e`), and `npm run typecheck` clean. Playwright is not in CI; run it before opening a PR that touches `resources/js`. What only a real iPhone can show (the keyboard, push, the installed app) is listed in the PR for Josh to check, never claimed as verified.

## Code or model

Default to code. A model call is only for judgment: understanding language, deciding what matters, or writing in Donna's voice. Every new kind of model call needs a one-line reason in the PR.

**Always code, never a model:** math and totals (costs, counts, dates, durations), sorting, ranking, and filtering, matching names and aliases, thresholds (stale topics, rabbit-hole counts), parsing commands and slash commands, validation, formatting output, applying structured results to the database, scheduling, retries, and anything that can be answered by a query.

**A model, when judgment is needed:** writing Donna's replies, deciding what is a fact and whether it replaces an old one, tagging topics, writing summaries of people and topics, proposing projects or character changes.

**Never:** a model call to reformat, validate, or re-check another model's output (use structured output), to answer something a query can answer, or to summarize data only code will read.

## Rules from the Vision

- **Code before LLM** — deterministic work (fetch, filter, file ops, totals) runs as code, never as a model call
- **Integrations are direct APIs packaged as Agent Skills** (`SKILL.md` + Laravel tool classes), not MCP servers
- **Keep the "absorb" layer thin** — orchestration prompts, routing tables and generic memory algorithms should be easy to delete later
- **Every LLM call records a row in `costs`** — model, tokens in/out, USD, and what triggered it
- **Graduated Autonomy is enforced server-side** — external or irreversible actions confirm first unless trust has been granted
- **Credentials stay encrypted on Josh's server** — never logged, never sent to the model
- **Never merge PRs** — merging stays with Josh

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

## Foundational Context

This application is a Laravel application running on PHP 8.4. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:
- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Use `search-docs` before changes that depend on Laravel ecosystem APIs, behavior, configuration, or version-specific syntax. Skip it for copy-only edits and other changes where package documentation is irrelevant. Reuse sufficient results already in context instead of searching again.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists, including path-scoped framework guidelines under `.ai/rules/boost`. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.

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

- This app is self-hosted on one Forge-managed DigitalOcean droplet (Postgres on the droplet, files and backups in DO Spaces). It is not deployed to Laravel Cloud — don't suggest Cloud. See `docs/vision.md` at the repo root.

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

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

# Pest

- This project uses Pest. Create tests with `php artisan make:test --pest {name}`.
- Do not include the test suite directory in `{name}`. Use `SomeFeatureTest`, not `Feature/SomeFeatureTest`.
- Read the `testing-best-practices` skill for guidance on coverage, naming, structure, dependency isolation, and review.
- Do not delete tests or test files without approval. They are part of the application.

## Running Tests

- Run the narrowest set of tests that covers the change. Pass a file path or `--filter=testName` to `php artisan test --compact`.
- Rerun a test after each change to it.
- Run `vendor/bin/pest` to call the test runner directly. It accepts the same file path and `--filter=testName` arguments.

</laravel-boost-guidelines>
