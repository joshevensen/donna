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

ANTHROPIC_API_KEY=...
VOYAGEAI_API_KEY=...

DONNA_TIMEZONE=America/Chicago
DONNA_USER_NAME=Josh
DONNA_USER_EMAIL=you@example.com
```

Forge fills in `APP_KEY` and the database username and password. For [using Donna from your phone](blink-iphone.md), also add `DONNA_API_URL` and `DONNA_API_TOKEN`.

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
| `SESSION_DRIVER` | Server, Mac | `database` | On the Mac use `array`, for the same reason. |
| `DB_QUEUE_RETRY_AFTER` | Server | `660` | Seconds before an unfinished job is handed to another worker. Must be longer than the worker's timeout ([queue-and-scheduler.md](queue-and-scheduler.md)). Leave it. |

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
