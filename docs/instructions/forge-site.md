# Forge site

The Laravel app on the droplet, served at `donna.joshevensen.com`.

## Before you start

- [forge-server.md](forge-server.md) is done, and you have the server's IP address.
- Forge is connected to your GitHub account, so it can pull `joshevensen/donna` *(unverified; where this lives in Forge may differ)*.
- You don't need the API keys yet. The site deploys without them; the next two files add them to its environment.

## 1. DNS

At your registrar, add an `A` record for `donna.joshevensen.com` pointing at the server's IP address. The certificate in step 3 can't be issued until it resolves.

## 2. Create the site

In Forge, create a site for `donna.joshevensen.com` from the `joshevensen/donna` repository, branch `main`. Open **Advanced settings** and set:

| Setting | Value | Why |
|---|---|---|
| Root directory | `/api` | The Laravel app is in the repo's `api/` folder, not at its root. Forge's help text says this is "where your application code lives". |
| Web directory | `/public` | Measured from the root directory *(unverified; if the site shows a 404 after the first deploy, try `/api/public`)*. |
| PHP version | 8.4 | |
| Zero downtime deployments | On, Forge's default | It can only be set when the site is created. These instructions assume it is on. |
| Frontend package manager, Build command | Leave as they are | Donna has no frontend. The deploy script in step 5 has no npm lines, so these do nothing. |

If the form offers to install Composer dependencies, leave it off. The deploy script does that.

## 3. Certificate

Issue a **Let's Encrypt** certificate for the domain.

## 4. Environment

Open the site's **Environment** tab. Forge copied `api/.env.example` into it, and its docs say it replaces some settings to match the server's database, without saying which. Check these, fixing any that are blank or wrong:

- `APP_KEY` set. If it is blank, make one on any machine with `openssl` and paste it in:

```bash
echo "base64:$(openssl rand -base64 32)"
```

- `DB_CONNECTION=pgsql`, `DB_DATABASE=donna`, `DB_USERNAME=forge`, and `DB_PASSWORD` set to the password of the `forge` database user, which Forge showed when you created the server. If you didn't keep it, reset it from the server's **Databases** tab. Forge's docs say to change it only there.
- The rest of the values in [environment-variables.md](environment-variables.md#production-environment). `.env.example` starts with `APP_ENV=local` and `APP_DEBUG=true`, so these must be changed.

Leave out the `ANTHROPIC_API_KEY` and `VOYAGEAI_API_KEY` lines for now.

## 5. Deploy script

In the site's deployment settings *(unverified; where the script is edited may differ)*, replace the whole script with:

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

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

## 6. First deploy

Deploy. Turn on "push to deploy" if you want every push to `main` to ship itself.

Then `https://donna.joshevensen.com/up` should answer 200.

## 7. Find the app's folder

Forge builds each deploy in its own folder and points `current` at the live one, so the app is not where you might guess. SSH in:

```bash
ssh forge@<server-ip>
```

```bash
find -L /home/forge/donna.joshevensen.com/current -maxdepth 2 -name artisan
```

The folder that holds `artisan` is the app's folder. The other instructions call it `<app-folder>`, and where they show a command that runs in it, replace `<app-folder>` with this path.

Next: [anthropic-api-key.md](anthropic-api-key.md).

---

[← All setup instructions](README.md)
