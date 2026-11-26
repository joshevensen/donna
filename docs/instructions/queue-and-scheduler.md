# Queue worker and scheduler

Two things run in the background on the server. Without the first Donna never answers; without the second she never extracts what she learned.

## Before you start

- [forge-site.md](forge-site.md) is done and the site has deployed, with both API keys in its environment ([Anthropic](anthropic-api-key.md), [Voyage](voyage-api-key.md)).

## Queue worker

Replies are written by a queue worker, not by the web request. That is why closing the terminal loses nothing. In the site's **Processes** settings, add a queue worker *(unverified; the tab and form labels may differ)* with:

| Setting | Value |
|---|---|
| Queue connection | `database` |
| Queue | `replies,default` |
| Number of processes | `1` |
| Timeout | `630` |
| Tries | `1` |

Leave the other fields at their defaults. Forge shows a preview of the command; it should contain `--queue=replies,default`, `--timeout=630` and `--tries=1`.

- **One process is deliberate.** Donna answers one message at a time, because each reply needs the one before it.
- **The three time limits nest.** A single reply is cut off after 600 seconds (`job_timeout` in `api/config/donna.php`). The worker's timeout is 630, and an unfinished job is handed to another worker after 660 (`DB_QUEUE_RETRY_AFTER`). If you raise one, raise the ones above it.
- **Deploys restart it.** `$RESTART_QUEUES()` in the deploy script restarts the worker onto the new code.

### Check which code it runs

Forge's docs don't say which path a queue worker uses when zero-downtime deployments are on. It must be the live app (`current`), not one old release folder, or it would keep running old code after every deploy. On the server:

```bash
sudo grep -rH "directory\|command" /etc/supervisor/conf.d/
```

Find the worker for this site. Its `directory` should go through `current`, not `releases`. If it names a release folder, this step is wrong for your setup; note what it does say and fix it here.

## Scheduler

In the site's Laravel settings, turn on the **Laravel Scheduler** toggle *(unverified; the exact tab may differ)*. Forge's docs say it then runs `schedule:run` every minute using the site's PHP version.

That drives the daily memory extraction (03:00 in `DONNA_TIMEZONE`), the check for its results every ten minutes, and the hourly job that gives any memory saved while embeddings were down its vector.

### Check which code it runs

Forge's docs don't say how the scheduler handles the `current` folder and the root directory either. On the server:

```bash
crontab -l
```

There should be a `schedule:run` line for this site, and its path should go through `current`, not `releases`. If `crontab -l` is empty, look under Forge's Scheduler tab instead.

Next: [backups.md](backups.md).

---

[← All setup instructions](README.md)
