# Queue worker and scheduler

Two things run in the background on the server. Without the first Donna never answers; without the second she never extracts what she learned. Both are set up under the site's **Processes** tab in Forge.

## Before you start

- [forge-site.md](forge-site.md) is done and the site has deployed, with both API keys in its environment ([Anthropic](anthropic-api-key.md), [Voyage](voyage-api-key.md)).

## 1. Queue worker

Replies are written by a queue worker, not by the web request. That is why closing the app loses nothing. The same worker sends the notification when a reply finishes while the app is closed ([web-push.md](web-push.md)).

Open the site's **Processes** tab, then **Background processes**, and create a new background process on the **Queue worker** tab. Name it `Donna queue` if the form asks for a name. Set:

| Field | Value | Forge's default |
|---|---|---|
| `php` | PHP 8.4 | |
| `connection` | `database` | blank |
| `processes` | `1` | `1` |
| `--queue` | `replies,default` | blank |
| `--sleep` | `1` seconds | `3` |
| `--timeout` | `630` seconds | `60` |
| `--tries` | `1` | `1` |

Leave the rest as they are: `--backoff` 0, `--rest` 0, `--memory` 128 MB, `--env` blank, `--force` off. Forge shows a preview of the command; it should contain `--queue=replies,default`, `--sleep=1`, `--timeout=630` and `--tries=1`.

- **One process is deliberate.** Donna answers one message at a time, because each reply needs the one before it.
- **Both queues are needed.** Replies are on `replies`; notifications, memory indexing and the daily extraction are on `default`. Listing `replies` first means an answer never waits behind the rest.
- **The timeout must be raised.** Forge's default of 60 seconds would cut a reply off mid-thought.
- **The three time limits nest.** A single reply is cut off after 600 seconds (`job_timeout` in `config/donna.php`). The worker's timeout is 630, and an unfinished job is handed to another worker after 660 (`DB_QUEUE_RETRY_AFTER`). If you raise one, raise the ones above it.
- **A shorter sleep makes her quicker.** An idle worker checks for work every `--sleep` seconds, so at the default a new message could wait up to 3 seconds before she starts on it.
- **Deploys restart it.** `$RESTART_QUEUES()` in the deploy script restarts the worker onto the new code.

### Check which code it runs

Forge's docs don't say which path a queue worker uses when zero-downtime deployments are on. It must be the live app, `/home/forge/donna.joshevensen.com/current`, not one old release folder, or it would keep running old code after every deploy. On the server:

```bash
sudo grep -rH "directory\|command" /etc/supervisor/conf.d/
```

Find the worker for this site. Its `directory` should go through `current`, not `releases` *(unverified)*. If it names a release folder, this step is wrong for your setup; note what it does say and fix it here.

## 2. Scheduler

Open the site's **Processes** tab, then **Scheduler**, and create a new scheduled job. Set:

| Field | Value |
|---|---|
| Name | `Donna scheduler` |
| Command | `php8.4 /home/forge/donna.joshevensen.com/current/artisan schedule:run` |
| User | `forge` |
| Frequency | Every minute. Forge's default is Weekly, so change it. If there is no such option, choose Custom and enter `* * * * *`. |
| Monitor with heartbeats | Leave off |

Forge fills in the command with the path to `artisan` for this site. If what it shows differs from the path above, keep Forge's path and make sure the command ends in `artisan schedule:run`.

That drives the daily memory extraction (03:00 in `DONNA_TIMEZONE`), the check for its results every ten minutes, and the hourly job that gives any memory saved while embeddings were down its vector.

### Check it

On the server, list the jobs Laravel will run:

```bash
php8.4 /home/forge/donna.joshevensen.com/current/artisan schedule:list
```

It should list three: `StartExtraction`, `PollExtractions` and `EmbedMissing`. To see the line Forge installed:

```bash
crontab -l
```

It should show `schedule:run` for this site, with a path that goes through `current`, not `releases`. If `crontab -l` is empty, look under the Scheduler tab instead.

Next: [storage.md](storage.md).

---

[← All setup instructions](README.md)
