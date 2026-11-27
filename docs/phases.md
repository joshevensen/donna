# Phases

The order Donna gets built in. Each phase is a working Donna Josh actually uses — not a layer of plumbing waiting on the next one.

Source of truth is [`vision.md`](vision.md); concepts are defined in [`domain.md`](domain.md).

## How phases work

- **One tech doc at a time** — `docs/tech/phase-N.md` is written just before that phase is built, using what the previous phases taught. Later phases here are intentionally rough.
- **Docs first, together** — each phase doc starts with an interview: Claude reads the relevant vision sections and asks Josh, a few questions per round, about what the vision doesn't answer — real situations from his life, not implementation. From that, Josh and Claude write the doc together: user stories first (Josh's call), then the technical design (Claude's call, discussed with Josh), and UI mockups once there's UI. Josh approves the finished doc. The data model says what writes every table, so nothing is defined that nothing fills.
- **Built in one shot** — once approved, Fable implements the phase doc in a single pass, no milestones. The doc references whatever else it needs (vision, domain model, prompts) and makes reasonable defaults rather than leaving decisions open. Before the PR is marked ready, an independent reviewer checks the diff against the doc: Phase 1's review found that nothing wrote `project_topic`, which the tests had missed because they set that data up by hand.
- **Setup outside the repo is written down** — anything a phase needs that isn't code (keys, accounts, Forge, DNS, devices) gets a file in [`docs/instructions/`](instructions/README.md) in the same PR, so Josh can set it up without asking.
- **Builds survive interruptions** — the build works on a branch, opens a draft PR early with a progress checklist from the phase doc, and commits and pushes after each larger area of work, ticking that area's checklist item in the same step. If a session ends or hits a usage limit, a new session resumes from the branch, the checklist, and the phase doc; nothing is lost.
- **Data is protected, not precious** — the database is never wiped casually; changes go through migrations. But this is a personal project, not a product with customers: basic backups are enough, and losing data would hurt, not end things.
- **Done means daily use** — a phase is finished when Josh is using it day to day, not when every bullet is checked. Leftover bullets move to a later phase.
- **Code projects first, web first** — Josh's main goal is to replace Claude and Claude Code, the Donna way, with Donna in charge of his code projects. Phase 1 is used through `php artisan donna:chat`; Phase 2 is a web chat that works on the Mac and the phone; Code Work follows. The native apps wait for Presence, which can't work without Siri, Action Button capture and device context. Travel, email, driving and reminders come after.
- **This doc changes** — reorder, split, or cut phases as things are learned. Update it in the same PR as the change.

---

## Phase 1 — Foundation, Conversation + Memory

Donna runs on the real server, every call is accounted for, and she remembers. Memory is the product; everything after builds on it.

**Status:** built; awaiting daily use.

**Foundation**
- One Forge-managed droplet with Postgres + pgvector on it; droplet backups + database dumps to Spaces (the app itself doesn't use Spaces until Files)
- Sanctum token auth (one user)
- Laravel AI SDK wired to Anthropic behind a provider-agnostic interface; Voyage AI for embeddings (Anthropic has no embeddings model)
- Web search via Anthropic's server-side web search tool, so Donna can go find out instead of guessing
- `costs` table — every LLM call records a row
- Messages stored permanently, with their source
- Message endpoint + `donna:chat` artisan command, run from Josh's Mac against the deployed API
- Character profile in the database, versioned — version 1 is the profile Josh wrote, seeded by `CharacterProfileSeeder`. Cached as the start of the system prompt.

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
- Start the personal eval set — Josh flags exchanges as good or bad examples from day one, plus personality and boundary cases (pushes back, never sycophantic, doesn't pad, no romance, no "I missed you"). A runner that replays them comes with the first prompt or model change, not before. Replaying a reply needs the memory Donna saw, which changes over time, and flagged examples don't keep it yet — saving each reply's memory block is worth doing before the runner.

**Done when:** Josh talks to Donna daily from the terminal and she recalls things he told her days ago without being prompted.

## Phase 2 — Web chat

The first front end, so Josh can leave the terminal. A web app he opens in a browser tab on the Mac and installs to the iPhone's Home Screen. It costs nothing to distribute and needs no Apple developer account. Detailed in [`tech/phase-2.md`](tech/phase-2.md).

**Status:** doc approved; not yet built.

- Chat UI per the vision and the mockup: one conversation, texting-style bubbles (replies as one bubble or several), typing dots, no progress messages, a collapsing input pinned above the keyboard, markdown and code blocks, errors and offline states, dark mode only
- Sign-in by emailed code and link, no passwords; Sanctum cookie sessions that last 30 days from last use, capped at 90 days; an email on every new sign-in with a sign-out-everywhere button
- Web Push for replies when the app isn't open
- Chat commands: natural language with a tap button, or `/signout` and `/notifications`
- Tapping a bubble shows its time; "sources" shows what she looked at. No ratings in the UI (Phase 1's flagging endpoint stays, unused for now)
- The repo becomes one Laravel app at the root (no `api/` folder, `apple/` removed); this ships in the Phase 2 PR, and the Forge site's root directory changes with it
- The API gains a `web` message source, idempotent sends, retry, `after` polling, bubbles, sources and actions
- No attachments (Phase 6), no projects page, no menu
- `donna:chat` and Blink stay as the fallback

**Done when:** Josh uses the web chat daily instead of the terminal, on his Mac and on his phone.

## Phase 3 — Skills, Integrations, Code Work awareness

Donna learns what is going on in Josh's code projects, read-only first. Nothing is changed, so trust doesn't have to exist yet.

- Agent Skills loader — `SKILL.md` stored in Spaces, indexed in Postgres, loaded on relevance; Josh can also invoke one with `/skill-name` (only when the message's first word is exactly `/` plus a registered name, so file paths still pass through; a leading space sends it literally). `@Name` is for people (Phase 2)
- Integration credentials, encrypted
- GitHub, read-only — issues, PRs, CI status, review comments, and Orc's decision records, reported in her voice
- Projects tied to repos, so "what's the state of my projects?" and "what's blocking #42?" have real answers
- Nudges when work is ready: "#42 is specced — start the build." Josh runs the interactive Orc steps (plan, build, walkthru) in Claude Code himself for now
- Starter skill: Research

**Done when:** Josh asks Donna about his code projects instead of opening GitHub.

## Phase 4 — Graduated Autonomy

Donna starts acting, so trust has to exist first.

- Actions, Action Types, Trust Grants — confirm-first enforced server-side, audit log, autonomous actions reported
- Approvals in the web chat: approve, decline, or grant standing trust for that action type
- The first writes, on GitHub: comment, label, create an issue (never merge)
- Presence "doing it for him" — work done first, surfaced ready to approve (the full form arrives with Presence)

## Phase 5 — Orchestration + Code Work

Donna runs the work, not just watches it.

- Jobs and scoped Workers on durable workflows, parallel by default. Durable workflows are introduced here
- Per-job budgets and cost totals — including the declined attempts of refusal fallbacks, which Phase 1 doesn't record yet
- Checkpoints — jobs pause for confirmation and resume. Orc's gates become Donna questions in the web chat
- "What are you working on?"
- Headless Claude Code runs with Orc on a droplet, when Josh is away; Donna watches the PR and CI and reports. Merge stays human
- The token-economics question (Max subscription vs. headless credit or API) gets answered with real usage data

**Done when:** Josh can hand Donna a specced issue and come back to a PR.

## Phase 6 — Files + Archive Memory

- Files (MD, CSV) — create, view, manual edit, Donna-edit
- Deterministic operations without the LLM (replace, rename, append, delete line)
- Chunking at logical boundaries + embeddings; files join per-message context
- File viewer in the web chat (side panel on wide screens, sheet on the phone)
- Attachments in chat

Placed here because code projects don't need it. If Josh starts wanting files sooner, move it up.

## Phase 7 — Presence v1 + the native iOS app

Donna comes to Josh. The native app starts here, because Presence needs what the web can't give: Siri, the Action Button, and device context.

**App (`Donna` target only)**
- iOS first; the iPad and Mac versions follow. Chat reuses what the web chat taught
- Dictation via SpeechAnalyzer
- Quick capture — iPhone Action Button / widget; on the Mac, hotkey + menu bar field
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
- First personal integrations: Gmail, Google Calendar; starter skills: Correspondence, Briefing

**Done when:** Josh uses the app daily, captures land in under five seconds, and nudges arrive at moments he can act on them.

## Phase 8 — Home Node

- `DonnaNode` — headless macOS agent, login item, outbound over Tailscale
- Runs Workers dispatched from the cloud; Mac-only jobs queue while offline
- Messages read access + on-device triage (Foundation Models)
- EventKit (Reminders, Calendar), Shortcuts

## Later

Unordered until earlier phases make the order obvious.

- Passive Capture — email (could follow Gmail), texts (needs Phase 8), wearable
- Skill learning loop — create, refine, propose, retire
- Memory curation job
- Retrieval tuning on real data — a similarity floor for message and fact search, which Phase 1 doesn't have
- Scheduled character review — Donna periodically reviews shared history on her own and proposes profile edits
- AI-judged nudge timing (once there's outcome data)
- `DonnaWatch` capture app
- Travel starter skill
- Voice output (TTS) setting
- A Mac app (hotkey and menu bar capture) if the web chat in a tab isn't enough
