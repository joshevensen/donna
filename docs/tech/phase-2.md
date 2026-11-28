# Phase 2 — Web chat

Donna gets a front end: a chat Josh opens from an icon on his iPhone's Home Screen or a tab on his Mac, that feels like texting a person. Scope is in [`phases.md`](../phases.md); look and feel in [`design.md`](../design.md); concepts in [`domain.md`](../domain.md). Phase 1's API and memory are unchanged except where this doc says.

**Status:** built; awaiting daily use. Where the build had to depart from this doc, the text below has been corrected and the change is listed under [What changed while building](#what-changed-while-building). The keyboard, push and the installed app have not been checked on a real iPhone yet.

**Later:** the terminal client (`donna:chat`, the Mac and Blink setups) and the evaluation endpoint (`POST /messages/{message}/evaluation`, the terminal's `/good` and `/bad`) were removed; the web app is the only client. Flagging a reply happens by telling Donna in chat (`FlagReply`). What follows still describes them as built.

**Mockup:** one interactive screen, [open the artifact](https://claude.ai/artifact/TUsiu8dbpssjDijzWax7q1) (private to Josh). Its source is kept in [`phase-2/mockup.dc.html`](phase-2/mockup.dc.html) so the design survives if the link doesn't. It is the iPhone layout; the Mac layout is the same chat in a centered column and wasn't mocked up.

---

## User Stories

### Texting Donna

- I open Donna from an icon on my iPhone's Home Screen, or from a tab on my Mac. It looks like a text thread, not a tool.
- There's one conversation, the same on both devices and in the terminal. Older messages load as I scroll up.
- My message appears the moment I send it. Three dots bounce while she works. Her reply arrives as one bubble, or as a few short ones when that reads better, like someone texting. I never see progress messages, tool calls or thinking.
- When she answers while the app is closed, my phone shows a notification, like a text would.
- Her replies keep their formatting: lists, inline code, and code blocks with a copy button.
- Dates divide the days ("Today", "Yesterday", "Tuesday, Nov 24"). Tap any bubble to see its time. Tap "sources" on one of hers to see what she looked at.
- The input is pinned to the bottom and moves up with the keyboard. When it's empty and I'm not typing, it shrinks to a slim bar so I can see more of the conversation. Typing opens it three lines tall; it grows to six.

### People

- When I write `@Dave`, she knows I mean a person. If she already knows a Dave, she uses what she knows about him; if she doesn't, she starts tracking him and asks who he is. If more than one person fits, she asks which.
- `@` works the same from the terminal.

### When things go wrong

- If a message doesn't send, it stays in the thread marked "Not delivered. Tap to retry." Retrying never sends it twice.
- If Donna couldn't answer, the thread says so and I can tell her to try again.
- If I'm offline, a strip says so, and anything I send is marked not delivered until I'm back.

### Signing in

- I sign in with my email: Donna sends a 6-digit code and a link. On my iPhone I type the code into the installed app; on my Mac I tap the link. I stay signed in on each device for 30 days after I last use it, so in practice I almost never sign in again. After 90 days in a row I sign in again whatever I do.
- There are no passwords. If I lose a phone, I can sign out everywhere.
- Every time a new device signs in, I get an email saying which device and when, with a button to sign everything out. If it wasn't me, I know within minutes.
- I can sign out, and turn notifications on or off, by telling Donna ("sign me out", "sign me out everywhere", "turn on notifications") and tapping the button she gives me, or with `/signout` (or `/signout all`) and `/notifications`.

### Not in this phase

- Attachments and the plus button (Files, Phase 3)
- Autocomplete while typing `@` or `/` (a menu of people and commands). Both are plain text that works without it.
- A projects page, ratings, search, a cost page, or any menu. I ask Donna instead. (Phase 1's rating endpoint stays in the API, unused.)
- Streaming a reply word by word. Replies arrive as whole bubbles.
- A Mac-specific layout beyond the centered column, the native app, and Presence nudges (Phase 7)
- Passwords, and biometric or passkey login

---

## Design

Where this section is silent, follow Laravel and React best practice, the `CLAUDE.md` file and the Boost guidelines, and note notable choices in the PR. If something here turns out to be wrong once you're in the code, deviate, say why in the PR, and update this doc in the same PR.

### Delivery

- Build on a branch with a draft PR against `main` and a checklist from this doc, ticked per push, as `CLAUDE.md` says. Never merge.
- API in Pest feature tests, web in Vitest and a few Playwright flows, Pint, `tsc --noEmit` and the full suite green. CI gains Node steps: `npm ci`, type-check, Vitest, build.
- **The repo flatten is part of this phase.** The Laravel app is now the repo root, `apple/` is gone, and there is one `CLAUDE.md`; this is already done on the working branch and ships in the same PR as the rest of Phase 2. The PR's "Before deploying" starts with the Forge change: set the site's Root directory to `/` and point the queue worker and scheduler at `current/artisan` ([`forge-site.md`](../instructions/forge-site.md), [`queue-and-scheduler.md`](../instructions/queue-and-scheduler.md)). With push-to-deploy on, the first deploy after the merge fails until Forge is changed; that is harmless, since a failed deploy never goes live. Fix Forge and deploy again.
- **Build order** (the PR checklist follows it): (0) the flatten, done; (1) sign-in and sessions, including the cap and new-device email; (2) the API changes (idempotency, retry, `after`, bubbles, sources, actions, mentions, `GET /me`); (3) the web shell, the chat and the keyboard behavior; (4) **stop and test the keyboard on a real iPhone, in Safari and installed**; (5) push; (6) the instruction files and deploy script.
- Every change that needs something outside the repo adds or updates a file in [`docs/instructions/`](../instructions/README.md) in the same PR (listed under Deployment).

### What's decided and why

| Decision | Choice | Reason |
|---|---|---|
| Web stack | React 19 with TypeScript, Vite, React Router, no UI kit and no state library (hooks and context) | Three routes and one real screen; the chat needs fine control of scroll, textarea height and the keyboard, which a UI kit would fight. React is Josh's call. |
| Where it lives | Inside the Laravel app, the normal way: React in `resources/js`, built by Vite with the Laravel plugin, shown by one Blade view at every non-API URL | One app, one deploy, one `package.json`, hot reload in `composer dev`, and the same origin as the API, so no CORS and Sanctum's cookie login just works. The API stays JSON-only at `/api/v1/*`; the web app is its first client, and the native app will be the second. |
| Login | Email sign-in by one-time code and link, no passwords; Sanctum cookie sessions, 30 days sliding | One flow instead of login, forgot and reset pages, and no password to store or lose. A code is needed because on iOS a link opened from Mail signs in Safari, not the installed Home Screen app (they don't share cookies). |
| Reply bubbles | Donna separates bubbles in her reply with a line containing only `---`; code splits it | Deciding when to split is judgment (hers); splitting is code. No schema change. |
| Live updates | Polling, no websockets | One user. Revisit if it feels laggy. |
| Push | Web Push via `laravel-notification-channels/webpush` (v13.0.1 on Packagist, September 2026, requires Laravel `^13.13`; the app is on 13.44) | Standard VAPID Web Push; works for iOS Home Screen web apps (iOS 16.4+). |

### The web app (`resources/js`)

- React 19 + Vite + TypeScript, React Router with the routes `/` (chat), `/login` (email, then the code), and `/login/link/:token` (a page with one "Sign in" button, so mail scanners that prefetch the link don't use it up). Custom hooks for state (messages, polling, keyboard, push); no Redux or other state library.
- **Dev:** `composer dev` runs Vite alongside the app with hot reload; no proxy needed. **Build:** `npm run build` writes `public/build`, served by Nginx. Add `laravel-vite-plugin`, `@vitejs/plugin-react` and `tsc --noEmit` to the checks.
- **Served by Laravel:** one Blade view, `resources/views/app.blade.php` (the `@vite` tags, the manifest and icon links), returned by a catch-all `GET` in `routes/web.php` for every path that isn't `/api/*`, `/sanctum/*` or `/up`. This replaces the old `public/index.html` landing page, which is deleted (otherwise Nginx would keep serving it at `/`). Update the "pure JSON" rule in `CLAUDE.md` (see Deployment). Tests call `withoutVite()` so they don't need a built manifest.
- **Installable:** a web app manifest (name Donna, `display: standalone`, background Canvas `#0F0B0B`, the icon at 192/512 and the 180 touch icon) and `theme-color` Canvas. Icons are exported from `public/favicon.svg` per `design.md`.
- **Markdown:** replies render with `react-markdown` (no `dangerouslySetInnerHTML`, raw HTML ignored): paragraphs, lists, inline code, fenced code (monospace, no highlighting, copy button), links open in a new tab. Josh's own messages render as plain text.
- **The link page on iPhone:** `/login/link/:token` opened in Safari from Mail signs in Safari, not the installed app. When the page isn't running as the installed app on iOS, it says so and points to the code instead (the code email also leads with the code).
- **Dark only:** `<meta name="color-scheme" content="dark">`, tokens as CSS custom properties from `design.md`.
- **Text size:** 16px minimum in inputs so iOS doesn't zoom; `rem` sizes.

### Chat behavior

The look is in `design.md` and the mockup; these are the behaviors to build:

| Behavior | Spec |
|---|---|
| Layout | One column, input pinned to the bottom. Phone: full width. Mac (≥ 768px): centered, 600px max. No header, title bar or menu. |
| Scroll | Newest at the bottom; scrolling to the top loads older messages (cursor `before`), keeping the scroll position. A new message scrolls to the bottom only if the reader was already near it. |
| Bubbles | Josh's: Raised, right. Donna's: Accent purple, left. All corners round (20px) except where bubbles from one sender stack: inner corners are straight, so a run reads as one bubble cut by 2px lines. 10px between groups. |
| Date dividers | Centered "Today", "Yesterday", weekday + date within the week, then "Nov 24". No time. Computed in Josh's timezone (from `GET /me`) so every device agrees. |
| Tap a bubble | Toggles its time. On Donna's bubbles the same line shows "N sources", which opens a bottom sheet titled "What I looked at". |
| Sending | Appears at once in the thread (optimistic, with a client key). On a phone Enter is a new line; with a mouse and keyboard Enter sends and Shift+Enter is a new line. Send is disabled while the field is empty. |
| Typing dots | After a send, three bouncing dots in a Donna bubble until her reply arrives (reduced motion: static). A multi-bubble reply is revealed one bubble at a time with the dots between, paced by bubble length, only when the reply completes while the app is open. Older replies show all bubbles at once. |
| Input | Collapsed (slim bar: plus is **not** shown in this phase, a "Message Donna" tap target, disabled send) when empty and unfocused. Tapping opens a field three lines tall that grows to six, then scrolls (measure the real height, not character counts). iOS only opens the keyboard when `focus()` runs synchronously inside the tap, so keep the textarea in the DOM at all times and expand it with CSS, rather than mounting a new one after the tap. |
| Keyboard | Pin the app to `window.visualViewport` (height and offset) so the input rides above the keyboard; the page itself never scrolls. Treat a keyboard as closed when the visual viewport is within ~80px of the window height, which also guards the iOS 26 offset-not-resetting bug. Verify on a real iPhone, in the installed app and in Safari. |
| Errors | Not delivered: bubble stays, "Not delivered. Tap to retry" in Error. Couldn't reply (reply `status: failed`): centered "Donna couldn't reply." with Try again. Failed background jobs (`meta.failed_jobs` on the messages endpoints is above zero): a second strip above the input, styled like the offline one, until they are cleared. Offline (`navigator.onLine` false or a network error): a strip above the input; sends fail until it clears. Errors always have an icon and words. |
| Unsent messages and drafts | A message that fails to send is kept on the device (IndexedDB or `localStorage`) and shown as not delivered even after the app is closed and reopened; retrying it works as before. The text in the input is saved as a draft and restored, because iOS kills Home Screen apps freely. |
| Signed out mid-use | Any `401` (the 30-day idle timeout or the 90-day cap) sends the app to `/login` with the draft and any unsent messages kept; after signing in it returns to the chat. |
| Action buttons | When her reply carries an action (below), a button sits under her last bubble. Tapping runs it client-side and then shows "Done". |
| Sources sheet | Web citations first, then the memory items she was given (see Sources), capped at 8. |

The mockup shows a plus button and attachment chips; both wait for Files (Phase 3) and are not built here.

### API changes

All still under `/api/v1`, JSON, `auth:sanctum` (cookie session for the web app, tokens for the terminal).

| Change | Detail |
|---|---|
| `source` | Add `web` to `MessageSource`; `POST /messages` accepts `terminal` or `web`. |
| Idempotent sends | `POST /messages` takes an `Idempotency-Key` header (a client UUID). A repeat with the same key returns the original messages instead of creating new ones. Stored as a nullable unique `messages.client_key`. This is what makes "retry" safe when the first request actually landed and only the response was lost. |
| Retry a failed reply | `POST /messages/{reply}/retry` — only for Donna's `failed` replies: sets it `pending` and re-dispatches `GenerateReply`. |
| Polling for new messages | `GET /messages?after={id}` returns messages newer than `id`, oldest first, with `meta.next_after` when there are more than `limit`. The web app polls every 2s while a reply is pending and the tab is visible (that reply by `GET /messages/{id}`, plus `after` for anything new), every 10s while visible and idle, and not while hidden (push covers that). This is how a message sent from another device appears. |
| `bubbles` | `MessageResource` adds `bubbles: string[]`: the body split on lines that are exactly `---`, ignoring any inside a fenced code block, trimmed, empty ones dropped. A body with none returns one bubble. |
| `sources` | `MessageResource` adds `sources`: `[{type, label}]`, web citations (which also carry their `url`, so the sheet can link to them) plus the memory items the reply was given (below), capped at 8. Replaces nothing; `citations` stays. |
| `client_key` | `MessageResource` returns a message's `client_key`. A device whose send got no answer recognises the message when it later arrives from the server, and stops showing it as unsent. |
| `actions` | `MessageResource` adds `actions`: `[{type, label}]` from `meta.actions`. |
| `GET /me` | `{name, timezone, push: {configured, public_key}}`. The public VAPID key is what the browser subscribes with. Whether this device is subscribed is the client's own check (`PushManager.getSubscription()`), not the server's, since subscriptions are per device. |
| Push subscriptions | `POST /push-subscriptions` stores `{endpoint, keys}` for the signed-in device; `DELETE /push-subscriptions` `{endpoint}` removes it. An endpoint is only accepted if it belongs to a known push service (Apple, Google, Mozilla, Microsoft): the server posts to whatever address a subscription holds. |
| Presence | `DELETE /presence`: the app says it went to the background. See Push. |
| Sign-in | `POST /api/v1/auth/login-code` `{email}` — always answers 202 whether or not the email matches (it only ever matches Josh's), and sends the email if it does; 429 once throttled. `POST /auth/verify` `{email, code}` and `POST /auth/verify-link` `{token}` — start the session and answer with the same body as `GET /me`. `POST /auth/logout`, `POST /auth/logout-everywhere`, `POST /auth/revoke` (signed; from the new-sign-in email). The SPA fetches `/sanctum/csrf-cookie` first. |

**Mentions.** `@` in a message means "this is someone you should know". `MentionResolver` (plain code) finds tokens that start with `@` at the start of the message or after whitespace, followed by a name (letters, digits, `_`, `'` and `-` inside). It skips emails (`a@b.com`, where the `@` follows a word), scoped packages (`@vitejs/plugin-react`, followed by `/`) and anything inside code spans or fenced code. Each token is matched to People by name and alias, case-insensitively, with the same matcher the context already uses; if nobody is named exactly that, by first name (`@Sarah` for Sarah Lark). A possessive (`@Dave's`) is tried as written, then as `Dave`. `@Josh` and `@Donna` add nothing: both are always in context. Matched people always go in the memory block's People section. Unmatched tokens are listed there as "Josh mentioned @Dave with @, but you don't know who that is", and `operating-instructions.md` tells her: if it's someone in his life, call `save_person` now with what she can tell and ask who they are, in her own words; if it isn't a person (a GitHub handle, a package, a bot), leave it alone; if two people fit, ask which. So matching is code, and the judgment about what an unknown name is stays hers; no `People` row is made without her call, which keeps handles like `@dependabot` from becoming people. The text is stored as typed. Only people for now; projects and topics are matched by name already and may get `@` later. This is a Phase 1 context-builder change, small enough to ship here.

**Sources.** `ContextBuilder` already scores everything it puts in the memory block. When it builds a reply's context it also records, in `messages.meta.sources`, the top items by score (facts, messages, people, topics) as `{type, label}` where the label is the text a person would recognise (the fact's statement, the message's first line with its date). Facts and messages are ranked together by their retrieval score; people and topics have no score, so up to two of the eight places are kept for them. Code, no model call. The sheet is titled "What I looked at", not "What I used": these are what she was shown, not proof she relied on them.

**Reply formatting.** Update `resources/prompts/operating-instructions.md`: she is texting, so short and conversational by default; markdown is available for lists, inline code and fenced code; when a reply reads better as several short texts she separates them with a line containing only `---` (not inside code), and most replies stay one bubble. `body` is stored as written. The context builder keeps feeding `body` back to her as-is, so she sees her own pattern. Remove the "plain text" instruction from Phase 1.

**App actions.** A new tool, `app_action`, with `type` of `sign_out`, `sign_out_everywhere`, `enable_notifications` or `disable_notifications`. It does no work server-side except append `{type, label}` to `meta.actions` on the reply being written (held in memory and saved with the reply, so a reply that fails and is retried starts clean); her text explains what the button does. The label is fixed per type, in code. This is Graduated Autonomy's confirm-first: she can only offer, and Josh's tap does it. Browsers only allow the notification permission prompt from a tap, so the button is needed anyway.

**Slash commands**, handled in the client with no model call: `/signout`, `/notifications` (toggle), `/help` (lists them as a local, unsent message). They never reach the API. Anything else starting with `/` is sent as a normal message.

### Login and the account

- There is one account. `DONNA_USER_EMAIL` (exists) is the only address that can sign in; no password is stored or used (the `users.password` column keeps the random value the seeder generates).
- **Sign-in codes:** a `login_codes` table: `user_id`, `code_hash`, `link_token_hash`, `expires_at`, `used_at`, `attempts`. Asking for one creates a random 6-digit code and a 40-character link token (both stored hashed), valid for 10 minutes and usable once; asking again invalidates the old one. Five wrong guesses invalidate the code. Requests are throttled: 3 code requests per email per 10 minutes and 10 per IP per hour (so the address can't be mail-bombed), and 5 wrong guesses per code. `LoginCodeNotification` sends one email with the code and the link (`{APP_URL}/login/link/{token}`). Neither sign-in email goes on the queue, which can be busy for minutes writing a reply. The code is made and sent just after the 202 has gone back, so the answer takes the same time whether or not the address is Josh's; the new-sign-in notice is sent in the request. A send that fails is logged and nothing else changes. A wrong guess is counted before the code is checked, in one statement, so guesses sent at once can't beat the limit of five.
- **Fallback with no mail:** `php artisan donna:login` (run on the server) prints a fresh code and link. It is the way in until mail is set up, or if the provider is down.
- **Sessions:** Sanctum cookie sessions on the database driver. `SESSION_LIFETIME=43200` (30 days). Laravel's session lifetime is an idle timeout renewed by activity, so using the app keeps it alive and 30 days unused ends it. `SESSION_SECURE_COOKIE=true`, same-site lax, `SANCTUM_STATEFUL_DOMAINS=donna.joshevensen.com`. Signing in on a new device creates its own session. **Absolute cap:** a session also ends 90 days after it was created however much it's used (`DONNA_SESSION_MAX_DAYS`, default 90). The sign-in stamps `signed_in_at` into the session, and a small middleware on the stateful routes ends any session older than the cap. This bounds a stolen session that would otherwise be kept alive by use, since a sliding timeout only helps if the thief stops.
- **Sign out everywhere** deletes all of the user's session rows and push subscriptions. The terminal's tokens are left alone; revoke those from the server.
- **New-device email:** every successful sign-in sends `NewSignInNotification` to Josh: the device and browser (parsed locally with a few patterns, no package), the time, and the IP address, with a "Sign out everywhere" link. The link opens `/security/revoke/:token` in the app, a page with one button, so mail scanners that prefetch it don't trigger it; the button posts to `POST /auth/revoke` with the link's signature (a temporary signed URL, valid 7 days) and ends every session, so it works from any device, even one that isn't signed in. Checking the signature is code; there is no model call.
- **Later (Phases 4–5):** when Donna can take actions that can't be undone, those require a fresh emailed code first (step-up), on top of Graduated Autonomy's confirm-first. Not built in this phase.
- `PeopleSeeder` keeps the user's email in sync with `DONNA_USER_EMAIL`, looking the user up through Josh's Person rather than by email, so changing the variable doesn't create a second user.
- Sign-out ends that device's session and unsubscribes it from push.

### Push

- A `push_subscriptions` table: `user_id`, `endpoint` (unique), `public_key`, `auth_token`, `content_encoding`, `user_agent`, `last_used_at`. Dead endpoints (404/410 from the push service) are deleted on send. `user_agent` and `last_used_at` are written when a device subscribes and each time it opens the app (it re-sends its subscription); nothing reads them yet. They are there to tell devices apart when something needs looking into.
- A session that ends by itself (30 days unused, or the 90-day cap) leaves that device's subscription in place, so it still gets notifications and asks for a sign-in when opened. Only signing out, or signing out everywhere, removes it.
- A hand-written service worker (`public/sw.js`, so it's served from the root scope; no offline caching) handles `push` and `notificationclick` (focus the open window or open `/`). No app-shell caching: the app needs the network.
- **When to send:** when a reply completes and the app wasn't watching it. `GET /messages/{id}` and `GET /messages?after=` touch a short-lived cache key per user ("last polled"); if it was touched in the last 15 seconds the app is open and no push is sent. When the app is hidden or closed it calls `DELETE /presence`, which clears the key at once: otherwise a reply that finished within 15 seconds of Josh locking his phone would never notify. Code, not a model call. The notification is queued (on `default`), so a slow push service never touches the reply.
- **Known limit:** the "app is open" check is per user, not per device, so an open, visible tab on the Mac suppresses the phone's notification for those 15 seconds. Acceptable for one person; revisit if it bites.
- **Content:** title "Donna", body the reply's first bubble cut to about 140 characters. One push per reply, tagged so a retry replaces it. It shows on the lock screen; this is Josh's own device.
- **Turning it on:** conversational. "Turn on notifications" → she replies with an Enable button → the tap asks for browser permission and registers the subscription. It only works once the app is installed to the Home Screen on iOS 16.4+; if it isn't, the button says how to install it.
- VAPID keys in the environment (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`); `GET /me` reports whether they're configured.
- Presence nudges (Phase 7) reuse all of this.

### Security

- Cookies: `HttpOnly`, `Secure`, `SameSite=Lax`; Sanctum's CSRF protection on every state-changing request from the web app.
- Headers on the web app's responses (a small middleware): a Content-Security-Policy that only allows the app's own scripts, styles and images (no inline scripts, which Vite's build doesn't need), `frame-ancestors 'none'` and `X-Frame-Options: DENY` so it can't be embedded, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: same-origin` (nothing goes to other sites; it can't be `no-referrer`, because Sanctum recognises the app's own requests by their origin or referrer). The policy is left off while Vite's dev server is running, which needs inline scripts. Markdown never renders raw HTML.
- `POST /messages` is throttled (60 a minute) so a hijacked session can't run up the bill; the Anthropic key's monthly cap is the backstop.

### Data model changes

| Change | Detail |
|---|---|
| `messages.client_key` | nullable uuid, unique. |
| `messages.meta` | gains `sources` and `actions` (no new column). |
| `push_subscriptions` | new, as above. |
| `login_codes` | new, as above. The `sessions` table exists in the base Laravel migrations; confirm `SESSION_DRIVER=database`. |

### Code vs model in this phase

| Code | Model |
|---|---|
| Splitting a reply into bubbles | When to split, and her reply text (Sonnet 5.5, as now) |
| Sources: which context items are listed, and their labels | Understanding "sign me out" and "turn on notifications" and offering the button (part of the normal reply; no extra call) |
| Idempotency, retry, polling, pagination | — |
| Push: whether to send, subscriptions, payload | — |
| Slash commands, date labels, bubble grouping, markdown rendering | — |

No new kind of model call, and no new `costs` rows.

### Deployment and instructions

- **Deploy script:** after `composer install`, add `npm ci`, `npm run build` and `rm -rf node_modules` (the build tools aren't needed at run time, and Forge keeps several releases). Node 22 or newer on the server *(unverified; check Forge's Node version)*. This reverses the removal of the npm lines in Phase 1, so update [`forge-site.md`](../instructions/forge-site.md) with the new script and its failure modes.
- **New instruction files:** `mail.md` (Resend, a transactional email provider for sign-in codes, which are the only way in besides `donna:login`, so deliverability matters. It is used through its HTTPS API with Laravel's `resend` mailer and the `resend/resend-php` package, because DigitalOcean blocks the SMTP ports on droplets. DNS records for sending; the `MAIL_*` variables; and a check that `DONNA_USER_EMAIL` in production is an address Josh actually receives mail at) and `web-push.md` (generating VAPID keys, the variables, installing the app to the iPhone Home Screen, testing a notification). Add both to the README's first-time table.
- **Updated:** `environment-variables.md` (`VAPID_*`, `MAIL_*`, `SESSION_*`, `DONNA_SESSION_MAX_DAYS`, `SANCTUM_STATEFUL_DOMAINS`), `forge-site.md`, `verify-setup.md` (sign in, send a message, get a notification), `terminal-on-mac.md` and `blink-iphone.md` (now the fallback, not the main way), `operating.md`.
- `CLAUDE.md`: Layout (updated by the flatten: the repo is one Laravel app), the Backend and Web client rows, and Instructions. `design.md`'s "Landing page" section becomes the sign-in screen.
- The PR's "Before deploying" lists the instruction files touched, in order.

### Testing

Follow the Testing rules in the root `CLAUDE.md`. Fake every model, mail and push service.

**API (Pest feature tests):**
- Sign-in: asking for a code sends one email to Josh's address and nothing to any other (both answer 202); the code or the link starts a session once; it expires after 10 minutes, dies after five wrong guesses, and a new request invalidates the old one; throttling. With `Mail::fake()`. The cookie session can then use the message endpoints exactly as a token can; sign out everywhere ends every session. A session older than the 90-day cap is rejected even if it was used yesterday. Each successful sign-in sends the new-device email, and its revoke link ends every session only when the button's request is made (a plain GET of the link changes nothing).
- Idempotent send: the same key twice creates one pair of messages.
- Retry only works on a failed reply and re-dispatches the job.
- `after` returns only newer messages, oldest first.
- Bubble splitting (a unit test: it has real branching around code fences).
- Sources are recorded from the context a reply was built with.
- Mentions (a unit test: it's pure logic with real branching): `@Dave` is found at the start or after a space and not in an email, a scoped package or code; a matched mention puts the person in the context, an unmatched one is listed as unknown, two matches are flagged as ambiguous.
- `app_action` appends an action to the reply and does nothing else.
- Push: sent when the reply completes and the app wasn't polling, not sent when it was; dead endpoints are pruned.
- Seeder: email synced without creating a second user; `donna:login` prints a working code and link.

**Web (Vitest):** bubble grouping and corner radii, date labels in a timezone, the reveal queue for multi-bubble replies, markdown rendering (no raw HTML gets through), tested with React Testing Library where a component is the boundary, keyboard-viewport math, slash-command parsing. **Playwright with a mocked API:** send and receive, not delivered then retry, couldn't reply then try again, sign in. The keyboard behavior can't be tested without a device; it's a manual check listed in the PR.

### Risks to check early

- **The keyboard on iOS**, in Safari and in the installed Home Screen app. Both have known bugs (the installed-app viewport, and `visualViewport` offsets after the keyboard closes). Build this first and test it on Josh's phone before the rest.
- **Web Push on iOS** only works from an installed Home Screen app, and needs a real device to test.
- **Sign-in email** needs a mail provider and DNS before it can be tested for real. `donna:login` is the way in until then. Check that the code works typed into the installed iPhone app, and that opening the link from Mail on the phone does not strand you in Safari.
- **Build on the droplet:** `npm ci` and the Vite build run on the 2 GB / 1 vCPU server on every deploy. Watch memory and time on the first deploys; if it's too heavy, move the build to CI and ship the built assets another way.

### What changed while building

Small things the build added or changed, each for a reason found in the code. The sections above already say the same; this is the list in one place.

- **`GET /me` returns the public VAPID key**, and the sign-in endpoints answer with the same body, so the app has what it needs without a second request.
- **Messages return their `client_key`, and web sources their `url`.** The first lets a device drop an "unsent" message that had in fact arrived; the second makes cited pages tappable in the sheet.
- **`DELETE /presence`.** Without it, a quick reply after locking the phone was held back by the 15-second "app is open" window and never notified.
- **Push endpoints must belong to a known push service.** Otherwise a stolen session could make the server post to any address.
- **Sign-in emails never go on the queue; the reply notification does.** The first can't wait behind a long reply; the second must never hold one up.
- **"I already have a code" on the sign-in page.** A code from `donna:login` had no way in: the only route to the code field asked for an emailed code, which cancels the printed one. Found by the independent review.
- **`Referrer-Policy: same-origin`**, and no Content-Security-Policy while Vite's dev server is running.
- **Mentions match by first name too**, and handle possessives.
- **The input waits for a press to finish before it collapses.** Collapsing moves the thread; doing it mid-click made the click miss whatever was pressed (found by the "Try again" flow in Playwright).
- **Unsent messages:** one that failed for lack of a connection goes out by itself when the connection returns (as the offline strip says); one that was mid-send when the app closed is sent again on reopening. Both are safe because of the idempotency key. Anything else waits for a tap.
- **Small things kept on the device:** who is signed in (so the app opens straight to the chat), a sign-in that is waiting for its code (so returning from Mail lands on the code field), and which of Donna's buttons were used.
- **Retrying anything but a failed reply answers 409**, and retries share the 60-a-minute limit with sends.
- **`donna:chat` shows a multi-bubble reply as paragraphs**, without the `---` lines.
- **`composer dev` listens on the `replies` queue**, so replies arrive when working locally.
- **Mail is Resend**, and the deploy script removes `node_modules` after the build.
- **The flatten was already on `main`** when the build started, so the PR's diff doesn't show it; the Forge change it needs is still the first step before deploying.

### Not in this phase

Autocomplete for `@` and `/`, attachments and the plus button, Projects, ratings, search and cost screens, a menu, streaming, a native app, Presence, websockets, offline support, and passwords, passkeys and biometrics.
