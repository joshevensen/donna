# Talking to Donna from a terminal on your Mac

**This is the fallback.** The main way to talk to Donna is the web chat at `https://donna.joshevensen.com`, in a browser on the Mac or installed on the iPhone ([web-push.md](web-push.md)). The terminal is for when the web app is broken, or when you'd rather stay in a terminal.

`php artisan donna:chat` runs on your Mac against the server. It only calls the server's API over HTTPS and never touches a database, so a closed terminal loses nothing. It is the same conversation as the web chat: what you say in one shows up in the other.

## Before you start

- The server is up: [forge-site.md](forge-site.md) and [queue-and-scheduler.md](queue-and-scheduler.md) are done.
- PHP and Composer are installed on your Mac.

## 1. Make your token

The terminal signs in with a token, not with the emailed code the web app uses. On the server, create a token for your Mac. It is shown once:

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan donna:token mac
```

## 2. Set up the Mac

In a clone of the repo:

```bash
composer install && cp .env.example .env && php artisan key:generate
```

Then put these in that local `.env`:

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
- `@Name` works as it does in the web chat: it tells her this is someone she should know.

A token keeps working until you revoke it. "Sign out everywhere" in the web app does not touch tokens ([operating.md](operating.md#a-lost-or-stolen-device)).

When you open it again it shows the latest exchange, including a reply that finished while you were away.

## If it won't connect

- "Set DONNA_API_URL and DONNA_API_TOKEN" means the Mac's `.env` is missing them.
- "Donna did not accept the token" means the token is wrong or was copied incompletely. Make a new one.
- "Could not reach Donna" means the URL is wrong or the server is down; check `https://donna.joshevensen.com/up`.

---

[← All setup instructions](README.md)
