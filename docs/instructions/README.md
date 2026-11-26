# Setup instructions

Everything Donna needs that isn't in the repo: accounts, API keys, the server, DNS, backups, and the devices Josh talks to her from. The code is in the repo; this is the rest.

Each file is one task. Do them in this order the first time. Afterwards they are reference: look up a variable, rotate a key, add a device.

**This folder is kept current by whoever changes the code.** A change that needs something new outside the repo adds or updates the matching file in the same PR (see [`CLAUDE.md`](../../CLAUDE.md)).

## First-time setup

| # | File | What it covers |
|---|---|---|
| 1 | [`forge-server.md`](forge-server.md) | The droplet, and one recipe that gives it swap and pgvector |
| 2 | [`forge-site.md`](forge-site.md) | The site: DNS, certificate, environment, deploy script. The keys below go into its environment |
| 3 | [`anthropic-api-key.md`](anthropic-api-key.md) | The key Donna thinks with, and the spend limit on it |
| 4 | [`voyage-api-key.md`](voyage-api-key.md) | The key her memory searches with |
| 5 | [`queue-and-scheduler.md`](queue-and-scheduler.md) | The worker that writes her replies and the clock that runs the daily jobs |
| 6 | [`backups.md`](backups.md) | Droplet backups and database dumps to Spaces |
| 7 | [`terminal-on-mac.md`](terminal-on-mac.md) | Your token, and talking to her from your Mac |
| 8 | [`verify-setup.md`](verify-setup.md) | How to tell it all works |

## Optional and reference

| File | What it covers |
|---|---|
| [`blink-iphone.md`](blink-iphone.md) | Talking to her from your iPhone with Blink Shell |
| [`environment-variables.md`](environment-variables.md) | Every setting: where it lives, its default, what it does |
| [`operating.md`](operating.md) | Resizing, spend, updating, and what to look at when something is off |

## Conventions

- Forge, DigitalOcean, Anthropic and Blink rename their screens now and then. Where a label doesn't match what you see, the goal of the step is what matters.
- A step marked *(unverified)* was written from documentation, not followed live. When you follow one and it differs, fix the file.
- Commands assume the server's `forge` user and the site at `/home/forge/donna.joshevensen.com`. `<app-folder>` is the folder on the server that holds the app's `artisan` file; [forge-site.md](forge-site.md#7-find-the-apps-folder) shows how to find it, and you replace it in any command that shows it.
- Never put a key in the repo, in a chat with Donna, or in a pull request.
