# Checking it worked

After setup, and again after anything big changes on the server.

## Right away

- `https://donna.joshevensen.com/up` answers 200. If not, the site or its certificate isn't set up ([forge-site.md](forge-site.md)).
- `https://donna.joshevensen.com` shows Donna's icon and a sign-in form.
  - A 500 instead means the web app wasn't built: check the deploy script has its `npm` lines ([forge-site.md](forge-site.md)).
- **Sign in.** Enter your address, press **Email me a code**, and type the code from the email.
  - No email: [mail.md](mail.md). To get in anyway, `php8.4 artisan donna:login` on the server prints a code; enter it with **I already have a code**.
  - The right code only ever gets "Something went wrong": `SANCTUM_STATEFUL_DOMAINS` doesn't match the site's address ([environment-variables.md](environment-variables.md)). The code isn't used up by those tries.
  - The code is accepted and you are straight back at the sign-in form: `SESSION_SECURE_COOKIE=true` on a site without its certificate, or opened over `http://`.
  - A "New sign-in to Donna" email follows every sign-in. That is working as intended.
- **Send her a message.** Your message appears at once, three dots bounce, and a reply arrives.
  - If the dots go on forever, the queue worker isn't running ([queue-and-scheduler.md](queue-and-scheduler.md)).
  - If the thread says "Donna couldn't reply", read the end of `storage/logs/laravel.log` on the server. A wrong API key is the usual cause. **Try again** asks her for the same reply; it does not send your message twice.
- **Close the tab and open it again.** You are still signed in. If not, `SESSION_LIFETIME` is still `120` ([environment-variables.md](environment-variables.md)).
- **Get a notification.** With the app installed and notifications on ([web-push.md](web-push.md)), send a message and lock the phone. Her reply arrives as a notification.
- Ask her "what did you cost me today?" A non-zero answer means cost tracking is recording.
- Write `@` in front of a name she doesn't know ("lunch with @Dave on Friday"). She should ask who that is.

## Files

After Phase 3 is deployed and [storage.md](storage.md)'s check has printed `ok`:

- **Attach a markdown file.** Tap the plus, **Choose a file**, pick a `.md` file, and send it with "here are my notes". A chip appears above the text, the message shows the file as a card, and she answers about what is in it.
  - The chip says "Couldn't upload" for a PDF or a photo but text files work: the Space isn't reachable ([storage.md](storage.md)).
  - The chip says "Too large. Max 10 MB" for a file under 10 MB: the server's upload limits ([forge-site.md](forge-site.md), step 7).
- **Edit it together.** "Replace X with Y everywhere in that file." Her reply has a card that says what changed. Tap it: the viewer shows the new text.
- **Edit it yourself.** In the viewer press **Edit**, change a line, **Save**. Then tell her "I changed it, carry on" and ask what you changed. She should know without being told.
- **See it kept.** Give her something to keep ("this is my vendor notes file, I'll be coming back to it"). She says she kept it and its card shows **Kept**. Flip the switch in the viewer off and on: the badge follows.
- **Get it back.** Much later, or after other talk, ask about something only that file says. She answers from it, and "sources" on her reply lists the file and the section. Tap that source: the file opens.
- **Undo and delete.** "Undo that" after one of her edits puts the text back. "Delete that file", then "bring it back".
- **A CSV.** Attach a Stripe or Shopify export and ask for a total. Check her number against the export. (She gets it from a query run in code, not by adding it up herself.)
- **A PDF.** Attach a real Stripe or Shopify PDF, open it in the viewer, and read the text she was given. The columns of a table should be apart, each amount under its heading. If the cells run together, `pdftotext` isn't installed ([forge-server.md](forge-server.md)). **This is the one check that has never been run on your real files.**
- **A scan.** Attach a scanned PDF or a photo of a receipt and ask what it says. Then, a few messages later, "look at that receipt again".
- `/files` lists kept files, then recent ones, without her.
- Ask "what did you cost me today?" after keeping a long file: the number includes embedding it.

## On the iPhone

These can only be checked on the phone itself:

- **The pickers, in the installed app and in a Safari tab.** **Choose a photo** opens the photo library and the photo arrives as a JPEG (a HEIC original is converted by iOS). **Choose a file** opens Files, possibly after iOS's own menu, and `.md`, `.csv`, `.json` and `.pdf` files can all be picked.
- **The viewer.** It covers the whole screen, the chat behind it can't be scrolled or tapped, and a long file scrolls inside it.
- **Edit mode.** Tapping the text raises the keyboard, the text area shrinks to sit above it, **Cancel** and **Save** stay visible, and the page doesn't zoom.
- **A PDF's "Open original"**, in Safari and in the installed app. In the installed app, check it opens without asking you to sign in and that there is a way back.

- **The keyboard, in the installed app and in a Safari tab.** Tap the input: it opens three lines tall and sits directly above the keyboard, with the newest message still visible above it. Type until it grows to six lines, then scrolls. Send: the keyboard stays up. Tap the thread or swipe the keyboard away: the input drops back to the bottom as a slim bar, with no gap under it and nothing cut off. The page behind never scrolls or bounces.
- **The top of the screen** in the installed app: messages scroll up to the status bar and stop under it; the clock stays readable.
- **Signing in with the code**, typed into the installed app after reading it in Mail. Coming back from Mail should land on the code field.
- **The link in the email**, opened from Mail on the phone. It opens Safari and says that it signs in Safari, not the app. You should not end up signed in to the wrong place without being told.

## The morning after

Daily extraction runs at 03:00 in `DONNA_TIMEZONE`. By morning, on the server:

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan tinker --execute 'echo App\Models\ExtractionRun::latest("id")->first()?->status->value;'
```

That prints `applied`. If it prints `submitted`, the batch is still processing and the check will pick it up within the day. If it prints `failed`, see `error` on that row and `storage/logs/laravel.log`. If it prints nothing, the scheduler isn't running ([queue-and-scheduler.md](queue-and-scheduler.md)).

Then ask her something you told her yesterday and see whether she knows.

## Failed jobs

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan queue:failed
```

Lists anything that gave up after its retries. The web app also shows a strip above the input ("Something failed behind the scenes…") while any failed job exists; it goes away once they are cleared with `php8.4 artisan queue:flush`.

---

[← All setup instructions](README.md)
