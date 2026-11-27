# Donna

A personal AI that knows me, remembers everything, and doesn't let me slack off. A Laravel app with a web chat (and later SwiftUI apps).

Donna is a personal tool, not a product: one user, self-hosted, built for exactly how I work. See [`docs/vision.md`](docs/vision.md) — it's the source of truth for what Donna is and isn't.

## Layout

One Laravel app at the repo root:

```
app/ routes/ resources/ database/ tests/ ...   Laravel: the JSON API at /api/v1, plus the React web app (resources/js)
docs/                                          Vision, design, build order, and setup instructions
```

The native apps (later) will get their own repository or folder when Presence begins.

## Docs

- [`docs/vision.md`](docs/vision.md) — what Donna is and why
- [`docs/domain.md`](docs/domain.md) — concepts and the rules that always hold
- [`docs/phases.md`](docs/phases.md) — build order and where things stand
- [`docs/instructions/`](docs/instructions/README.md) — setting up everything outside the repo: keys, Forge, DNS, backups, and talking to Donna from your Mac or iPhone
