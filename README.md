# Donna

A personal AI that knows me, remembers everything, and doesn't let me slack off. Laravel + SwiftUI.

Donna is a personal tool, not a product: one user, self-hosted, built for exactly how I work. See [`docs/vision.md`](docs/vision.md) — it's the source of truth for what Donna is and isn't.

## Layout

```
donna/
  api/      Laravel — pure JSON API, Postgres + pgvector, durable workflows
  apple/    One Xcode project — iOS / iPadOS / macOS apps, watchOS capture, Home Node agent
  docs/     Vision and design docs
```

One repo on purpose: most features touch both the API and the apps, so one issue → one PR.

## Docs

- [`docs/vision.md`](docs/vision.md) — what Donna is and why
- [`docs/domain.md`](docs/domain.md) — concepts and the rules that always hold
- [`docs/phases.md`](docs/phases.md) — build order and where things stand
- [`docs/deploy.md`](docs/deploy.md) — putting it on a server, and talking to Donna from the terminal
