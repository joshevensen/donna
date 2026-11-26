# Deploying Donna

The steps Josh runs once to put Phase 1 on a server, and what to watch afterwards. Everything lives on one Forge-managed DigitalOcean droplet; see the Stack table in [`CLAUDE.md`](../CLAUDE.md).

Forge's screens get renamed now and then. Where a label below doesn't match what you see, the goal of the step is what matters.

---

## Before you start

| You need | Where | Notes |
|---|---|---|
| Anthropic API key | console.anthropic.com | **Set a hard monthly spend limit on it** before anything else. Donna has no spend cap in code. |
| Voyage AI key | MongoDB Atlas → Model API keys | Voyage is owned by MongoDB. No database is involved; the key only works against `ai.mongodb.com`, which is what the app calls. |
| Spaces bucket + access key | DigitalOcean → Spaces | For database backups only in this phase. |
| DNS | Your registrar | An `A` record for `donna.joshevensen.com` pointing at the droplet, once it exists. |

---

## 1. Server

In Forge, create a server on DigitalOcean:

- **Type:** App server
- **Size:** Basic, 2 GB RAM / 1 vCPU ($12/mo)
- **PHP:** 8.4
- **Database:** Postgres, with a database named `donna`
- **Backups:** turn on DigitalOcean droplet backups

Then SSH in (`ssh forge@<ip>`) for two things Forge doesn't do:

**Swap.** Check whether there is any:

```bash
swapon --show
```

If that prints nothing, add 2 GB:

```bash
sudo fallocate -l 2G /swapfile && sudo chmod 600 /swapfile && sudo mkswap /swapfile && sudo swapon /swapfile && echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

**pgvector.** Install the package that matches the server's Postgres version, then enable it in the database. The extension has to be created by the Postgres superuser; the migrations assume it is there.

```bash
psql --version
```

```bash
sudo apt-get install -y postgresql-17-pgvector
```

```bash
sudo -u postgres psql -d donna -c 'CREATE EXTENSION IF NOT EXISTS vector;'
```

(Swap the `17` for whatever `psql --version` reported.)

---

## 2. Site

Create a site for `donna.joshevensen.com` from the `joshevensen/donna` repository, branch `main`.

The Laravel app is in the `api/` folder of the repo, not at its root, so:

- **Web directory:** `/api/public`
- Leave "install Composer dependencies" off during setup — the deploy script below does it from the right folder.

Then issue a **Let's Encrypt** certificate for the domain.

### Environment

Open the site's environment file and set these (leave the rest of what Forge generated as it is):

```dotenv
APP_NAME=Donna
APP_ENV=production
APP_DEBUG=false
APP_URL=https://donna.joshevensen.com

DB_CONNECTION=pgsql
DB_DATABASE=donna

QUEUE_CONNECTION=database
CACHE_STORE=database

ANTHROPIC_API_KEY=...
VOYAGEAI_API_KEY=...

DONNA_TIMEZONE=America/Chicago
DONNA_USER_NAME=Josh
DONNA_USER_EMAIL=you@example.com
```

Forge fills in `APP_KEY` and the database username and password. `DONNA_USER_EMAIL` is only a label on the one user row — you never log in with it.

Spaces credentials do **not** go in the app's environment in Phase 1. The app doesn't touch Spaces until Files (Phase 3); backups are configured in Forge (step 5).

### Deploy script

Replace the site's deploy script with:

```bash
cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

cd api
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --force
$FORGE_PHP artisan optimize
$FORGE_PHP artisan queue:restart
```

`db:seed` is safe on every deploy: it creates Josh, Donna, Kristen, the first character profile and the interview areas if they are missing, and never overwrites anything Donna has learned.

Deploy once now. Turn on "deploy on push" if you want merges to `main` to ship themselves.

---

## 3. Queue worker

Replies are written by a queue worker, not by the web request — that is why closing the terminal loses nothing. Add one worker (a daemon) for the site:

| Setting | Value |
|---|---|
| Connection | `database` |
| Queue | `replies,default` |
| Processes | `1` |
| Timeout | `660` |
| Tries | `1` |
| Directory | the site's `api` folder |

If the form doesn't have a directory field, create a daemon instead with this command, run from the site's `api` folder:

```bash
php8.4 artisan queue:work database --queue=replies,default --timeout=660 --tries=1
```

One process is deliberate: Donna answers one message at a time, because each reply needs the one before it.

---

## 4. Scheduler

Add a scheduled job that runs every minute:

```bash
php8.4 /home/forge/donna.joshevensen.com/api/artisan schedule:run
```

That drives the daily memory extraction (03:00 Chicago time) and the check for its results every ten minutes.

---

## 5. Backups

Two layers:

1. **Droplet backups** — turned on in step 1.
2. **Database dumps to Spaces** — in Forge, add a backup configuration for the server: provider "S3 compatible" (or DigitalOcean Spaces), your Spaces endpoint, bucket, key and secret; database `donna`; daily; keep 14.

If your Forge plan doesn't include database backups, install `s3cmd` on the server, run `s3cmd --configure` once with the Spaces key, and add a nightly scheduled job instead:

```bash
pg_dump -U forge donna | gzip | s3cmd put - s3://YOUR-BUCKET/donna/$(date +\%F).sql.gz
```

Either way, restore one dump to a scratch database once, so you know the backups are real.

---

## 6. First conversation

On the server, create your API token (it is shown once):

```bash
cd /home/forge/donna.joshevensen.com/api && php8.4 artisan donna:token
```

On your Mac, in a clone of this repo:

```bash
cd api && composer install && cp .env.example .env && php artisan key:generate
```

Then put these in that local `api/.env`. The terminal client only talks to the server over HTTPS, so it needs no local database:

```dotenv
CACHE_STORE=file
SESSION_DRIVER=array
DONNA_API_URL=https://donna.joshevensen.com
DONNA_API_TOKEN=the-token-from-the-server
```

And talk to her:

```bash
php artisan donna:chat
```

Type a message and finish with Ctrl+D. `/good [note]` and `/bad [note]` flag her last reply as an example for the eval set, `/history [n]` shows more of the conversation, `/quit` leaves.

### Checking it worked

- `https://donna.joshevensen.com/up` answers 200.
- Her first reply arrives. If it sits on "thinking" forever, the queue worker isn't running; if it comes back as "could not reply", check `storage/logs/laravel.log` on the server — a wrong API key is the usual cause.
- Ask her "what did you cost me today?" — a non-zero answer means cost tracking is recording.
- The morning after: the `extraction_runs` table has a row with status `applied`, and `facts` has rows with provenance `extraction`.

