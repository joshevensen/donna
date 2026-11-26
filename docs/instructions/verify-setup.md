# Checking it worked

After setup, and again after anything big changes on the server.

## Right away

- `https://donna.joshevensen.com/up` answers 200. If not, the site or its certificate isn't set up ([forge-site.md](forge-site.md)).
- Send her a message from [your Mac](terminal-on-mac.md). A reply arrives.
  - If it sits on "thinking" forever, the queue worker isn't running ([queue-and-scheduler.md](queue-and-scheduler.md)).
  - If it comes back as "could not reply", read the end of `storage/logs/laravel.log` on the server. A wrong API key is the usual cause.
- Ask her "what did you cost me today?" A non-zero answer means cost tracking is recording.

## The morning after

Daily extraction runs at 03:00 in `DONNA_TIMEZONE`. By morning, on the server:

```bash
cd /home/forge/donna.joshevensen.com/api && php8.4 artisan tinker --execute 'echo App\Models\ExtractionRun::latest("id")->first()?->status->value;'
```

That prints `applied`. If it prints `submitted`, the batch is still processing and the check will pick it up within the day. If it prints `failed`, see `error` on that row and `storage/logs/laravel.log`. If it prints nothing, the scheduler isn't running ([queue-and-scheduler.md](queue-and-scheduler.md)).

Then ask her something you told her yesterday and see whether she knows.

## Failed jobs

```bash
cd /home/forge/donna.joshevensen.com/api && php8.4 artisan queue:failed
```

Lists anything that gave up after its retries.

---

[← All setup instructions](README.md)
