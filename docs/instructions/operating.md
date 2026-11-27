# Operating Donna

What to keep an eye on once she is running.

## Updating her

Pushing to `main` ships it, if you turned on "deploy on push" ([forge-site.md](forge-site.md)). Otherwise press Deploy in Forge. A deploy runs migrations, re-seeds anything missing without touching what she has learned, rebuilds the config cache and restarts the worker.

## Resizing the server

If the droplet regularly sits above about 80% memory, or swap is in steady use, resize it. **Change CPU and RAM only.** That kind of resize is reversible. Never grow the disk: a disk resize can't be undone.

## Spend

- The limit on the Anthropic key is the hard stop ([anthropic-api-key.md](anthropic-api-key.md)).
- "What did you cost me this month?" is the running total. Voyage's first 200 million tokens are free and Donna prices them at list anyway, so her number runs slightly high.
- A model she uses that isn't in the pricing table in `config/donna.php` is recorded at $0 and reported as an error, so her totals would run low. Add its price when you change a model.

## When replies start failing

Replies opt into Anthropic's server-side refusal fallback, which is a beta. If Anthropic changes it and replies start failing with a request error, set `DONNA_REFUSAL_FALLBACK=false` in the site's environment and redeploy.

Otherwise start with the end of `storage/logs/laravel.log` on the server and `php8.4 artisan queue:failed` ([verify-setup.md](verify-setup.md)).

## Tuning

Every retrieval count and threshold (how many memories she looks at, when a topic counts as stale, token budgets) is in `config/donna.php`. Model choices are there too, each overridable from the environment ([environment-variables.md](environment-variables.md)).

---

[← All setup instructions](README.md)
