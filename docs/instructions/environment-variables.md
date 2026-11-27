# Environment variables

Every setting Donna's own configuration reads, and the ones Laravel needs to run her. Defaults are what applies when the variable is unset.

- **Server** means the site's environment file in Forge. Changing it needs a redeploy, because the deploy script caches the configuration ([forge-site.md](forge-site.md)).
- **Mac** means `.env` in your clone of the repo on your Mac, which only the terminal client needs ([terminal-on-mac.md](terminal-on-mac.md)).

## Production environment

What to set on the server. Leave the rest of what Forge generated as it is.

```dotenv
APP_NAME=Donna
APP_ENV=production
APP_DEBUG=false
APP_URL=https://donna.joshevensen.com

DB_CONNECTION=pgsql
DB_DATABASE=donna

QUEUE_CONNECTION=database
CACHE_STORE=database

SESSION_DRIVER=database
SESSION_LIFETIME=43200
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=donna.joshevensen.com

MAIL_MAILER=resend
RESEND_API_KEY=...
MAIL_FROM_ADDRESS=donna@donna.joshevensen.com
MAIL_FROM_NAME=Donna

VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:you@example.com

ANTHROPIC_API_KEY=...
VOYAGEAI_API_KEY=...

DONNA_TIMEZONE=America/Chicago
DONNA_USER_NAME=Josh
DONNA_USER_EMAIL=you@example.com
```

Forge fills in `APP_KEY` and the database username and password. The mail lines come from [mail.md](mail.md) and the `VAPID_` lines from [web-push.md](web-push.md); the site runs without them, but you can then only sign in with `donna:login` and get no notifications. For the [terminal fallback on your phone](blink-iphone.md), also add `DONNA_API_URL` and `DONNA_API_TOKEN`.

**A site created before the web chat existed has `SESSION_LIFETIME=120` in its environment**, copied from the old `.env.example`. Change it to `43200`, or the web app signs you out after two hours.

## Laravel

| Variable | Where | Default | Notes |
|---|---|---|---|
| `APP_NAME` | Server | `Laravel` | `Donna`. |
| `APP_ENV` | Server | `production` | |
| `APP_DEBUG` | Server | `false` | Never `true` in production: it shows secrets in error pages. |
| `APP_URL` | Server | | `https://donna.joshevensen.com`. |
| `APP_KEY` | Server | | Forge generates it. Changing it breaks anything encrypted with it. |
| `DB_CONNECTION` | Server | | `pgsql`. |
| `DB_DATABASE` | Server | | `donna`. |
| `DB_USERNAME`, `DB_PASSWORD` | Server | | Forge sets these. |
| `QUEUE_CONNECTION` | Server | `database` | No Redis: one user doesn't need it. |
| `CACHE_STORE` | Server, Mac | `database` | On the Mac use `file`, so the terminal client needs no database. |
| `SESSION_DRIVER` | Server, Mac | `database` | Must be `database` on the server: "sign out everywhere" works by deleting the sessions stored there. On the Mac use `array`, so the terminal client needs no database. |
| `SESSION_LIFETIME` | Server | `43200` | Minutes a web sign-in lasts **after it was last used**: 43200 is 30 days. Using the app keeps renewing it, up to `DONNA_SESSION_MAX_DAYS`. |
| `SESSION_SECURE_COOKIE` | Server | | `true`. The sign-in cookie is then only ever sent over HTTPS. Leave it unset on a Mac running the app over plain `http://localhost`. |
| `SANCTUM_STATEFUL_DOMAINS` | Server | `localhost` and the host in `APP_URL` | `donna.joshevensen.com`. The one address whose browser requests are trusted to carry the sign-in cookie. If it is wrong, signing in answers "Something went wrong" even with the right code. Never set it to an empty value. |
| `DB_QUEUE_RETRY_AFTER` | Server | `660` | Seconds before an unfinished job is handed to another worker. Must be longer than the worker's timeout ([queue-and-scheduler.md](queue-and-scheduler.md)). Leave it. |

## File storage

The Space for files ([storage.md](storage.md)). Unused until Phase 3.

| Variable | Where | Default | Secret | Notes |
|---|---|---|---|---|
| `FILESYSTEM_DISK` | Server | `local` | No | `s3` once Phase 3 is deployed. |
| `AWS_ACCESS_KEY_ID` | Server | | Yes | The Space's access key (the `AWS_` names are Laravel's; the values are DigitalOcean's). |
| `AWS_SECRET_ACCESS_KEY` | Server | | Yes | Its secret. |
| `AWS_DEFAULT_REGION` | Server | | No | `nyc3`. |
| `AWS_BUCKET` | Server | | No | `donna-joshevensen`. |
| `AWS_ENDPOINT` | Server | | No | `https://nyc3.digitaloceanspaces.com`, without the bucket name. |
| `AWS_URL` | Server | | No | `https://donna-joshevensen.nyc3.digitaloceanspaces.com`. |
| `AWS_USE_PATH_STYLE_ENDPOINT` | Server | `false` | No | Leave `false`. |

