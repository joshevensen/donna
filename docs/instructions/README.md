# Setup instructions

Everything Donna needs that isn't in the repo: accounts, API keys, the server, DNS, mail, backups, and the devices Josh talks to her from. The code is in the repo; this is the rest.

Each file is one task. Do them in this order the first time. Afterwards they are reference: look up a variable, rotate a key, add a device.

**This folder is kept current by whoever changes the code.** A change that needs something new outside the repo adds or updates the matching file in the same PR (see [`CLAUDE.md`](../../CLAUDE.md)).

## First-time setup

| # | File | What it covers |
|---|---|---|
| 1 | [`forge-server.md`](forge-server.md) | The droplet, and one recipe that gives it swap, pgvector and the PDF reader |
| 2 | [`forge-site.md`](forge-site.md) | The site: DNS, environment, the deploy script (which also builds the web app), first deploy, the certificate once DNS has spread, and the upload limits. The keys below go into its environment |
| 3 | [`anthropic-api-key.md`](anthropic-api-key.md) | The key Donna thinks with, and the spend limit on it |
| 4 | [`voyage-api-key.md`](voyage-api-key.md) | The key her memory searches with |
| 5 | [`queue-and-scheduler.md`](queue-and-scheduler.md) | The worker that writes her replies and the clock that runs the daily jobs |
| 6 | [`storage.md`](storage.md) | The Space where attached PDFs and images live, the key the app uses for it, and the setting that sends files there |
| 7 | [`backups.md`](backups.md) | Droplet backups and database dumps to Spaces |
| 8 | [`mail.md`](mail.md) | The email provider that delivers sign-in codes, its DNS records, and signing in without email |
| 9 | [`web-push.md`](web-push.md) | Notification keys, installing Donna to the iPhone's Home Screen, and turning notifications on |
| 10 | [`verify-setup.md`](verify-setup.md) | How to tell it all works |

## Optional and reference

| File | What it covers |
|---|---|
| [`environment-variables.md`](environment-variables.md) | Every setting: where it lives, its default, what it does |
| [`operating.md`](operating.md) | Resizing, spend, updating, what files accumulate, and what to look at when something is off |

## Conventions

- Forge, DigitalOcean, Anthropic and Resend rename their screens now and then. Where a label doesn't match what you see, the goal of the step is what matters.
- A step marked *(unverified)* was written from documentation, not followed live. When you follow one and it differs, fix the file.
- Commands assume the server's `forge` user and the site at `/home/forge/donna.joshevensen.com`. The app itself lives in `/home/forge/donna.joshevensen.com/current`, which is where the commands below run.
- Never put a key in the repo, in a chat with Donna, or in a pull request.
