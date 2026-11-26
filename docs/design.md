# Design

How Donna looks and feels. [`vision.md`](vision.md) (UI section) says what the interface is — chat, dark mode only; this doc says what it's made of. Phase docs reference this for color, type, and components rather than redefining them, and put their own mockups in `docs/tech/phase-N.md`.

Status: foundation. Color, icon, and principles are settled; type, spacing, and component specs are first drafts to be firmed up in the phase doc that first builds the UI (Phase 2).

---

## Principles

- **Dark only** — one theme, no toggle, no light variant. The apps and the landing page all declare dark.
- **Chat is the interface** — the design is a quiet frame around a conversation. Chrome is minimal; color is used for meaning, not decoration.
- **Doesn't beg for attention** — no badges that count up, no pulsing, no streaks. Donna is measured by how much she helps, not how much she's used (see vision, Presence).
- **Tailored, not flashy** — the icon is a suit and tie; the app should feel the same: deep, restrained, well-fitted.
- **Native first** — SwiftUI system components, Dynamic Type, and platform conventions on iOS, iPadOS, and macOS. Custom styling only where it carries the brand.

---

## Color

Five reds, one family, named by the suit they come from. Numbers run light to dark.

| # | Name | Hex | Role |
|---|---|---|---|
| 1 | Lightest | `#A3262A` | Accent — the single interactive color: send button, links, selected state, focus ring, unread marker |
| 2 | Light (lapel) | `#6B1214` | Raised surfaces — Donna's chat bubbles, cards, sheets, input field, hover/pressed backgrounds |
| 3 | Mid (suit) | `#3B0000` | Panels — sidebars, file viewer, toolbar, the icon's background |
| 4 | Dark | `#260000` | Separators and borders on Darkest; subtle fills (code blocks, table stripes) |
| 5 | Darkest | `#140000` | Page and window background — the canvas everything sits on |

### Neutrals

Text needs more than five reds. These two come straight from the icon (shirt and tie) and are the only non-red colors in the system.

| Name | Hex | Role |
|---|---|---|
| Shirt | `#F4EBE1` | Primary text and icons; Josh's chat bubbles (with Darkest text) |
| Tie | `#1E1718` | Text on Shirt-colored fills |

Secondary text is Shirt at 65% opacity; disabled is 35%. Don't introduce a pure white or pure black.

### Layering

Surfaces get lighter as they come forward: Darkest (canvas) → Dark (inset, borders) → Mid (panels) → Light (raised) → Lightest (accent only, never a large surface).

### Contrast

Shirt text, measured (WCAG):

| On | Ratio |
|---|---|
| Darkest `#140000` | 17.3 : 1 |
| Dark `#260000` | 16.5 : 1 |
| Mid `#3B0000` | 15.0 : 1 |
| Light `#6B1214` | 10.4 : 1 |
| Lightest `#A3262A` | 6.2 : 1 |

All pass AA for body text. Lightest on Darkest is only 2.8 : 1, so the accent is **never used as text color on a dark surface** for anything that must be read — use it for fills, icons larger than 24 pt, borders, and underlines, and keep the label Shirt.

### Status colors

Error is Lightest, which is already red — so error is distinguished by shape, not hue: a red border plus an icon and message (matches the vision: input disabled, border red, notification explains). Success and warning get no new hues; use an icon and text. If Phase 2 shows this isn't enough, add one muted amber and one muted green here, not ad hoc in the app.

### Tokens

Same names on every platform. Swift: an asset catalog color set per token (single appearance, dark). Web: CSS custom properties.

```
--donna-1: #A3262A;   /* accent */
--donna-2: #6B1214;   /* raised */
--donna-3: #3B0000;   /* panel */
--donna-4: #260000;   /* border / inset */
--donna-5: #140000;   /* canvas */
--donna-shirt: #F4EBE1;
--donna-tie: #1E1718;
```

---

## Icon

The Donna icon is a suit from the front: a Shirt-colored V with a Tie-colored knot, framed by Light lapels, on a Mid background. Source of truth is [`api/public/favicon.svg`](../api/public/favicon.svg) (512 × 512 viewBox, square, full-bleed).

- **Full-bleed square** — no baked-in rounded corners or padding. iOS, iPadOS, and macOS apply their own mask; the landing page rounds it with CSS.
- **Derived files** — `favicon.ico` (16/32/48) and `apple-touch-icon.png` (180) are rasterized from the SVG. Regenerate them whenever the SVG changes; never edit them by hand.
- **App icons** — Xcode's 1024 × 1024 App Icon asset is exported from the same SVG when the apple project is created. The watchOS and Home Node apps use the same icon.
- **Don't** — recolor it, add a wordmark inside it, or place it on a light background.

### Landing page

`api/public/index.html` shows the icon centered on a Darkest background and nothing else. It has no route or view; the API stays pure JSON.

---

## Typography

- **System fonts** — SF Pro on Apple platforms, `ui-sans-serif` stack on the web. No custom typefaces to ship or license.
- **Dynamic Type everywhere** — text styles, never fixed sizes.
- **Monospace** (SF Mono) for code, file paths, and CSV/edit views.
- Hierarchy comes from weight and opacity (Shirt 100% / 65% / 35%), not extra colors.

---

## Components

First drafts; see vision UI section for behavior.

- **Chat history** — one continuous conversation on Darkest. Donna's messages sit in Light bubbles; Josh's in Shirt bubbles with Tie text. Timestamps and metadata in secondary text.
- **Chat input** — fixed to the bottom, Light field on a Mid bar, Lightest send button. Disabled while thinking; red border on error.
- **Thinking indicator** — a quiet in-chat indicator in secondary text; no bouncing dots.
- **File viewer** — Mid panel. Visual mode renders on Mid; edit mode is visually distinct (Dark inset, monospace) so Josh always knows which he's in. Metadata is expandable at the bottom.
- **Quick capture** — a single Light field that gets out of the way; same tokens as chat input.
- **Notifications** — system styling only; Donna's icon and plain text.

### Platform layout

- **iPhone** — chat fills the screen; files open as a full-screen sheet.
- **iPad / Mac** — chat plus a 50/50 side panel for files.
- **Watch** — capture only: dictation, then a confirmation. Darkest background, Shirt text.

---

## Decisions to make with the first UI phase

- Final type scale and spacing grid
- Whether error/success need dedicated hues (see Status colors)
- Motion: default to system animations, nothing decorative
- Haptics and sound for capture confirmation
- Mockups (live in the phase doc, approved by Josh before building)
