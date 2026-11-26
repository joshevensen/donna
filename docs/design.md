# Design

How Donna looks and feels. [`vision.md`](vision.md) (UI section) says what the interface is — chat, dark mode only; this doc says what it's made of. Phase docs reference this for color, type, and components rather than redefining them, and put their own mockups in `docs/tech/phase-N.md`.

Status: foundation. Color, icon, and principles are settled (the palette was reworked to dark neutrals before Phase 2); type, spacing, and component specs are first drafts to be firmed up in the phase doc that first builds the UI (Phase 2).

---

## Principles

- **Dark only** — one theme, no toggle, no light variant. The web app, the native apps and the landing page all declare dark.
- **Chat is the interface** — the design is a quiet frame around a conversation. Chrome is minimal; color is used for meaning, not decoration.
- **Deep reds, kept dark** — surfaces are a ramp of dark reds built on the icon's Suit (`#3B0000`), staying at the dark end with small steps between them. The brighter reds (Lapel, Accent) appear in small doses: the send button, the selected marker, an error. If a screen looks bright red, something is overusing them.
- **Doesn't beg for attention** — no badges that count up, no pulsing, no streaks. Donna is measured by how much she helps, not how much she's used (see vision, Presence).
- **Tailored, not flashy** — the icon is a suit and tie; the app should feel the same: deep, restrained, well-fitted.
- **Web first, native later** — the web app is the first front end (see vision, UI). It uses plain HTML and CSS with system fonts and the tokens below. When the native apps arrive they use SwiftUI system components, Dynamic Type and platform conventions, with the same tokens.

---

## Color

Two groups: a ramp of five deep reds for surfaces, and the bright reds, which are used sparingly.

### Surfaces — deep reds

One hue (the icon's Suit, `#3B0000`) stepped darker and slightly lighter. Raised is the Suit itself. Steps are deliberately small. Numbers run dark to light; surfaces get lighter as they come forward.

| # | Name | Hex | Role |
|---|---|---|---|
| 1 | Canvas | `#120000` | Page and window background — what everything sits on |
| 2 | Inset | `#1E0000` | Hairlines and separators on Canvas; recessed fills (code blocks, table stripes, error banner) |
| 3 | Panel | `#230000` | Sidebar, top bar, input bar, the project detail pane |
| 4 | Raised (the Suit) | `#3B0000` | Donna's chat bubbles, cards, the input field, sheets |
| 5 | Hover | `#4D0A0A` | Hover and pressed backgrounds, the selected row, borders on Raised |

### Bright reds

| Name | Hex | Role |
|---|---|---|
| Accent | `#A3262A` | The single interactive color, used in small amounts: the send button, the selected-item marker, flagged state, error borders. Never a large surface. |
| Lapel | `#6B1214` | The icon's lapels. Not used for UI surfaces; too bright for a background. |

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

| On | Shirt | Shirt 65% | Shirt 35% |
|---|---|---|---|
| Canvas `#120000` | 17.4 : 1 | 7.4 : 1 | 2.8 : 1 |
| Inset `#1E0000` | 16.9 : 1 | 7.3 : 1 | 2.8 : 1 |
| Panel `#230000` | 16.6 : 1 | 7.2 : 1 | 2.8 : 1 |
| Raised `#3B0000` | 15.0 : 1 | 6.7 : 1 | 2.7 : 1 |
| Hover `#4D0A0A` | 13.1 : 1 | 6.1 : 1 | 2.6 : 1 |

Tie on Shirt is 15.0 : 1, and Shirt on Accent is 6.2 : 1. All body text passes AA. Disabled text (35%) is exempt.

The steps between surfaces are small (adjacent surfaces differ by only 1.02 to 1.15 : 1), so separation comes from the step itself plus a hairline where two meet, not from contrast alone. Check on a real screen and widen a step here if it disappears.

Accent on these surfaces is only 2.1 to 2.8 : 1, so it is **never text** and never the only signal. Use it for fills with a Shirt label or icon, and pair it with a shape or a word. **Focus rings are Shirt, not Accent**, because Accent fails the 3 : 1 needed for them.

### Status colors

Error is red, which is also the accent, so an error is told apart by shape: an Accent border plus an icon and a message (matches the vision: input disabled, border red, message explains). Success and warning get no new hues; use an icon and text. If Phase 2 shows this isn't enough, add one muted amber and one muted green here, not ad hoc in the app.

### Tokens

Same names on every platform. Web: CSS custom properties. Swift: an asset catalog color set per token (single appearance, dark).

```
--donna-canvas: #120000;
--donna-inset: #1E0000;
--donna-panel: #230000;
--donna-raised: #3B0000;   /* the icon's Suit */
--donna-hover: #4D0A0A;
--donna-accent: #A3262A;
--donna-lapel: #6B1214;   /* icon only */
--donna-shirt: #F4EBE1;
--donna-tie: #1E1718;
```

---

## Icon

The Donna icon is a suit from the front: a Shirt-colored V with a Tie-colored knot, framed by Lapel (`#6B1214`) lapels, on a Suit (`#3B0000`) background. Source of truth is [`api/public/favicon.svg`](../api/public/favicon.svg) (512 × 512 viewBox, square, full-bleed).

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

- **Chat history** — one continuous conversation on Canvas. Donna's messages sit in Raised bubbles; Josh's in Shirt bubbles with Tie text. Timestamps and metadata in secondary text. Day dividers are small uppercase secondary text.
- **Sources and flags** — under each Donna reply: the time, a "N sources" toggle that lists what she used (on Inset), and good/bad flags. A set flag is Hover with a Shirt border, not red.
- **Chat input** — fixed to the bottom, a Raised field on a Panel bar. Send is the one Accent button. Disabled while thinking; Accent border plus an icon and message on error. The focus ring is Shirt.
- **Buttons** — primary is Shirt with Tie text; secondary is transparent with Shirt text. Accent fills are reserved for send.
- **Thinking indicator** — a quiet in-chat indicator in secondary text; no bouncing dots.
- **Code blocks** — Inset with a monospace font and a copy button; a language label in secondary text.
- **Navigation** — selected item is Raised with a short Accent marker on its left edge, so selection is shape plus a color accent, not a red slab.
- **Pills** — Active: Raised with a small Accent dot; Proposed: Shirt 65% outline; Parked: dashed outline. Never a filled red pill.
- **File viewer** — Panel. Visual mode renders on Panel; edit mode is visually distinct (Inset, monospace) so Josh always knows which he's in. Metadata is expandable at the bottom.
- **Quick capture** — a single Raised field that gets out of the way; same tokens as chat input.
- **Notifications** — system styling only; Donna's icon and plain text.

### Platform layout

- **Mac (web)** — a sidebar (Panel) with Chat and Projects, and the chat in a centered column. Files, later, open in a 50/50 side panel.
- **iPhone (web, installed to the Home Screen)** — chat fills the screen; a top bar leads to Projects; files open as a full-screen sheet.
- **Native apps (later)** — the same layouts in SwiftUI. **Watch** is capture only: dictation, then a confirmation, on Canvas with Shirt text.

---

## Decisions to make with the first UI phase

- Final type scale and spacing grid
- Whether error/success need dedicated hues (see Status colors)
- Motion: default to system animations, nothing decorative
- Haptics and sound for capture confirmation
- Mockups (live in the phase doc, approved by Josh before building)
