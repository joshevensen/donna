# Forge site

The Laravel app on the droplet, served at `donna.joshevensen.com`.

## Before you start

- [forge-server.md](forge-server.md) is done, and you have the server's IP address.
- Forge is connected to your GitHub account, so it can pull `joshevensen/donna` *(unverified; where this lives in Forge may differ)*.
- Both API keys are ready: [Anthropic](anthropic-api-key.md) and [Voyage](voyage-api-key.md).

## 1. DNS

At your registrar, add an `A` record for `donna.joshevensen.com` pointing at the server's IP address. The certificate in step 3 can't be issued until it resolves.

## 2. Create the site

In Forge, create a site for `donna.joshevensen.com` from the `joshevensen/donna` repository, branch `main`.

The Laravel app is in the repo's `api/` folder, not at its root, so:

- **Web directory:** `/api/public`
- Leave "install Composer dependencies" off during setup. The deploy script below does it from the right folder.

## 3. Certificate

Issue a **Let's Encrypt** certificate for the domain.

## 4. Environment

Open the site's environment file and set the values in [environment-variables.md](environment-variables.md#production-environment). Forge fills in `APP_KEY` and the database username and password; leave those as they are.

## 5. Deploy script

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

- `db:seed` is safe on every deploy. It creates Josh, Donna, Kristen, the first character profile and the interview areas if they are missing, and never overwrites anything Donna has learned.
- `optimize` caches the configuration, so **a changed environment variable only takes effect after a redeploy**.
- `queue:restart` makes the worker pick up new code.

## 6. First deploy

Deploy once. Turn on "deploy on push" if you want every push to `main` to ship itself.

Then `https://donna.joshevensen.com/up` should answer 200.

Next: [queue-and-scheduler.md](queue-and-scheduler.md).

---

[← All setup instructions](README.md)
