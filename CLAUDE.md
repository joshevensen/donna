# Donna — repo guide

Donna is Josh's personal AI: one user, self-hosted. `docs/vision.md` is the source of truth. Read the relevant section before building anything, and push back on work that conflicts with it rather than quietly diverging.

## Layout

- `api/` — Laravel, pure JSON API (no views, no frontend)
- `apple/` — one Xcode project: iOS/iPadOS/macOS app, watchOS capture app, and the Home Node agent (headless macOS app)
- `docs/` — `vision.md` (what and why), `domain.md` (concepts and invariants), `phases.md` (build order), `tech/phase-N.md` (one per phase: user stories, design, mockups; written with Josh, approved, then implemented in one shot), `landscape.md` (dated facts); update the relevant doc when a decision changes it

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel — pure API, JSON responses only |
| Native client | Swift / SwiftUI — iOS, iPadOS, macOS, watchOS (capture only); dark mode only |
| Home Node | Mac mini — headless Swift agent, outbound-only over Tailscale, EventKit, Messages database, Foundation Models framework |
| Durable workflows | `durable-workflow/workflow` — Presence and Orchestration jobs |
| AI SDK | Laravel AI SDK (`laravel/ai`) — provider-agnostic, Anthropic by default |
| LLM provider | Anthropic (Haiku / Sonnet / Opus by task); Apple on-device model for Home Node triage |
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

## Rules from the Vision

- **Code before LLM** — deterministic work (fetch, filter, file ops, totals) runs as code, never as a model call
- **Integrations are direct APIs packaged as Agent Skills** (`SKILL.md` + Laravel tool classes), not MCP servers
- **Keep the "absorb" layer thin** — orchestration prompts, routing tables and generic memory algorithms should be easy to delete later
- **Every LLM call records a row in `costs`** — model, tokens in/out, USD, and what triggered it
- **Graduated Autonomy is enforced server-side** — external or irreversible actions confirm first unless trust has been granted
- **Credentials stay encrypted on Josh's server** — never logged, never sent to the model
- **Never merge PRs** — merging stays with Josh
