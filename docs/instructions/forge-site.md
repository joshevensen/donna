# Forge site

The Laravel app on the droplet, served at `donna.joshevensen.com`.

## Before you start

- [forge-server.md](forge-server.md) is done, and you have the server's IP address.
- Forge is connected to your GitHub account, so it can pull `joshevensen/donna` *(unverified; where this lives in Forge may differ)*.
- You don't need the API keys yet. The site deploys without them; the next two files add them to its environment.

## 1. DNS

At your registrar, add an `A` record for `donna.joshevensen.com` pointing at the server's IP address. It needs time to spread across the internet, and the certificate in step 6 can't be issued until it has. To see whether it has reached you:

```bash
dig +short donna.joshevensen.com
```

It should print the server's IP address. Carry on with the next steps while you wait.

## 2. Create the site

In Forge, create a site for `donna.joshevensen.com` from the `joshevensen/donna` repository, branch `main`. Open **Advanced settings** and set:

| Setting | Value | Why |
|---|---|---|
| Root directory | `/` | Forge's default: the Laravel app is the repo's root. (If it was `/api` from an earlier version of this repo, change it. See "If the deploy fails".) |
| Web directory | `/public` | Forge's default. |
| PHP version | 8.4 | |
| Zero downtime deployments | On, Forge's default | It can only be set when the site is created. These instructions assume it is on. |
| Frontend package manager, Build command | Leave as they are | Donna has no frontend. The deploy script in step 4 has no npm lines, so these do nothing. |

If the form offers to install Composer dependencies, leave it off. The deploy script does that.

## 3. Environment

Open the site's **Environment** tab. Forge copied `.env.example` into it, and its docs say it replaces some settings to match the server's database, without saying which. Check these, fixing any that are blank or wrong:

- `APP_KEY` set. If it is blank, make one on any machine with `openssl` and paste it in:

```bash
echo "base64:$(openssl rand -base64 32)"
```

- `DB_CONNECTION=pgsql`, `DB_DATABASE=donna`, `DB_USERNAME=forge`, and `DB_PASSWORD` set to the password of the `forge` database user, which Forge showed when you created the server. If you didn't keep it, reset it from the server's **Databases** tab. Forge's docs say to change it only there.
- The rest of the values in [environment-variables.md](environment-variables.md#production-environment). `.env.example` starts with `APP_ENV=local` and `APP_DEBUG=true`, so these must be changed.

Leave out the `ANTHROPIC_API_KEY` and `VOYAGEAI_API_KEY` lines for now.

Forge links this file into the app's folder on every deploy, so this tab is the only place to edit it. A deploy that gets through `migrate` is the proof it is being found: without it, Laravel would fall back to SQLite and the pgvector migration would fail.

## 4. Deploy script

In the site's deployment settings, replace the whole script with:

```bash
$CREATE_RELEASE()

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs

$FORGE_PHP artisan optimize
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --force

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

- `$CREATE_RELEASE()`, `$ACTIVATE_RELEASE()` and `$RESTART_QUEUES()` are Forge's zero-downtime steps. Each deploy is built in a new folder, switched live at the end, and the queue worker is restarted onto the new code.
- Forge's default script has no `composer install`, so it is added here.
- The two `mkdir` lines make the folders Laravel writes to. A new site's shared `storage` folder starts empty, and Forge's docs don't say whether it fills these in.
- `optimize` caches the configuration, so **a changed environment variable only takes effect after a redeploy**.
- `db:seed` is safe on every deploy. It creates Josh, Donna, Kristen, the first character profile and the interview areas if they are missing, and never overwrites anything Donna has learned.
- There are no `npm` lines. Forge's default script has `npm ci || npm install` and `npm run build`, which fail on Donna with "Could not read package.json" because she has no frontend. If your first deploy failed on that, this is the fix.

## 5. First deploy

Deploy. The log should show "Linking environment file" and "Linking storage directories" and end without an error. Turn on "push to deploy" if you want every push to `main` to ship itself.

There is no certificate yet, so check it over plain HTTP: `http://donna.joshevensen.com/up` should answer 200. That also needs the DNS from step 1 to have spread.

### If the deploy fails

Two failures have come up on this site:

| Error in the deploy log | Cause | Fix |
|---|---|---|
| `Could not read package.json` (npm) | Forge's default script has `npm ci` and `npm run build`; Donna has no frontend. | Use the script in step 4, which has no npm lines. |
| `Composer could not find a composer.json`, or `cd: api: No such file or directory` | The site's **Root directory** is still `/api` from an earlier version of this repo, where the app lived in an `api/` folder. | Set **Root directory** to `/` in the site's Advanced settings, point the queue worker and scheduler at `/home/forge/donna.joshevensen.com/current` (see [queue-and-scheduler.md](queue-and-scheduler.md)), and deploy again. |

A failed deploy never goes live. Forge only switches to a release in `$ACTIVATE_RELEASE()`, so fix the script and deploy again.

## 6. Certificate

Do this after the first deploy, once `dig +short donna.joshevensen.com` prints the server's IP address. Forge can't issue a certificate before the domain resolves.

1. **Stop the `www` redirect.** Edit the site's domain, `donna.joshevensen.com`, and set the redirect to **No Redirect**. Donna lives on a subdomain, so there is no `www.` version of it to redirect from.
2. Issue a **Let's Encrypt** certificate for the domain.

Then `https://donna.joshevensen.com/up` should answer 200.

## 7. Check the app's folder

Forge builds each deploy in its own folder and points `current` at the live one. SSH in and check that the app is where the other instructions run their commands:

```bash
ssh forge@<server-ip>
```

```bash
ls /home/forge/donna.joshevensen.com/current/artisan
```

It should print the path. The other instructions run their commands in `/home/forge/donna.joshevensen.com/current` *(unverified: Forge's own pre-filled scheduler command pointed at `current/api/artisan` while the root directory was `/api`, which implies `current/artisan` now)*. If `artisan` is somewhere else, use that folder in their commands instead.

Next: [anthropic-api-key.md](anthropic-api-key.md).

---

[← All setup instructions](README.md)
