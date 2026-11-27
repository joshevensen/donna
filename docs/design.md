# Design

How Donna looks and feels. [`vision.md`](vision.md) (UI section) says what the interface is — chat, dark mode only; this doc says what it's made of. Phase docs reference this for color, type, and components rather than redefining them, and put their own mockups in `docs/tech/phase-N.md`.

Status: foundation. Color, icon, and principles are settled (the palette was reworked before Phase 2: dark neutrals and a purple icon); type, spacing, and component specs are first drafts to be firmed up in the phase doc that first builds the UI (Phase 2).

---

## Principles

- **Dark only** — one theme, no toggle, no light variant. The web app, the native apps and the landing page all declare dark.
- **Chat is the interface** — the design is a quiet frame around a conversation. Chrome is minimal; color is used for meaning, not decoration.
- **Dark neutrals, one purple** — surfaces are a ramp of warm near-blacks. Purple is the brand and the single interactive color, so it appears in small doses: the send button, the selected marker. If a screen looks purple, something is overusing it.
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
| 4 | Raised | `#2D2223` | Donna's chat bubbles, cards, the input field, sheets |
| 5 | Hover | `#3A2D2E` | Hover and pressed backgrounds, the selected row, borders on Raised |

### Purple — the brand and the interactive color

| Name | Hex | Role |
|---|---|---|
| Accent | `#7C3AED` | The icon's background, and the single interactive color, used in small amounts: the send button, the selected-item marker, the Active dot. Never a large surface inside the app. |
| Lapel | `#5B21B6` | The icon's lapels only. |

### Error

| Name | Hex | Role |
|---|---|---|
| Error | `#E05A55` | Error borders and icons only, always with a message. The one non-brand hue; purple can't mean "something went wrong". |

### Neutrals — text

Two colors come straight from the icon (shirt and tie).

| Name | Hex | Role |
|---|---|---|
| Shirt | `#F4EBE1` | Primary text and icons; Josh's chat bubbles; primary buttons (with Tie text) |
| Tie | `#1E1718` | Text on Shirt-colored fills |

Secondary text is Shirt at 65% opacity; disabled is 35%. Don't introduce a pure white or pure black.

### Layering

Canvas (1) → Inset (2) → Panel (3) → Raised (4) → Hover (5). Accent is never a surface.

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

The Donna icon is a suit from the front: a Shirt-colored V with a Tie-colored knot, framed by Lapel (`#5B21B6`) lapels, on an Accent (`#7C3AED`) background. Source of truth is [`api/public/favicon.svg`](../api/public/favicon.svg) (512 × 512 viewBox, square, full-bleed).

- **Full-bleed square** — no baked-in rounded corners or padding. iOS, iPadOS, and macOS apply their own mask; the landing page rounds it with CSS.
- **Derived files** — `favicon.ico` (16/32/48) and `apple-touch-icon.png` (180) are rasterized from the SVG. Regenerate them whenever the SVG changes; never edit them by hand.
- **App icons** — Xcode's 1024 × 1024 App Icon asset is exported from the same SVG when the apple project is created. The watchOS and Home Node apps use the same icon.
- **Don't** — recolor it, add a wordmark inside it, or place it on a light background.

### Landing page

`api/public/index.html` shows the icon centered on a Canvas background and nothing else. It has no route or view; the API stays pure JSON.

---

## Typography

- **System fonts** — `ui-sans-serif` stack on the web (renders as SF Pro on Apple devices), SF Pro in the native apps. No custom typefaces to ship or license.
- **Scalable text** — web: `rem` sizes and no fixed pixel text, so the browser's text size is respected; native: Dynamic Type text styles.
- **Monospace** (SF Mono) for code, file paths, and CSV/edit views.
- Hierarchy comes from weight and opacity (Shirt 100% / 65% / 35%), not extra colors.

---

## Components

First drafts, tried out in the Phase 2 mockup; see vision UI section for behavior.

- **Chat history** — one continuous conversation on Canvas, texting-style. Josh's bubbles are Raised with Shirt text, on the right; Donna's are Accent purple with Shirt text, on the left. Consecutive bubbles from one sender sit 2px apart and only the last has the tail. Donna usually answers in one bubble and may split a reply into several when the content calls for it, each preceded by the typing dots.
- **Time and sources** — nothing is printed under bubbles. Tapping any bubble shows its time; on Donna's bubbles the same line has "N sources", which opens a bottom sheet of what she used. A small centered time (for example "Today 9:41 AM") appears only after a gap of an hour or more and at day changes. There are no ratings.
- **No header** — the Donna icon floats, centered, over the top of the messages, with a fade so text scrolls cleanly beneath it. There is no title bar and no menu button: sign-out, search, cost and notification settings all happen by talking to her.
- **Chat input** — fixed to the bottom, a Raised field, three lines tall and growing to six, then scrolling. Send is the one Accent button. Enter makes a new line on the phone. The focus ring is Shirt.
- **Typing indicator** — three dots in a Donna bubble that bounce in turn (a deliberate exception to the quiet principle; reduced-motion users get static dots). No progress messages and no "thinking" text: it should feel like texting, not like watching a tool work.
- **Not delivered** — if a message fails to send or she can't answer, the bubble stays with "Not delivered. Tap to retry" under it in Error. The input stays usable.
- **Buttons** — primary is Shirt with Tie text; secondary is transparent with Shirt text. Accent fills are reserved for Donna's bubbles and send.
- **Code blocks** — Canvas-colored inside Donna's bubble, with a monospace font, a language label and a copy button.
- **File viewer** — Panel. Visual mode renders on Panel; edit mode is visually distinct (Inset, monospace) so Josh always knows which he's in. Metadata is expandable at the bottom.
- **Quick capture** — a single Raised field that gets out of the way; same tokens as chat input.
- **Notifications** — system styling only; Donna's icon and plain text.

### Platform layout

- **iPhone (web, installed to the Home Screen)** — the chat fills the screen under a floating icon. This is the screen the Phase 2 mockup designs.
- **Mac (web)** — the same single chat in a centered column; not yet mocked up.
- **Native apps (later)** — the same chat in SwiftUI. **Watch** is capture only: dictation, then a confirmation, on Canvas with Shirt text.

---

## Decisions to make with the first UI phase

- Final type scale and spacing grid
- Whether error/success need dedicated hues (see Status colors)
- Motion: default to system animations, nothing decorative
- Haptics and sound for capture confirmation
- Mockups (live in the phase doc, approved by Josh before building)
