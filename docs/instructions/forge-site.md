# Forge site

The Laravel app on the droplet, served at `donna.joshevensen.com`: the API, and the web chat you open in a browser or from your iPhone's Home Screen.

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
| Frontend package manager, Build command | Leave as they are | The deploy script in step 4 installs and builds the web app itself, so these do nothing. |

If the form offers to install Composer dependencies, leave it off. The deploy script does that.

## 3. Environment

Open the site's **Environment** tab. Forge copied `.env.example` into it, and its docs say it replaces some settings to match the server's database, without saying which. Check these, fixing any that are blank or wrong:

- `APP_KEY` set. If it is blank, make one on any machine with `openssl` and paste it in:

```bash
echo "base64:$(openssl rand -base64 32)"
```

- `DB_CONNECTION=pgsql`, `DB_DATABASE=donna`, `DB_USERNAME=forge`, and `DB_PASSWORD` set to the password of the `forge` database user, which Forge showed when you created the server. If you didn't keep it, reset it from the server's **Databases** tab. Forge's docs say to change it only there.
- The rest of the values in [environment-variables.md](environment-variables.md#production-environment). `.env.example` starts with `APP_ENV=local` and `APP_DEBUG=true`, so these must be changed.

Leave out the `ANTHROPIC_API_KEY` and `VOYAGEAI_API_KEY` lines for now, and the mail and notification lines: [mail.md](mail.md) and [web-push.md](web-push.md) add those. The `SESSION_` and `SANCTUM_` lines in that list matter from the first deploy: without them you are signed out of the web app after two hours.

Forge links this file into the app's folder on every deploy, so this tab is the only place to edit it. A deploy that gets through `migrate` is the proof it is being found: without it, Laravel would fall back to SQLite and the pgvector migration would fail.

## 4. Deploy script

In the site's deployment settings, replace the whole script with:

```bash
$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader

npm ci
npm run build
rm -rf node_modules

mkdir -p storage/framework/{sessions,views,cache/data}
mkdir -p storage/logs

$FORGE_PHP artisan optimize
$FORGE_PHP artisan migrate --force
$FORGE_PHP artisan db:seed --force

$ACTIVATE_RELEASE()

$RESTART_QUEUES()
```

- `$CREATE_RELEASE()`, `$ACTIVATE_RELEASE()` and `$RESTART_QUEUES()` are Forge's zero-downtime steps. Each deploy is built in a new folder, switched live at the end, and the queue worker is restarted onto the new code.
- **`cd $FORGE_RELEASE_DIRECTORY` has to be there.** `$CREATE_RELEASE()` makes the new release folder but does not move into it; Forge's own default script has this line right after it for that reason. Without it every command runs in `/home/forge` and the deploy fails at the first one.
- Forge's default script has no `composer install`, so it is added here.
- **The order matters: build first, migrate last.** If the build fails after `migrate` has run, the old release keeps serving against a database that has already changed. With the build first, a failed build changes nothing.
- The two `mkdir` lines make the folders Laravel writes to. A new site's shared `storage` folder starts empty, and Forge's docs don't say whether it fills these in.
- `optimize` caches the configuration, so **a changed environment variable only takes effect after a redeploy**.
- `db:seed` is safe on every deploy. It creates Josh, Donna, Kristen, the first character profile and the interview areas if they are missing, and never overwrites anything Donna has learned.
- `npm ci` installs exactly what `package-lock.json` lists, and `npm run build` compiles the web app into `public/build`. Both run inside the new release folder, before it goes live, so a failed build never reaches the site. An earlier version of this script had no npm lines, because Donna had no web app then; they are needed now.
- `rm -rf node_modules` throws away the build tools once the app is built. Nothing needs them at run time, and Forge keeps several releases, so leaving them would fill a small disk with copies.
- The build needs **Node 22 or newer** on the server. Check over SSH with `node -v`. Forge installs Node when it provisions a server *(unverified: which version)*. If it prints something older than `v22`, upgrade it before deploying *(unverified: Forge may offer this in the server's settings; otherwise install Node 22 from NodeSource)*.
- The build is the heaviest thing the droplet does: on the first deploys, watch how long the `npm` lines take and that the deploy doesn't die there. See "If the deploy fails".

## 5. First deploy

Deploy. The log should show "Linking environment file" and "Linking storage directories" and end without an error. Turn on "push to deploy" if you want every push to `main` to ship itself.

There is no certificate yet, so check it over plain HTTP: `http://donna.joshevensen.com/up` should answer 200. That also needs the DNS from step 1 to have spread. Don't try to sign in yet: the web app only works over HTTPS, which step 6 adds.

### If the deploy fails

| Error | Cause | Fix |
|---|---|---|
| `npm: command not found` in the deploy log, or npm complaining about the Node version | Node isn't installed, or is older than 22. | Install or upgrade Node on the server (step 4), then deploy again. |
| The deploy dies during `npm ci` or `npm run build` with `Killed`, or just stops | The droplet ran out of memory. | Check the swap file from [forge-server.md](forge-server.md) exists (`swapon --show`). If it does and builds still die, the droplet needs more memory ([operating.md](operating.md)). |
| Every page of the site answers 500 and the log says `Vite manifest not found` | The deploy script has no `npm run build`, so the web app was never built. | Use the script in step 4 and deploy again. |
| `Composer could not find a composer.json file in /home/forge` | The script has no `cd $FORGE_RELEASE_DIRECTORY` after `$CREATE_RELEASE()`, so it is running in the home folder, not the release. | Add the line (step 4) and deploy again. |
| `Composer could not find a composer.json` in a `releases/...` folder, or `cd: api: No such file or directory` | The site's **Root directory** is still `/api` from an earlier version of this repo, where the app lived in an `api/` folder. | Set **Root directory** to `/` in the site's Advanced settings, point the queue worker and scheduler at `/home/forge/donna.joshevensen.com/current` (see [queue-and-scheduler.md](queue-and-scheduler.md)), and deploy again. |

A failed deploy never goes live. Forge only switches to a release in `$ACTIVATE_RELEASE()`, so fix the script and deploy again.

## 6. Certificate

Do this after the first deploy, once `dig +short donna.joshevensen.com` prints the server's IP address. Forge can't issue a certificate before the domain resolves.

1. **Stop the `www` redirect.** Edit the site's domain, `donna.joshevensen.com`, and set the redirect to **No Redirect**. Donna lives on a subdomain, so there is no `www.` version of it to redirect from.
2. Issue a **Let's Encrypt** certificate for the domain.

Then `https://donna.joshevensen.com/up` should answer 200, and `https://donna.joshevensen.com` should show Donna's icon and a sign-in form. Signing in needs either mail ([mail.md](mail.md)) or `donna:login` on the server.

## 7. Upload limits

Files are attached one per request, up to 10 MB each. PHP and Nginx each have their own limit on the size of a request, and both must be above that, or a large file is turned away before Donna sees it.

In Forge, open the **server**, then its **PHP** settings, and set **Max File Upload Size** to `15` (megabytes) *(unverified: Forge has had this setting for years and it changes PHP and Nginx together, but its name and place may have moved)*.

Then check both over SSH:

```bash
grep -E '^(upload_max_filesize|post_max_size)' /etc/php/8.4/fpm/php.ini
```

Both should be `15M` or more *(unverified: the path, and Forge's defaults, which are thought to be higher than this already)*.

```bash
grep -rh client_max_body_size /etc/nginx/ | sort -u
```

Should show `15M` or more, or the limit Nginx applies is its own default of 1 MB.

How to tell from the web app: attach a file of about 9 MB. If its chip says "Too large. Max 10 MB" although the file is under 10 MB, one of the two limits is too low.

## 8. Check the app's folder

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
