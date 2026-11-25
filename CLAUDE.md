# Donna — repo guide

Donna is Josh's personal AI: one user, self-hosted. `docs/vision.md` is the source of truth. Read the relevant section before building anything, and push back on work that conflicts with it rather than quietly diverging.

## Layout

- `api/` — Laravel, pure JSON API (no views, no frontend)
- `apple/` — one Xcode project: iOS/iPadOS/macOS app, watchOS capture app, and the Home Node agent (headless macOS app)
- `docs/` — vision and design docs; update `docs/vision.md` when a decision changes it

## Stack

- **API:** Laravel, Postgres + pgvector (hybrid retrieval with full-text), Sanctum, Laravel AI SDK (`laravel/ai`, Anthropic by default), `durable-workflow/workflow` for Presence and Orchestration jobs
- **Hosting:** DigitalOcean droplet, Postgres on a DO Volume, files in DO Spaces
- **Apple:** Swift / SwiftUI, dark mode only, App Intents for Siri, SpeechAnalyzer for dictation, APNs for push
- **Home Node:** headless Swift agent on a Mac mini, outbound-only over Tailscale

## Working with Josh

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
