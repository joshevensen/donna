# Operating Donna

What to keep an eye on once she is running.

## Updating her

Pushing to `main` ships it, if you turned on "deploy on push" ([forge-site.md](forge-site.md)). Otherwise press Deploy in Forge. A deploy installs and builds the web app, runs migrations, re-seeds anything missing without touching what she has learned, rebuilds the config cache and restarts the worker.

The build (`npm ci` and `npm run build`) is the heaviest thing the droplet does. If deploys start taking minutes, or die partway with `Killed`, the droplet is short of memory: see "Resizing the server". A failed deploy never goes live.

An open web app picks up a new version the next time it is reloaded or reopened. Nothing forces it.

## Signing in, and staying signed in

- You sign in with a code emailed to `DONNA_USER_EMAIL` ([mail.md](mail.md)). No other address can sign in, and there is no password.
- **If the email doesn't come**, `php8.4 artisan donna:login` on the server prints a code and a link that work once, for 10 minutes.
- A device stays signed in for 30 days after you last used it (`SESSION_LIFETIME`), and at most 90 days in a row however much you use it (`DONNA_SESSION_MAX_DAYS`). When it ends, the app goes to the sign-in form and keeps whatever you were typing.
- Every sign-in sends you a "New sign-in to Donna" email naming the device, the time and the IP address. If one arrives that wasn't you, press **Sign out everywhere** in it.

## A lost or stolen device

Any one of these signs every browser and phone out and turns off all their notifications:

- Tell her "sign me out everywhere" and tap the button she gives you, or type `/signout all`.
- Press **Sign out everywhere** in any "New sign-in to Donna" email from the last 7 days. It works from any device, signed in or not.

Then sign in again on the devices you still have, and turn notifications back on ([web-push.md](web-push.md)).

The terminal's tokens are separate and are **not** revoked by any of that. If the lost device held one (a Mac with `DONNA_API_TOKEN` in its `.env`), revoke them all on the server and make new ones ([terminal-on-mac.md](terminal-on-mac.md)):

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan tinker --execute 'App\Models\Person::josh()->user->tokens()->delete();'
```

## Files and what they cost to keep

- **Nothing is deleted automatically.** Every file you attach stays, kept or not. So does every version of every text file, and every upload you removed from the input before sending. Asking Donna to delete a file only hides it; she can bring it back.
- **Where it adds up.** Text and its versions are in the database, which is on the droplet's disk: a 100 KB plan edited 50 times is 5 MB. Original PDFs and images are in the Space ([storage.md](storage.md)), where the base plan covers 250 GB *(unverified: DigitalOcean's current Spaces pricing)*. At 10 MB a file it takes thousands of photos to matter.
- **To see how much there is**, on the server:

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan tinker --execute 'echo App\Models\File::withTrashed()->count()." files, ".App\Models\FileVersion::count()." versions, ".round(App\Models\File::withTrashed()->whereNotNull("storage_path")->sum("size_bytes") / 1e6)." MB in the Space";'
```

- **What a kept file costs.** Keeping a file embeds it once, in pieces, and an edit re-embeds only the pieces that changed. A 10 MB kept text file is a few cents; a CSV is only indexed for its first 2,000 rows. It shows in the spend line under the input like any other cost.
- **What a file costs per message.** A small attached file goes to the model whole (up to about 6,000 tokens); a larger one goes as an outline and she reads the parts she needs. An image or a scanned PDF is sent as pictures, which costs more than text, and again each time she looks at it again. If files make replies expensive, the limits are in `config/donna.php` under `files`.
- **If she doesn't find a kept file she should**, or drags in unrelated ones: `context.chunk_max_distance` and `context.related_chunks` in `config/donna.php`.
- Indexing runs on the `default` queue, which the worker already works ([queue-and-scheduler.md](queue-and-scheduler.md)). If it fails, the strip above the input says something failed behind the scenes, and `php8.4 artisan queue:failed` names `IndexFile`.

## Resizing the server

If the droplet regularly sits above about 80% memory, or swap is in steady use, resize it. **Change CPU and RAM only.** That kind of resize is reversible. Never grow the disk: a disk resize can't be undone.

## Spend

- The limit on the Anthropic key is the hard stop ([anthropic-api-key.md](anthropic-api-key.md)).
- She accepts at most 60 messages a minute, so a stolen sign-in can't run up the bill faster than that before the key's limit stops it.
- "What did you cost me this month?" is the running total. Voyage's first 200 million tokens are free and Donna prices them at list anyway, so her number runs slightly high.
- A model she uses that isn't in the pricing table in `config/donna.php` is recorded at $0 and reported as an error, so her totals would run low. Add its price when you change a model.

## When replies start failing

Replies opt into Anthropic's server-side refusal fallback, which is a beta. If Anthropic changes it and replies start failing with a request error, set `DONNA_REFUSAL_FALLBACK=false` in the site's environment and redeploy.

Otherwise start with the end of `storage/logs/laravel.log` on the server and `php8.4 artisan queue:failed` ([verify-setup.md](verify-setup.md)).

In the web app a failed reply shows as "Donna couldn't reply" with **Try again**, which asks for the same reply again without re-sending your message.

## When notifications stop

See the table in [web-push.md](web-push.md#if-it-doesnt-work). The usual causes are the queue worker being down, a Donna tab left open and visible on another device, or the `VAPID_` keys having changed.

## Tuning

Every retrieval count and threshold (how many memories she looks at, when a topic counts as stale, token budgets) is in `config/donna.php`. Model choices are there too, each overridable from the environment ([environment-variables.md](environment-variables.md)).

---

[← All setup instructions](README.md)
