# Queue worker and scheduler

Two things run in the background on the server. Without the first Donna never answers; without the second she never extracts what she learned.

## Before you start

- [forge-site.md](forge-site.md) is done and the site has deployed, with both API keys in its environment ([Anthropic](anthropic-api-key.md), [Voyage](voyage-api-key.md)).

## Queue worker

Replies are written by a queue worker, not by the web request. That is why closing the terminal loses nothing. In Forge, add one worker (a daemon) for the site:

| Setting | Value |
|---|---|
| Connection | `database` |
| Queue | `replies,default` |
| Processes | `1` |
| Timeout | `630` |
| Tries | `1` |
| Directory | the site's `api` folder |

If the form doesn't have a directory field, create a daemon instead with this command, run from the site's `api` folder:

```bash
php8.4 artisan queue:work database --queue=replies,default --timeout=630 --tries=1
```

- **One process is deliberate.** Donna answers one message at a time, because each reply needs the one before it.
- **The three time limits nest.** A single reply is cut off after 600 seconds (`job_timeout` in `api/config/donna.php`). The worker's timeout is 630, and an unfinished job is handed to another worker after 660 (`DB_QUEUE_RETRY_AFTER`). If you raise one, raise the ones above it.

## Scheduler

Add a scheduled job in Forge that runs every minute:

```bash
php8.4 /home/forge/donna.joshevensen.com/api/artisan schedule:run
```

That drives the daily memory extraction (03:00 in `DONNA_TIMEZONE`), the check for its results every ten minutes, and the hourly job that gives any memory saved while embeddings were down its vector.

Next: [backups.md](backups.md).

---

[← All setup instructions](README.md)
