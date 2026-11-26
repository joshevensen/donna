# Phases

The order Donna gets built in. Each phase is a working Donna Josh actually uses — not a layer of plumbing waiting on the next one.

Source of truth is [`vision.md`](vision.md); concepts are defined in [`domain.md`](domain.md).

## How phases work

- **One tech doc at a time** — `docs/tech/phase-N.md` is written just before that phase is built, using what the previous phases taught. Later phases here are intentionally rough.
- **Docs first, together** — each phase doc starts with an interview: Claude reads the relevant vision sections and asks Josh, a few questions per round, about what the vision doesn't answer — real situations from his life, not implementation. From that, Josh and Claude write the doc together: user stories first (Josh's call), then the technical design (Claude's call, discussed with Josh), and UI mockups once there's UI. Josh approves the finished doc.
- **Built in one shot** — once approved, Fable implements the phase doc in a single pass, no milestones. The doc references whatever else it needs (vision, domain model, prompts) and makes reasonable defaults rather than leaving decisions open.
- **Data is protected, not precious** — the database is never wiped casually; changes go through migrations. But this is a personal project, not a product with customers: basic backups are enough, and losing data would hurt, not end things.
- **Done means daily use** — a phase is finished when Josh is using it day to day, not when every bullet is checked. Leftover bullets move to a later phase.
- **API first until the app is needed** — Phase 1 is used through `php artisan donna:chat`. The Donna app starts in Phase 2, because Presence can't work without push, Siri, and device context.
- **This doc changes** — reorder, split, or cut phases as things are learned. Update it in the same PR as the change.

---

## Phase 1 — Foundation, Conversation + Memory

Donna runs on the real server, every call is accounted for, and she remembers. Memory is the product; everything after builds on it.

**Foundation**
- One Forge-managed droplet with Postgres + pgvector on it; Spaces; droplet backups + database dumps to Spaces
- Sanctum token auth (one user)
- Laravel AI SDK wired to Anthropic behind a provider-agnostic interface; Voyage AI for embeddings (Anthropic has no embeddings model)
- Web search via Anthropic's server-side web search tool, so Donna can go find out instead of guessing
- `costs` table — every LLM call records a row
- Messages stored permanently, with their source
- Message endpoint + `donna:chat` artisan command, run from Josh's Mac against the deployed API
- Character profile in the database, versioned — first version seeded from the draft in `api/resources/prompts/donna.md` (rewritten by Josh), after which the file is deleted. Cached as the start of the system prompt.

**Memory**
- Context rebuilt per message: current date, related messages, Facts, People, Skills (none yet)
- Hybrid retrieval — Postgres full-text + pgvector
- Personal and Social Memory — Facts and People, with provenance
- "Remember this" writes immediately
- Daily passive extraction via the Batch API
- Getting to Know You — an ongoing interview over days and weeks; the planning seed notes are pasted into chat
- Time awareness and stale-topic refreshers
- "What did you cost me this month?"
- Shared history — daily extraction also captures moments worth remembering between Josh and Donna (running jokes, calls she got right or wrong), stored as Facts about Donna
- Character growth — Donna proposes edits to her own profile; Josh approves; a new version is saved
- Start the personal eval set — Josh flags exchanges as good or bad examples from day one, plus personality and boundary cases (pushes back, never sycophantic, doesn't pad, no romance, no "I missed you"). A runner that replays them comes with the first prompt or model change, not before.

**Done when:** Josh talks to Donna daily from the terminal and she recalls things he told her days ago without being prompted.

## Phase 2 — Presence v1 + the Donna app

Donna comes to Josh. The app starts here.

**App (`Donna` target only)**
- Chat UI per the vision (single conversation, infinite scroll up, chat input, thinking/error states)
- Dictation via SpeechAnalyzer
- Quick capture — Mac hotkey + menu bar field, iPhone Action Button / widget
- Siri via App Intents ("tell Donna…")
- APNs with actionable notifications (done / snooze / open)
- Context Signals — calendar, location, driving, active device
- UI starting points from the original vision draft: max chat width 600px centered on Mac with no panel open; input shows up to 15 lines then scrolls; three input rows (text / controls: settings + mic left, paperclip + submit right / attachment tags, only when present); initial load of the last 2–3 days

**API**
- Tasks and Goals — captured explicitly or noticed in chat
- Rule-based timing — not while driving or in a calendar block, batch at transitions
- Nudges with outcomes, persistent until done; "done," "snooze," "tomorrow" from chat or notification
- "What's on my plate?"
- Accountability — stall detection, asks why, dial set conversationally
- Active memory check-ins
- Durable workflows introduced for Presence jobs
- No external actions yet — Donna can draft, not send

**Done when:** Josh uses the app daily instead of the terminal, captures land in under five seconds, and nudges arrive at moments he can act on them.

## Phase 3 — Files + Archive Memory

- Files (MD, CSV) — create, view, manual edit, Donna-edit
- Deterministic operations without the LLM (replace, rename, append, delete line)
- Chunking at logical boundaries + embeddings; files join per-message context
- File viewer in the app (side panel on iPad/Mac, sheet on iPhone)
- Attachments in chat

## Phase 4 — Skills, Integrations, Graduated Autonomy

Donna starts acting in the outside world, so trust has to exist first.

- Agent Skills loader — `SKILL.md` stored in Spaces, indexed in Postgres, loaded on relevance
- Actions, Action Types, Trust Grants — confirm-first enforced server-side, audit log, autonomous actions reported
- Integration credentials, encrypted
- First integrations: Gmail, Google Calendar
- Starter skills: Correspondence, Research, Briefing
- Presence "doing it for him" — work done first, surfaced ready to approve

## Phase 5 — Orchestration

- Jobs and scoped Workers on durable workflows, parallel by default
- Per-job budgets and cost totals
- Checkpoints — jobs pause for confirmation and resume
- "What are you working on?"
- Code Work — GitHub integration, Orc status tracking, headless Claude Code runs

## Phase 6 — Home Node

- `DonnaNode` — headless macOS agent, login item, outbound over Tailscale
- Runs Workers dispatched from the cloud; Mac-only jobs queue while offline
- Messages read access + on-device triage (Foundation Models)
- EventKit (Reminders, Calendar), Shortcuts

## Later

Unordered until earlier phases make the order obvious.

- Passive Capture — email (could follow Phase 4), texts (needs Phase 6), wearable
- Skill learning loop — create, refine, propose, retire
- Memory curation job
- Scheduled character review — Donna periodically reviews shared history on her own and proposes profile edits
- AI-judged nudge timing (once there's outcome data)
- `DonnaWatch` capture app
- Travel starter skill
- Voice output (TTS) setting