## Mail

Sign-in codes and the new-sign-in notice ([mail.md](mail.md)).

| Variable | Where | Default | Secret | Notes |
|---|---|---|---|---|
| `MAIL_MAILER` | Server | `log` | No | `resend`. The default writes mail into `storage/logs/laravel.log` instead of sending it, which is what you want on a Mac and not on the server. |
| `RESEND_API_KEY` | Server | | Yes | The Resend key with sending access. |
| `MAIL_FROM_ADDRESS` | Server | `hello@example.com` | No | `donna@donna.joshevensen.com`. The part after the `@` must be the domain verified in Resend. |
| `MAIL_FROM_NAME` | Server | The app's name | No | `Donna`. |

## Providers

| Variable | Where | Default | Secret | Notes |
|---|---|---|---|---|
| `ANTHROPIC_API_KEY` | Server | | Yes | [anthropic-api-key.md](anthropic-api-key.md). |
| `ANTHROPIC_URL` | Server | `https://api.anthropic.com/v1` | No | Only to route through a proxy. |
| `VOYAGEAI_API_KEY` | Server | | Yes | [voyage-api-key.md](voyage-api-key.md). |
| `VOYAGEAI_URL` | Server | `https://ai.mongodb.com/v1` | No | Atlas keys only work against this address. |

## Donna

| Variable | Where | Default | Notes |
|---|---|---|---|
| `DONNA_TIMEZONE` | Server | `America/Chicago` | The timezone for dates Donna sees, the 03:00 extraction, and "this month" in cost reports. Storage stays UTC. |
| `DONNA_USER_NAME` | Server | `Josh` | The name on the one user and his Person, used when they are first created. |
| `DONNA_USER_EMAIL` | Server | `josh@heydonna.app` | The only address that can sign in to the web app: sign-in codes and the new-sign-in email go here, so it must be an inbox you read ([mail.md](mail.md)). Changing it and redeploying moves the one user to the new address. |
| `DONNA_SESSION_MAX_DAYS` | Server | `90` | How long a web sign-in can last however much it is used. After this many days the device signs in again. |
| `DONNA_REFUSAL_FALLBACK` | Server | `true` | Replies opt into Anthropic's server-side refusal fallback, which is a beta. Set `false` if replies start failing with a request error after Anthropic changes it. |

## Web Push

Notifications when Donna replies and the app isn't open ([web-push.md](web-push.md)). Leave all three unset and push is simply off.

| Variable | Where | Default | Secret | Notes |
|---|---|---|---|---|
| `VAPID_PUBLIC_KEY` | Server | | No | The public half of the key pair. Browsers are given it when they subscribe. |
| `VAPID_PRIVATE_KEY` | Server | | Yes | The private half. Changing either key cuts off every device that has notifications on; each has to turn them on again. |
| `VAPID_SUBJECT` | Server | The site's address | No | A contact for the push services, as `mailto:you@example.com` or an `https://` address. |

## Models

Each is one job's model. The pricing table in `config/donna.php` must have the model, or its cost is recorded as $0 and reported as an error. Add its price there when you change one.

| Variable | Where | Default | Notes |
|---|---|---|---|
| `DONNA_REPLY_MODEL` | Server | `claude-sonnet-5-5` | Replies. |
| `DONNA_REPLY_EFFORT` | Server | `medium` | How hard the model thinks per reply: `low`, `medium`, `high`, `xhigh` or `max`. Higher costs more and is slower. |
| `DONNA_TAGGING_MODEL` | Server | `claude-haiku-5-5` | Topic tagging. |
| `DONNA_EXTRACTION_MODEL` | Server | `claude-sonnet-5-5` | The daily memory extraction. |
| `DONNA_EMBEDDING_MODEL` | Server | `voyage-4` | Memory search. Changing to a model with a different vector length needs a migration ([voyage-api-key.md](voyage-api-key.md)). |

## Terminal client

Used by `donna:chat`, on your Mac and, for phone use, on the server.

| Variable | Where | Default | Secret | Notes |
|---|---|---|---|---|
| `DONNA_API_URL` | Mac, Server for phone use | | No | `https://donna.joshevensen.com`. |
| `DONNA_API_TOKEN` | Mac, Server for phone use | | Yes | Made with `php artisan donna:token`. Use a separate token for each place. |
| `DONNA_SUBMIT_KEY` | Mac, Server for phone use | `f5` | No | The function key that sends a message, besides Ctrl+D: `f1` to `f12`, or blank for none ([blink-iphone.md](blink-iphone.md)). |

---

[← All setup instructions](README.md)
