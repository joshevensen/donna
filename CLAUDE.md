# Donna — repo guide

Donna is Josh's personal AI: one user, self-hosted. `docs/vision.md` is the source of truth. Read the relevant section before building anything, and push back on work that conflicts with it rather than quietly diverging.

## Layout

- `api/` — Laravel, pure JSON API (no views, no frontend)
- `apple/` — one Xcode project: iOS/iPadOS/macOS app, watchOS capture app, and the Home Node agent (headless macOS app)
- `.github/` — CI (Pint + the full suite on Postgres) and Dependabot; GitHub only reads these from the repo root, never from `api/`
- `docs/` — `vision.md` (what and why), `domain.md` (concepts and invariants), `phases.md` (build order), `tech/phase-N.md` (one per phase: user stories, design, mockups; written with Josh, approved, then implemented in one shot), `landscape.md` (dated facts), `deploy.md` (server setup); update the relevant doc when a decision changes it

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel — pure API, JSON responses only |
| Native client | Swift / SwiftUI — iOS, iPadOS, macOS, watchOS (capture only); dark mode only |
| Home Node | Mac mini — headless Swift agent, outbound-only over Tailscale, EventKit, Messages database, Foundation Models framework |
| Queue + scheduler | Laravel database queue (no Redis) and the Laravel scheduler; durable workflows arrive in Phase 2 |
| Durable workflows | `durable-workflow/workflow` — Presence and Orchestration jobs |
| AI SDK | Laravel AI SDK (`laravel/ai`) — provider-agnostic, Anthropic by default; the Batch API (daily extraction) uses Anthropic's official PHP SDK, which `laravel/ai` doesn't cover |
| LLM provider | Anthropic (Haiku / Sonnet / Opus by task), called directly; Anthropic server-side web search; Apple on-device model for Home Node triage |
| Embeddings | Voyage AI, via a MongoDB Atlas API key (Anthropic has no embeddings model; no MongoDB database) |
| Skills | Agent Skills standard (`SKILL.md`), stored in Spaces and indexed in Postgres |
| Integrations | Direct APIs via Laravel HTTP client, packaged as skills; Laravel MCP client only as an escape hatch |
| Code worker | Claude Code (headless) + Orc plugin, on a DigitalOcean droplet |
| Hosting | One DigitalOcean droplet, provisioned and deployed with Laravel Forge |
| Database | Postgres on the droplet itself (one server, managed by Forge); full-text + pgvector (hybrid retrieval) |
| File storage | DigitalOcean Spaces |
| Auth | Laravel Sanctum |
| Backups | Droplet backups + database dumps to Spaces |
| Voice | Apple SpeechAnalyzer (dictation) + App Intents (Siri) |
| Context signals | Calendar, location, Core Motion (driving), device activity — reported from the apps |
| Push | APNs |

## Working with Josh

- **Josh owns the what, Claude owns the how.** Josh defines what Donna is and does: user stories, the vision, and (later) UI mockups he approves. Every implementation decision — architecture, schema, packages, patterns, naming — is Claude's to make. Don't ask Josh about code choices; decide, follow best practices, and note the decision in the PR. Josh does want to discuss the design while a phase doc is being written — that's where his input on the how happens, not during implementation.
- **Build it the best way.** Idiomatic Laravel and Swift, well-tested, optimized where it matters. Use Boost, the skills, and these instruction files rather than shortcuts.

- Josh is an expert in Laravel — don't explain Laravel basics
- Josh is learning Swift/SwiftUI — briefly explain non-obvious Swift choices and platform gotchas
- Default to the established stack; propose alternatives only with a compelling reason

## Builds

- The draft PR's checklist is the resume point if a session stops, so it must always match the branch. Updating it is part of every push: commit, push, then tick the finished items in the PR description before starting the next area. Never push a finished area with its box unticked.

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
