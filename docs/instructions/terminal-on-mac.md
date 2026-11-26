# Talking to Donna from your Mac

Until the Phase 2 app exists, you talk to Donna with `php artisan donna:chat`, run on your Mac against the server. It only calls the server's API over HTTPS and never touches a database, so a closed terminal loses nothing.

## Before you start

- The server is up: [forge-site.md](forge-site.md) and [queue-and-scheduler.md](queue-and-scheduler.md) are done.
- PHP and Composer are installed on your Mac.

## 1. Make your token

On the server, create a token for your Mac. It is shown once:

```bash
cd /home/forge/donna.joshevensen.com/api && php8.4 artisan donna:token mac
```

## 2. Set up the Mac

In a clone of the repo:

```bash
cd api && composer install && cp .env.example .env && php artisan key:generate
```

Then put these in that local `api/.env`:

```dotenv
CACHE_STORE=file
SESSION_DRIVER=array
DONNA_API_URL=https://donna.joshevensen.com
DONNA_API_TOKEN=the-token-from-the-server
```

## 3. Talk to her

```bash
php artisan donna:chat
```

- Enter adds a new line. Send with Ctrl+D (or F5 where your keyboard sends it).
- `/good [note]` and `/bad [note]` flag her last reply as an example for the eval set.
- `/history [n]` shows more of the conversation, and `/quit` leaves.

When you open it again it shows the latest exchange, including a reply that finished while you were away.

## If it won't connect

- "Set DONNA_API_URL and DONNA_API_TOKEN" means the Mac's `api/.env` is missing them.
- "Donna did not accept the token" means the token is wrong or was copied incompletely. Make a new one.
- "Could not reach Donna" means the URL is wrong or the server is down; check `https://donna.joshevensen.com/up`.

Next: [verify-setup.md](verify-setup.md).

---

[← All setup instructions](README.md)
