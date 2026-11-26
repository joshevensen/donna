# Donna

A personal AI that knows me, remembers everything, and doesn't let me slack off. Laravel API, a web chat, and later SwiftUI apps.

Donna is a personal tool, not a product: one user, self-hosted, built for exactly how I work. See [`docs/vision.md`](docs/vision.md) — it's the source of truth for what Donna is and isn't.

## Layout

```
donna/
  api/      Laravel — pure JSON API, Postgres + pgvector, durable workflows
  apple/    (later) One Xcode project — iOS / iPadOS / macOS apps, watchOS capture, Home Node agent
  docs/     Vision and design docs
```

One repo on purpose: most features touch both the API and the apps, so one issue → one PR.

## Docs

- [`docs/vision.md`](docs/vision.md) — what Donna is and why
- [`docs/domain.md`](docs/domain.md) — concepts and the rules that always hold
- [`docs/phases.md`](docs/phases.md) — build order and where things stand
- [`docs/instructions/`](docs/instructions/README.md) — setting up everything outside the repo: keys, Forge, DNS, backups, and talking to Donna from your Mac or iPhone