---

## 7. Donna on your phone (Blink Shell)

Until the Phase 2 app exists, Donna works from an iPhone through a terminal app that connects to the server over SSH and runs `donna:chat` there. [Blink Shell](https://apps.apple.com/app/blink-shell/id1594898306) does this.

Dropping the connection costs nothing. Donna writes her replies on the server, and the next time you open `donna:chat` it shows the latest exchange, including a reply that finished while you were away.

### On the server

Create a token of its own for this, so it doesn't share one with your Mac:

```bash
cd /home/forge/donna.joshevensen.com/api && php8.4 artisan donna:token blink
```

Add it to the site's environment in Forge, next to the other settings:

```dotenv
DONNA_API_URL=https://donna.joshevensen.com
DONNA_API_TOKEN=the-token-from-above
```

**Redeploy afterwards.** The deploy script rebuilds the config cache; until it runs, `donna:chat` still reports that the URL and token are missing.

Then add a shortcut, so a session is just `donna`. Run this once over SSH from your Mac:

```bash
echo "alias donna='cd /home/forge/donna.joshevensen.com/api && php8.4 artisan donna:chat'" >> ~/.bashrc
```

### In Blink

1. **Make a key.** Run `config`, tap **Keys**, tap **+**, choose **Generate New**, name it `donna`, and pick **Ed25519**. Blink keeps the private key in the iOS Keychain, encrypted by the Secure Enclave. It has no passphrase option, so turn on Face ID for the app itself (Blink's [docs](https://docs.blink.sh/basics/ssh-keys) say it can protect the whole app; look in its settings).
2. **Authorize it.** Copy the key's **public** key from its screen under Keys and paste it into Forge: the server's **Settings → SSH → Add key**, named `iPhone`.
3. **Add the host.** In `config`, open **Hosts**, tap **+**, and set Alias `donna`, Hostname `donna.joshevensen.com`, User `forge`, and Key `donna`. See Blink's [Hosts docs](https://docs.blink.sh/basics/hosts).
4. **Connect.** In Blink, run `ssh donna`, then `donna`.

Blink's docs don't say where the public-key copy option is, and I couldn't confirm it, so if step 2 has no obvious button, do it from the Mac instead: generate the pair there, then import the private half into Blink (Blink's docs mention importing keys but not the steps).

```bash
ssh-keygen -t ed25519 -f ~/donna-phone -C iphone
```

Add `~/donna-phone.pub` in Forge as above, import `donna-phone` into Blink under **Keys → +**, and delete the private file from the Mac afterwards.

### Using it

- **Sending a message:** Enter starts a new line. Tap **F5** in Blink's Smart Keys bar to send. Ctrl+D works too: hold **CTRL** there and press **D**. The bar is hidden when an external keyboard is attached. If F5 does nothing, set `DONNA_SUBMIT_KEY` to another key (`f1` to `f12`) in the site's environment and redeploy. I couldn't confirm what Blink sends for each key.
- **Leaving:** type `/quit`, or just close Blink.
- **Dropped connections:** iOS can suspend Blink in the background, which can end a plain SSH session. Run `ssh donna` and `donna` again and nothing is lost. [Mosh](https://docs.blink.sh/advanced/advanced-mosh) survives network changes better, and `mosh donna` works the same way once `mosh` is installed on the server (`sudo apt-get install -y mosh`) and its UDP ports (60000 to 61000) are open in the server's firewall. Blink's docs don't say whether either survives the app being backgrounded, so it is optional.
- **A lost phone:** delete the `iPhone` key in Forge. The phone never holds the API token, which lives in the server's environment, so removing the key cuts off its access.

## Keeping an eye on it

- **Resize when memory is tight.** If the droplet regularly sits above ~80% memory, or swap is in steady use, resize it — **CPU and RAM only**. That kind of resize is reversible. Never grow the disk: a disk resize can't be undone.
- **Spend.** The cap on the Anthropic key is the hard stop. "What did you cost me this month?" is the running total; Voyage's first 200M tokens are free and Donna prices them at list anyway, so her number runs slightly high.
- **Refusal fallback.** Replies opt into Anthropic's server-side refusal fallback, which is a beta. If Anthropic changes it and replies start failing with a request error, set `DONNA_REFUSAL_FALLBACK=false` in the environment and redeploy.
- **Tuning.** Every retrieval count and threshold (how many memories, when a topic is stale, token budgets) is in `api/config/donna.php`. Model choices are there too, each overridable from the environment.
