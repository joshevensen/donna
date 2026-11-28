# Design

How Donna looks and feels. [`vision.md`](vision.md) (UI section) says what the interface is — chat, dark mode only; this doc says what it's made of. Phase docs reference this for color, type, and components rather than redefining them, and put their own mockups in `docs/tech/phase-N.md`.

Status: color, icon, and principles are settled (the palette was reworked before Phase 2: dark neutrals and a purple icon). The chat, its input, errors and the sign-in screen were built in Phase 2 ([`tech/phase-2.md`](tech/phase-2.md)), and the web app's stylesheet, `resources/css/app.css`, is where their exact sizes live. Attachments, file cards and the file viewer were built in Phase 3 ([`tech/phase-3.md`](tech/phase-3.md)). The components nothing has built yet (quick capture, the native apps) are still first drafts.

---

## Principles

- **Dark only** — one theme, no toggle, no light variant. The web app and the native apps all declare dark.
- **Chat is the interface** — the design is a quiet frame around a conversation. Chrome is minimal; color is used for meaning, not decoration.
- **Dark neutrals, one purple** — surfaces are a ramp of warm near-blacks. Purple is the brand and the single interactive color: Donna's bubbles, the send button, the selected marker. Nothing else is purple, so in a conversation purple means her.
- **Doesn't beg for attention** — no badges that count up, no streaks, no decorative motion. Donna is measured by how much she helps, not how much she's used (see vision, Presence).
- **Tailored, not flashy** — the icon is a suit and tie; the app should feel the same: deep, restrained, well-fitted.
- **Web first, native later** — the web app is the first front end (see vision, UI). It uses plain HTML and CSS with system fonts and the tokens below. When the native apps arrive they use SwiftUI system components, Dynamic Type and platform conventions, with the same tokens.

---

## Color

Two groups: a ramp of five dark neutrals for surfaces, and one bright purple for the things you act on. The purple is the icon's color.

### Ink — surfaces

Warm near-blacks, tinted a few degrees toward red so they don't read as cold gray. Numbers run dark to light; surfaces get lighter as they come forward.

| # | Name | Hex | Role |
|---|---|---|---|
| 1 | Canvas | `#0F0B0B` | Page and window background — what everything sits on |
| 2 | Inset | `#1A1414` | Hairlines and separators on Canvas; recessed fills (code blocks, table stripes, error banner) |
| 3 | Panel | `#1F1718` | Sidebar, top bar, input bar, the project detail pane |
| 4 | Raised | `#2D2223` | Josh's chat bubbles, cards, the input field, items in a sheet |
| 5 | Hover | `#3A2D2E` | Hover and pressed backgrounds, the selected row, borders on Raised |

### Purple — the brand and the interactive color

| Name | Hex | Role |
|---|---|---|
| Accent | `#7C3AED` | The icon's background, Donna's chat bubbles, and the single interactive color: the send button, the selected-item marker, the Active dot. Never a page or panel background. |
| Lapel | `#5B21B6` | The icon's lapels only. |

### Error

| Name | Hex | Role |
|---|---|---|
| Error | `#E05A55` | Error borders and icons only, always with a message. The one non-brand hue; purple can't mean "something went wrong". |

### Neutrals — text

Two colors come straight from the icon (shirt and tie).

| Name | Hex | Role |
|---|---|---|
| Shirt | `#F4EBE1` | Primary text and icons, on every surface including both kinds of bubble; primary buttons (with Tie text) |
| Tie | `#1E1718` | Text on Shirt-colored fills |

Secondary text is Shirt at 65% opacity; disabled is 35%. Don't introduce a pure white or pure black.

### Layering

Canvas (1) → Inset (2) → Panel (3) → Raised (4) → Hover (5). Accent is not a step in the ramp: it is Donna's bubbles and the things you press.

### Contrast

Measured (WCAG):

| On | Shirt | Shirt 65% | Shirt 35% | Accent | Error |
|---|---|---|---|---|---|
| Canvas `#0F0B0B` | 16.6 : 1 | 7.3 : 1 | 2.8 : 1 | 3.4 : 1 | 5.4 : 1 |
| Inset `#1A1414` | 15.4 : 1 | 7.1 : 1 | 2.9 : 1 | 3.2 : 1 | 5.0 : 1 |
| Panel `#1F1718` | 14.9 : 1 | 6.9 : 1 | 2.9 : 1 | 3.1 : 1 | 4.8 : 1 |
| Raised `#2D2223` | 13.1 : 1 | 6.4 : 1 | 2.9 : 1 | 2.7 : 1 | 4.2 : 1 |
| Hover `#3A2D2E` | 11.2 : 1 | 5.7 : 1 | 2.7 : 1 | 2.3 : 1 | 3.6 : 1 |

