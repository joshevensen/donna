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

## Status

Vision phase. Scaffolding only.
