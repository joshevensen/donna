# Phase 2 — Web chat

Donna gets a front end: a chat Josh opens from an icon on his iPhone's Home Screen or a tab on his Mac, that feels like texting a person. Scope is in [`phases.md`](../phases.md); look and feel in [`design.md`](../design.md); concepts in [`domain.md`](../domain.md). Phase 1's API and memory are unchanged except where this doc says.

**Status:** draft, waiting for Josh's approval.

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

### When things go wrong

- If a message doesn't send, it stays in the thread marked "Not delivered. Tap to retry." Retrying never sends it twice.
- If Donna couldn't answer, the thread says so and I can tell her to try again.
- If I'm offline, a strip says so, and anything I send is marked not delivered until I'm back.

### Signing in

- I sign in with my email and password. I stay signed in on each device until I sign out.
- If I forget my password, I can reset it from a link emailed to me.
- I can sign out, and turn notifications on or off, by telling Donna ("sign me out", "turn on notifications") and tapping the button she gives me, or with `/signout` and `/notifications`.

### Not in this phase

- Attachments and the plus button (Files, Phase 6)
- A projects page, ratings, search, a cost page, or any menu. I ask Donna instead. (Phase 1's rating endpoint stays in the API, unused.)
- Streaming a reply word by word. Replies arrive as whole bubbles.
- A Mac-specific layout beyond the centered column, the native app, and Presence nudges (Phase 7)
- A sign-in-by-link or biometric login

---

## Design

Where this section is silent, follow Laravel and Vue best practice, the root and `api/` `CLAUDE.md` files and the Boost guidelines, and note notable choices in the PR. If something here turns out to be wrong once you're in the code, deviate, say why in the PR, and update this doc in the same PR.

### Delivery

- Build on a branch with a draft PR against `main` and a checklist from this doc, ticked per push, as `CLAUDE.md` says. Never merge.
- API in Pest feature tests, web in Vitest and a few Playwright flows, Pint and the full suite green. CI gains a web job.
- Every change that needs something outside the repo adds or updates a file in [`docs/instructions/`](../instructions/README.md) in the same PR (listed under Deployment).

### What's decided and why

| Decision | Choice | Reason |
|---|---|---|
| Web stack | Vue 3 (`<script setup>`, TypeScript), Vite, Vue Router, no UI kit | Three routes and one real screen; the chat needs fine control of scroll, textarea height and the keyboard, which a UI kit would fight. Vue is the Laravel-community default. |
| Where it lives | New `web/` folder, built by Vite into `api/public/app`, served from the same origin as the API | Same origin means no CORS and Sanctum's cookie login for the SPA. |
| Login | Laravel Fortify (headless: login, logout, forgot and reset password only) + Sanctum cookie sessions | First-party, includes throttling and the reset-email flow; hand-rolling these is how auth bugs happen. The terminal keeps its tokens. |
| Reply bubbles | Donna separates bubbles in her reply with a line containing only `---`; code splits it | Deciding when to split is judgment (hers); splitting is code. No schema change. |
| Live updates | Polling, no websockets | One user. Revisit if it feels laggy. |
| Push | Web Push via `laravel-notification-channels/webpush` *(confirm the package is current before using it)* | Standard VAPID Web Push; works for iOS Home Screen web apps (iOS 16.4+). |

### The web app (`web/`)

- Vue 3 + Vite + TypeScript, Vue Router with three routes: `/` (chat), `/login`, `/reset-password/:token`, plus `/forgot-password`. Composables for state; no Pinia.
- **Dev:** `npm run dev` with Vite proxying `/api` and `/sanctum` to the API. **Build:** `npm run build` writes `api/public/app`.
- **Served by Laravel:** a `fallback` route returns `public/app/index.html` for any GET that isn't `/api/*` (and the static assets are served by Nginx). This is the one web route in the app; update the "pure JSON" note in `CLAUDE.md` and delete the old `public/index.html` landing page (the app replaces it, and signed-out visitors land on `/login`).
- **Installable:** a web app manifest (name Donna, `display: standalone`, background Canvas `#0F0B0B`, the icon at 192/512 and the 180 touch icon) and `theme-color` Canvas. Icons are exported from `api/public/favicon.svg` per `design.md`.
- **Markdown:** replies render with `markdown-it` with raw HTML disabled: paragraphs, lists, inline code, fenced code (monospace, no highlighting, copy button), links open in a new tab. Josh's own messages render as plain text.
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
| Input | Collapsed (slim bar: plus is **not** shown in this phase, a "Message Donna" tap target, disabled send) when empty and unfocused. Tapping opens a field three lines tall that grows to six, then scrolls (measure the real height, not character counts). |
| Keyboard | Pin the app to `window.visualViewport` (height and offset) so the input rides above the keyboard; the page itself never scrolls. Treat a keyboard as closed when the visual viewport is within ~80px of the window height, which also guards the iOS 26 offset-not-resetting bug. Verify on a real iPhone, in the installed app and in Safari. |
| Errors | Not delivered: bubble stays, "Not delivered. Tap to retry" in Error. Couldn't reply (reply `status: failed`): centered "Donna couldn't reply." with Try again. Offline (`navigator.onLine` false or a network error): a strip above the input; sends fail until it clears. Errors always have an icon and words. |
| Action buttons | When her reply carries an action (below), a button sits under her last bubble. Tapping runs it client-side and then shows "Done". |
| Sources sheet | Web citations first, then the memory items she was given (see Sources), capped at 8. |

The mockup shows a plus button and attachment chips; both wait for Files (Phase 6) and are not built here.

### API changes

All still under `/api/v1`, JSON, `auth:sanctum` (cookie session for the web app, tokens for the terminal).

| Change | Detail |
|---|---|
| `source` | Add `web` to `MessageSource`; `POST /messages` accepts `terminal` or `web`. |
| Idempotent sends | `POST /messages` takes an `Idempotency-Key` header (a client UUID). A repeat with the same key returns the original messages instead of creating new ones. Stored as a nullable unique `messages.client_key`. This is what makes "retry" safe when the first request actually landed and only the response was lost. |
| Retry a failed reply | `POST /messages/{reply}/retry` — only for Donna's `failed` replies: sets it `pending` and re-dispatches `GenerateReply`. |
| Polling for new messages | `GET /messages?after={id}` returns messages newer than `id`, oldest first. The web app polls every 2s while a reply is pending and the tab is visible, every 10s while visible and idle, and not while hidden (push covers that). This is how a message sent from another device appears. |
| `bubbles` | `MessageResource` adds `bubbles: string[]`: the body split on lines that are exactly `---`, ignoring any inside a fenced code block, trimmed, empty ones dropped. A body with none returns one bubble. |
| `sources` | `MessageResource` adds `sources`: `[{type, label}]`, web citations plus the memory items the reply was given (below). Replaces nothing; `citations` stays. |
| `actions` | `MessageResource` adds `actions`: `[{type, label}]` from `meta.actions`. |
| `GET /me` | `{name, timezone, push: {configured, subscribed}}`. |
| Push subscriptions | `POST /push-subscriptions` stores `{endpoint, keys}` for the signed-in device; `DELETE /push-subscriptions` removes it. |
| Auth (Fortify) | `POST /api/v1/auth/login`, `/logout`, `/forgot-password`, `/reset-password`. Registration, email verification, two-factor and profile features are off. Login is throttled (5 attempts a minute per email and IP). The reset link in the email points at `{APP_URL}/reset-password/{token}?email=…`. The SPA fetches `/sanctum/csrf-cookie` before logging in. |

**Sources.** `ContextBuilder` already scores everything it puts in the memory block. When it builds a reply's context it also records, in `messages.meta.sources`, the top items by score (facts, messages, people, topics) as `{type, label}` where the label is the text a person would recognise (the fact's statement, the message's first line with its date). Code, no model call. The sheet is titled "What I looked at", not "What I used": these are what she was shown, not proof she relied on them.

**Reply formatting.** Update `api/resources/prompts/operating-instructions.md`: she is texting, so short and conversational by default; markdown is available for lists, inline code and fenced code; when a reply reads better as several short texts she separates them with a line containing only `---` (not inside code), and most replies stay one bubble. `body` is stored as written. The context builder keeps feeding `body` back to her as-is, so she sees her own pattern. Remove the "plain text" instruction from Phase 1.

**App actions.** A new tool, `app_action`, with `type` of `sign_out`, `enable_notifications` or `disable_notifications`. It does no work server-side except append `{type, label}` to `meta.actions` on the reply being written; her text explains what the button does. This is Graduated Autonomy's confirm-first: she can only offer, and Josh's tap does it. Browsers only allow the notification permission prompt from a tap, so the button is needed anyway.

**Slash commands**, handled in the client with no model call: `/signout`, `/notifications` (toggle), `/help` (lists them as a local, unsent message). They never reach the API. Anything else starting with `/` is sent as a normal message.

### Login and the account

- Josh's credentials come from the environment: `DONNA_USER_EMAIL` (exists) and a new `DONNA_USER_PASSWORD`.
- `PeopleSeeder` uses the password **only when it creates the user**, never on later runs (`db:seed` runs on every deploy, so anything else would overwrite a password changed through the reset page). It also keeps the existing user's email in sync with `DONNA_USER_EMAIL`, looking the user up through Josh's Person rather than by email, so changing the variable doesn't create a second user.
- The deployed account already exists with a random password, so `DONNA_USER_PASSWORD` can't set it. A new server command, `php artisan donna:password` (prompts, hides input), sets it, and works with no mail configured. The reset page covers the rest.
- Sessions are database-backed, stay signed in until he signs out (remember-me always on), `SESSION_LIFETIME` of a year, `SESSION_SECURE_COOKIE=true`, same-site lax, `SANCTUM_STATEFUL_DOMAINS=donna.joshevensen.com`.
- Sign-out ends the session and unsubscribes that device from push.

### Push

- A `push_subscriptions` table: `user_id`, `endpoint` (unique), `public_key`, `auth_token`, `content_encoding`, `user_agent`, `last_used_at`. Dead endpoints (404/410 from the push service) are deleted on send.
- A hand-written service worker (`web/public/sw.js`, no offline caching) handles `push` and `notificationclick` (focus the open window or open `/`). No app-shell caching: the app needs the network.
- **When to send:** when a reply completes and the app wasn't watching it. `GET /messages/{id}` and `GET /messages?after=` touch a short-lived cache key per user ("last polled"); if it was touched in the last 15 seconds the app is open and no push is sent. Code, not a model call.
- **Content:** title "Donna", body the reply's first bubble cut to about 140 characters. One push per reply, tagged so a retry replaces it. It shows on the lock screen; this is Josh's own device.
- **Turning it on:** conversational. "Turn on notifications" → she replies with an Enable button → the tap asks for browser permission and registers the subscription. It only works once the app is installed to the Home Screen on iOS 16.4+; if it isn't, the button says how to install it.
- VAPID keys in the environment (`VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`); `GET /me` reports whether they're configured.
- Presence nudges (Phase 7) reuse all of this.

### Data model changes

| Change | Detail |
|---|---|
| `messages.client_key` | nullable uuid, unique. |
| `messages.meta` | gains `sources` and `actions` (no new column). |
| `push_subscriptions` | new, as above. |
| Sessions table | exists in the base Laravel migrations; confirm `SESSION_DRIVER=database`. |

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

- **Deploy script:** after `composer install`, add `cd web && npm ci && npm run build` (output lands in `api/public/app`). Node 22 or newer on the server *(unverified; check Forge's Node version)*. This reverses the removal of the npm lines in Phase 1, so update [`forge-site.md`](../instructions/forge-site.md) with the new script and its failure modes.
- **New instruction files:** `mail.md` (a transactional email provider for password reset: Postmark or Resend, both have first-party Laravel mail drivers, pick one when writing it; DNS records for sending; the `MAIL_*` variables) and `web-push.md` (generating VAPID keys, the variables, installing the app to the iPhone Home Screen, testing a notification). Add both to the README's first-time table.
- **Updated:** `environment-variables.md` (`DONNA_USER_PASSWORD`, `VAPID_*`, `MAIL_*`, `SESSION_*`, `SANCTUM_STATEFUL_DOMAINS`), `forge-site.md`, `verify-setup.md` (sign in, send a message, get a notification), `terminal-on-mac.md` and `blink-iphone.md` (now the fallback, not the main way), `operating.md`.
- `CLAUDE.md`: Layout (`web/`, the one web route), Stack row for the web client (now decided), and Instructions.
- The PR's "Before deploying" lists the instruction files touched, in order.

### Testing

Follow the Testing rules in the root `CLAUDE.md`. Fake every model, mail and push service.

**API (Pest feature tests):**
- Login, logout, and the reset flow (forgot → mail → reset), throttling, with `Mail::fake()`; the cookie session can then use the message endpoints exactly as a token can.
- Idempotent send: the same key twice creates one pair of messages.
- Retry only works on a failed reply and re-dispatches the job.
- `after` returns only newer messages, oldest first.
- Bubble splitting (a unit test: it has real branching around code fences).
- Sources are recorded from the context a reply was built with.
- `app_action` appends an action to the reply and does nothing else.
- Push: sent when the reply completes and the app wasn't polling, not sent when it was; dead endpoints are pruned.
- Seeder: password set only at creation, email synced without creating a second user; `donna:password`.

**Web (Vitest):** bubble grouping and corner radii, date labels in a timezone, the reveal queue for multi-bubble replies, markdown rendering (no raw HTML gets through), keyboard-viewport math, slash-command parsing. **Playwright with a mocked API:** send and receive, not delivered then retry, couldn't reply then try again, sign in. The keyboard behavior can't be tested without a device; it's a manual check listed in the PR.

### Risks to check early

- **The keyboard on iOS**, in Safari and in the installed Home Screen app. Both have known bugs (the installed-app viewport, and `visualViewport` offsets after the keyboard closes). Build this first and test it on Josh's phone before the rest.
- **Web Push on iOS** only works from an installed Home Screen app, and needs a real device to test.
- **Password reset email** needs a mail provider and DNS before it can be tested for real. `donna:password` is the way in until then.
- **`laravel-notification-channels/webpush`** is assumed to be maintained and Laravel 13 compatible; check before depending on it, and fall back to `minishlink/web-push` directly if not.

### Not in this phase

Attachments and the plus button, Projects, ratings, search and cost screens, a menu, streaming, a native app, Presence, websockets, offline support, and sign-in by link or biometrics.