Tie on Shirt is 15.0 : 1, and Shirt on Accent is 4.8 : 1 (passes AA for labels). All body text passes AA. Disabled text (35%) is exempt.

Accent clears 3 : 1 on Canvas, Inset and Panel but not on Raised or Hover, so it is **never body text** and never the only signal there; pair it with a shape or a word. **Focus rings are Shirt**, which works on every surface. The steps between surfaces are small (adjacent surfaces differ by 1.03 to 1.17 : 1), so check on a real screen and widen one here if it disappears.

### Status colors

An error is Error-colored (a border, an icon and a message), matching the vision: input disabled, border red, message explains. Success and warning get no new hues; use an icon and text. If Phase 2 shows this isn't enough, add one muted amber and one muted green here, not ad hoc in the app.

### Tokens

Same names on every platform. Web: CSS custom properties. Swift: an asset catalog color set per token (single appearance, dark).

```
--donna-canvas: #0F0B0B;
--donna-inset: #1A1414;
--donna-panel: #1F1718;
--donna-raised: #2D2223;
--donna-hover: #3A2D2E;
--donna-accent: #7C3AED;
--donna-lapel: #5B21B6;   /* icon only */
--donna-error: #E05A55;
--donna-shirt: #F4EBE1;
--donna-tie: #1E1718;
```

---

## Icon

The Donna icon is a suit from the front: a Shirt-colored V with a Tie-colored knot, framed by Lapel (`#5B21B6`) lapels, on an Accent (`#7C3AED`) background. Source of truth is [`public/favicon.svg`](../public/favicon.svg) (512 × 512 viewBox, square, full-bleed).

- **Full-bleed square** — no baked-in rounded corners or padding. iOS, iPadOS, and macOS apply their own mask; the sign-in screen rounds it with CSS.
- **Derived files** — `favicon.ico` (16/32/48), `apple-touch-icon.png` (180), and `icon-192.png` and `icon-512.png` (the web app manifest's icons) are rasterized from the SVG. Regenerate them whenever the SVG changes; never edit them by hand.
- **App icons** — Xcode's 1024 × 1024 App Icon asset is exported from the same SVG when the native app is created. The watchOS and Home Node apps use the same icon.
- **Don't** — recolor it, add a wordmark inside it, or place it on a light background.

### Sign-in screen

Anyone who isn't signed in sees the icon centered on Canvas, rounded like an app icon, with one line of text and one field under it: the email address, then the 6-digit code. The primary button is Shirt with Tie text. The two pages that arrive from an email (the sign-in link, and "sign out everywhere") use the same frame with a single button. This replaced the old static landing page.

---

## Typography

- **System fonts** — `ui-sans-serif` stack on the web (renders as SF Pro on Apple devices), SF Pro in the native apps. No custom typefaces to ship or license.
- **Scalable text** — web: `rem` sizes and no fixed pixel text, so the browser's text size is respected; native: Dynamic Type text styles.
- **Monospace** (SF Mono) for code, file paths, and CSV/edit views.
- Hierarchy comes from weight and opacity (Shirt 100% / 65% / 35%), not extra colors.

---

## Components

The chat components (Phase 2) and the file components (Phase 3) below are built; quick capture and notifications are first drafts. See vision UI section for behavior.

- **Chat history** — one continuous conversation on Canvas, texting-style. Josh's bubbles are Raised with Shirt text, on the right; Donna's are Accent purple with Shirt text, on the left. Every corner is round (20px) except where consecutive bubbles from one sender meet: those sit 2px apart with square inner corners, so a run reads as one bubble cut by thin lines. Runs are 10px apart. Donna usually answers in one bubble and may split a reply into several when the content calls for it, each preceded by the typing dots.
- **Time and sources** — nothing is printed under bubbles. Tapping any bubble shows its time; on Donna's bubbles the same line has "N sources", which opens a bottom sheet of what she used. A small centered date ("Today", "Yesterday", or "Tuesday, Nov 24" for older days, with no time) marks each new day. There are no ratings.
- **No header** — no title bar, no icon, no menu button: the messages run to the top of the screen. Sign-out, search, cost and notification settings all happen by talking to her.
- **Chat input** — fixed to the bottom, full width. When it's empty and unfocused it collapses to a slim bar, so more of the conversation shows. Tapping it opens a Raised field three lines tall that grows to six, then scrolls. Under the text runs a bar of three columns: the plus (attach) at the left, today's and this month's spend centered (midnight in Josh's timezone), and a 30×30 send button at the right, so the plus sits opposite send. Send is the one Accent button; the plus is Hover. Enter makes a new line on the phone; with a keyboard and mouse it sends, and Shift+Enter is the new line. The focus ring is Shirt.
- **Action buttons** — when Donna offers to do something only the app can do (sign out, turn notifications on), a Shirt button sits under her reply. Tapping it does it and the button reads "Done".
- **Attachments** — the plus opens a bottom sheet (Choose a file, Choose a photo, Cancel). Chosen files upload at once and sit as chips above the text, inside the field, each with a remove button: a thin Shirt line along the bottom while it uploads, and an Error border with the reason when it is refused ("Unsupported. Text, PDF and images only", "Too large. Max 10 MB"). Send waits until every chip is ready, and a line under the field says why. A message may be only chips.
- **Typing indicator** — three dots in a Donna bubble that bounce in turn (a deliberate exception to the quiet principle; reduced-motion users get static dots). No progress messages and no "thinking" text: it should feel like texting, not like watching a tool work.
- **Errors** — all in Error red with an icon and words, never color alone, and the input stays usable. A message that didn't send keeps its bubble with "Not delivered. Tap to retry" under it. If she couldn't answer, a centered line says "Donna couldn't reply." with Try again. While offline a slim strip above the input says messages will send when you're back. A bad attachment's chip explains itself.
- **Buttons** — primary is Shirt with Tie text; secondary is transparent with Shirt text. Accent fills are reserved for Donna's bubbles and send.
- **Code blocks** — Canvas-colored inside Donna's bubble, with a monospace font, a language label and a copy button. No syntax coloring. Josh's own fenced blocks (triple backticks) get the same treatment in his bubbles; the rest of what he types is never read as markdown.
- **File cards** — a file is one small card wherever it appears: an icon, the name, and a second line saying what it is ("CSV · 1,284 rows · 48 KB") or what happened to it ("Edited · Replaced 14 matches of …"). Sent files sit at the top of Josh's bubble, on Canvas at 45%; an image shows a thumbnail. Files Donna created, edited or opened sit under her reply on Raised. A kept file's card carries a small outlined **Kept** badge, everywhere, following the keep switch. Tapping a card opens the viewer. `/files` lists the cards in one of Donna's bubbles under "Kept" and "Recent, not kept".
- **File viewer** — Panel. On the phone a full-screen sheet over the chat; from 768px a side panel that takes the right half, with an Inset hairline, while the chat keeps its centred column in the left half. Header: the name, the primary action (Edit for markdown, a "Read-only" label for CSV and text, Open original for a PDF or image) and Close; under it the keep switch (Accent when on) with a line saying what it means. Body by kind: rendered markdown, a CSV as a table with a sticky Inset header and striped rows, a PDF as the text Donna reads in monospace under "Page N" labels, text in monospace, an image. Edit mode is visually distinct so Josh always knows which he's in: the whole viewer goes Inset, the header becomes Cancel, the name over "Editing", and Save, and the body is one monospace textarea. A refused save shows an Error-bordered banner with "Keep mine" and "Take Donna's". Details (added, modified, size, last edited by, version, download) expand at the bottom.
- **Quick capture** — a single Raised field that gets out of the way; same tokens as chat input.
- **Notifications** — system styling only; Donna's icon and plain text.

### Platform layout

- **iPhone (web, installed to the Home Screen)** — the chat fills the screen, with the input pinned to the bottom and riding above the keyboard. This is the screen the Phase 2 mockup designs. An open file covers it as a full-screen sheet.
- **Mac (web)** — the same single chat in a centered column, 600px at most, from 768px wide. With a file open the window splits in two: the chat's column on the left, the file on the right (Phase 3 mockup, board 8).
- **Native apps (later)** — the same chat in SwiftUI. **Watch** is capture only: dictation, then a confirmation, on Canvas with Shirt text.

---

## Decisions

Settled by Phase 2:

- **Type scale** — 1rem (16px) for messages and inputs, which also stops iOS zooming on focus; 0.8125rem for errors and notes; 0.75rem for dates, times and labels. Weight and opacity carry the hierarchy.
- **Status hues** — none added. Error red with an icon and words has been enough.
- **Motion** — the typing dots, and nothing else.
- **Mockups** — live with the phase doc, approved by Josh before building.

Still open:

- A spacing grid, once there is more than one screen to keep consistent
- Haptics and sound for capture confirmation (native apps)
