# Phase 1 — Foundation, Conversation + Memory

Donna runs on the real server, every call is accounted for, and she remembers. Scope is in [`phases.md`](../phases.md); concepts in [`domain.md`](../domain.md); her character profile draft in [`api/resources/prompts/donna.md`](../../api/resources/prompts/donna.md).

**Status:** user stories approved. Design drafted — awaiting Josh's approval.

---

## User Stories

### Talking to Donna

- I talk to Donna from a terminal on my Mac. She answers in her own voice, as defined in her character profile.
- There's one conversation. I never start a new chat, and nothing is siloed by thread or project.
- Closing the terminal never loses anything. Every message is saved the moment it's sent, and if I close mid-reply, Donna still finishes it.
- When she doesn't know something, she looks it up on the web instead of guessing.
- When I open `donna:chat` again, I see the last few exchanges and pick up right where we left off — including any reply I missed.

### She remembers everything

- When I bring up a topic, Donna draws on everything I've ever said about it, however long ago and wherever it came up. I never have to repeat myself.
- When I mention someone, she knows who they are, how they fit in my life, and where things stand with them.
- I don't have to tell her to remember things — she picks them up from what we talk about. When I do say "remember this," it sticks immediately.

### She keeps up when I change my mind

- When I change my mind, Donna updates what she knows and keeps the history of what I used to think.
- When the change matters, she points it out: "In March you said X — what changed?" When it doesn't, she just updates.

### She tracks my interests and rabbit holes

- Donna knows my interests by what I keep coming back to, not by a list I gave her.
- She comments on my rabbit holes: "That's your third keyboard rabbit hole this month."
- When a rabbit hole relates to one of my projects, she connects it.

### She knows my projects

- Donna knows what I'm working on — my projects and Kristen's — which ones are main, secondary, or just for fun, and which are active, parked, or dropped.
- When something new starts looking like a real project, she asks whether to track it. I never fill out a form or manage a list.
- I change a project's priority or status just by telling her: "Park Storyrift for now," "Bad Frog is Kristen's main thing."
- Projects never wall anything off. Something I said about Fibermade in a conversation about yarn shows still comes up when it's relevant.
- I can ask "what am I working on?" and get a short, honest answer — including what's been quiet for a while.

### She knows what time it is

- Donna always knows today's date and how long it's been since we talked about something.
- When a topic comes back after a while, she opens with a quick recap of where it stood.

### We get to know each other

- Over days and weeks, Donna interviews me about my life, interests, people, and our relationship — in sessions of any length, from five minutes to an hour.
- She remembers what we've covered and what's still open, and picks up where we left off. It weaves into normal conversation; I can also say "let's keep going" to pick the interview back up.
- I can give her the seed notes from planning, and she takes them in like anything else I tell her.

### I can see and fix what she knows

- I can ask "what do you know about me / Kristen / Fibermade?" and get a clear, honest answer.
- When something's wrong or out of date, I tell her in chat and she fixes it. When something shouldn't be there, I tell her to forget it.

### She's Donna, and she grows

- Donna stays in character — her personality and boundaries never slip.
- She remembers our shared history: running jokes, calls she got right, calls she got wrong.
- When she thinks part of who she is should change, she proposes the change to her character profile. I approve or reject it; nothing about her changes silently.

### Cost and quality

- I can ask "what did you cost me this month?" and get a straight answer.
- I can flag an exchange as a good or bad example, so future changes to Donna can be tested against real conversations.

### Not in this phase

- Reminders and nudges, including reminding me about events at the right time (Phase 2)
- The Donna app — Phase 1 is terminal only (Phase 2)
- Files and attachments (Phase 3)
- Donna doing anything outside the conversation — email, calendar, messages (Phase 4)

---

## ## Design

The build follows this section. Where it's silent, follow Laravel and Swift best practice, the root and `api/` `CLAUDE.md` files, and the Boost guidelines — and note any notable choice in the PR. If something here turns out to be wrong once you're in the code, deviate, say why in the PR, and update this doc in the same PR.

### Delivery

- Build on a branch and open one PR against `main`. Never merge — Josh merges.
- Pest feature tests for every story below, Pint clean, all tests green before the PR.
- The PR description lists notable decisions and anything that deviates from this doc.

### Packages and providers

| Need | Choice |
|---|---|
| Conversation, tagging, tools, web search, prompt caching, embeddings, pgvector helpers, fakes | `laravel/ai` (1.x) |
| Message Batches (daily extraction) | Anthropic's official PHP SDK — `laravel/ai` doesn't support the Batch API. Wrap it behind an app interface so tests use a fake. |
| Embeddings | Voyage AI through `laravel/ai`. Use `voyage-4` (general purpose; $0.06/M tokens, first 200M free); set dimensions from its docs and keep model + dimensions in config. Voyage is now owned by MongoDB: keys come from a MongoDB Atlas account and call `https://ai.mongodb.com/v1` (set the provider `url` if the driver defaults to `api.voyageai.com`). No MongoDB database is involved. |
| Queue | Laravel's database queue driver — one user, no Redis needed |
| Scheduling | Laravel scheduler. Durable workflows aren't needed until Phase 2. |

- **Not DigitalOcean Inference** — it resells the same models at provider rates, but its batch flow is its own, its web search is a third-party service rather than Anthropic's, and it adds a layer for no gain. Revisit only if one bill becomes worth it.
- **Don't use the SDK's conversation storage** (`RemembersConversations`, `agent_conversations` tables). Donna has exactly one conversation and rebuilds context per message from her own tables. Don't publish those migrations if the install allows it; if it doesn't, leave the tables unused.

### Models

All model choices live in `config/donna.php`, one entry per job:

| Job | Model | Notes |
|---|---|---|
| Replies | `claude-sonnet-5-5` | effort `medium` (configurable); adaptive thinking (the default) |
| Topic tagging | `claude-haiku-5-5` | effort `low`; structured output |
| Daily extraction | `claude-sonnet-5-5` via Batch API | structured output; judgment-heavy |

Sonnet 5.5 can return `stop_reason: refusal`. If `laravel/ai` exposes provider options, enable Anthropic's server-side fallback (`fallbacks: "default"` with its beta header); otherwise handle a refusal by saving a short in-character "I can't help with that one" reply and logging it. Never surface a raw error as Donna's voice.

### Data model

All tables use `id` bigint keys unless noted, timestamps, and Postgres-native types. Vector columns use pgvector with HNSW cosine indexes; text search uses generated `tsvector` columns (`english` config) with GIN indexes.

| Table | Purpose | Key columns |
|---|---|---|
| `users` | Josh's login (exists) | — |
| `people` | Everyone, including Josh and Donna | `kind` (self, donna, other), `name`, `aliases` (jsonb), `relationship`, `summary` (living description), `user_id` (Josh only) |
| `messages` | The one conversation | `author` (josh, donna), `body`, `source` (terminal for now), `status` (pending, complete, failed), `in_reply_to_id`, `error`, `embedding`, `search` (tsvector) |
| `topics` | What gets talked about | `name`, `summary` (where it stands), `first_discussed_at`, `last_discussed_at`, `embedding` |
| `message_topic` | Tags | `message_id`, `topic_id` |
| `projects` | What's being worked on | `name`, `aliases`, `owner_person_id`, `tier` (main, secondary, fun), `status` (proposed, active, parked, dropped), `summary`, `status_changed_at` |
| `project_topic` | Which topics belong to a project | `project_id`, `topic_id` |
| `facts` | What Donna knows | `statement`, `kind` (fact, preference, decision, opinion, moment), `confidence` (0–1), `provenance` (remember, extraction, interview, correction), `source_message_id`, `superseded_by_id`, `superseded_at`, `notable_change` (bool), `retrieval_count`, `last_retrieved_at`, `embedding`, `search` |
| `fact_subjects` | Polymorphic many-to-many | `fact_id`, `subject_type`, `subject_id` — morph map: `person`, `project`, `topic` |
| `character_profiles` | Who Donna is, versioned | `version`, `body`, `status` (proposed, active, rejected, superseded), `change_summary`, `proposed_in_message_id`, `decided_at` |
| `interview_areas` | Getting to Know You coverage | `area`, `status` (open, in_progress, covered), `notes`, `last_touched_at` |
| `eval_examples` | Flagged exchanges | `message_id` (Donna's reply), `rating` (good, bad), `note` |
| `extraction_runs` | Daily batch bookkeeping | `window_start`, `window_end`, `batch_id`, `status`, `results_applied_at` |
| `costs` | One row per model call | `purpose` (reply, tagging, extraction, embedding), `provider`, `model`, `input_tokens`, `output_tokens`, `cache_read_tokens`, `cache_write_tokens`, `web_search_requests`, `batched` (bool), `usd` (decimal 12,6), `trigger_type`/`trigger_id` (morph: message, extraction_run) |

Rules:
- Facts are never updated in place. A correction creates a new Fact and sets `superseded_by_id`/`superseded_at` on the old one. "Active" means not superseded.
- "Forget it" is the one hard delete: Josh's explicit request deletes the Fact and its subject links. Nothing is deleted automatically.
- Seed data (a seeder, idempotent, run on first deploy): Josh's user and Person, Donna's Person, Kristen's Person (relationship: wife), character profile v1 = the current text of `api/resources/prompts/donna.md` (copied into the seeder, then delete the file), and interview areas: life story, work, people, interests, how Josh works, goals, and Josh & Donna.

### Messages and replies

API (all routes `auth:sanctum`, JSON, versioned under `/api/v1`):

| Endpoint | Does |
|---|---|
| `POST /messages` | `{body, source}` → saves Josh's message, creates Donna's reply as `pending`, dispatches `GenerateReply`, returns `202` with both |
| `GET /messages/{message}` | One message — the client polls Donna's pending reply |
| `GET /messages?before={id}&limit=` | History, newest first, cursor-paginated |
| `POST /messages/{message}/evaluation` | `{rating, note}` — flag one of Donna's replies |

`GenerateReply` (queue `replies`, one at a time via `WithoutOverlapping`, 2 retries with backoff):
1. Embed Josh's message (store it).
2. Build context (below).
3. Prompt the `Donna` agent with tools, max ~8 steps.
4. Save the reply text (and web-search citations in a `meta` jsonb column), mark `complete`; record costs.
5. On final failure: mark `failed` with a short error. The client shows it; nothing is lost.

After a reply completes, queue `EmbedMessage` for Donna's reply and `TagTopics` for the exchange.

### `donna:chat`

An artisan command in the same app, run from Josh's Mac against the deployed API (`DONNA_API_URL` and `DONNA_API_TOKEN` in his local `.env`). It never touches the database directly.

- On start: fetch the last ~10 messages and print them, including any reply that finished while he was away; if one is still pending, wait for it.
- Input via Laravel Prompts (multi-line textarea). Send → show a thinking indicator → poll every second until complete or failed.
- Slash commands, handled in code, never sent to Donna: `/good [note]` and `/bad [note]` flag her last reply; `/history [n]`; `/quit`.
- Plain-text output with light formatting; no markdown renderer needed in Phase 1.

A server-side `php artisan donna:token` command creates Josh's Sanctum token.

### Context assembly

`ContextBuilder` is plain code — the only model call is the embedding of Josh's message. Order matters for caching:

1. **System, cached (breakpoint 1):** the active character profile + stable operating instructions (how to use memory and tools, when to bring up stale topics, interests, changed minds, the interview).
2. **System, cached (breakpoint 2):** Josh's Person summary and the list of active and parked projects with tier and status. Changes rarely.
3. **Recent conversation:** the last 20 messages verbatim as conversation turns (working memory), capped at ~8k tokens.
4. **Final user turn:** a `<memory>` block, then Josh's message. The block holds, each with dates:
   - **Related past messages** — hybrid search over messages outside the recent window: top 40 by vector similarity + top 40 by full-text rank, merged with reciprocal rank fusion, top ~12, each shown with the reply it got.
   - **Related facts** — the same hybrid search over active facts, top ~20, plus the superseded predecessors of any fact retrieved (so Donna sees "used to think X, since March thinks Y"; `notable_change` marks the ones worth raising).
   - **People** — anyone whose name or alias appears in the message (string match, code) or who is a subject of a retrieved fact: their summary and top facts.
   - **Topics** — the topics nearest the message by embedding: summary, `last_discussed_at`, and mention counts over 7/30/90 days. A topic idle for 14+ days is marked stale so Donna opens with a recap. Counts let her comment on rabbit holes.
   - **Projects boost, never filter** — items linked to a project the message relates to get a fixed score bonus before the final cut.
   - **Open interview areas** and any **pending proposals** (character changes, projects she suggested tracking).
   - Current date and time in Josh's timezone (`config('donna.timezone')`, default `America/Chicago`) and time since his last message.
5. The memory block is capped (~10k tokens) by trimming the lowest-scoring items. Retrieved facts get `retrieval_count`/`last_retrieved_at` bumped (for curation later).

All counts and thresholds above live in `config/donna.php` so they can be tuned without code changes.

### Donna's tools

Memory changes happen through tools Donna calls during a reply, so everything stays conversational. Each tool is a `laravel/ai` tool class doing deterministic work:

| Tool | Does |
|---|---|
| `remember` | Writes a Fact immediately (`provenance: remember`) with its subjects |
| `recall` | Explicit deeper search when the automatic context isn't enough |
| `what_i_know` | Lists active facts about a person, project, or topic — answers "what do you know about…" |
| `correct_fact` | Supersedes a fact with a corrected statement |
| `forget_fact` | Deletes a fact — only when Josh asks |
| `save_person` | Creates or updates a Person (name, aliases, relationship, summary) |
| `propose_project` / `update_project` | Suggests tracking a project; sets tier, status, owner after Josh says so |
| `propose_character_change` | Saves a proposed new profile version with a change summary |
| `decide_character_change` | Activates or rejects a proposal — only after Josh has said yes or no in the conversation |
| `update_interview` | Marks interview areas in progress or covered, with notes |
| `cost_report` | Sums `costs` for a period in SQL — no model involved in the maths |
| Web search | Anthropic's server-side web search through `laravel/ai`, `max_uses` capped in config |

### Topic tagging

`TagTopics` (Haiku 5.5, structured output) runs after each exchange. It's given the exchange plus the ~10 nearest existing topics and returns which of them apply and any new topic names. Code links the messages, creates new topics (with embeddings), and updates `last_discussed_at`. Topic summaries are refreshed by the daily extraction, not here. This resolves the domain model's open question: topics are assigned at write time and consolidated daily.

### Daily extraction

Scheduled daily at 03:00 Josh's time.

1. Collect messages since the last run's `window_end`. None → done.
2. Build Batch API requests (split by day if large). Each carries the messages, the People list, active projects, and the active facts most related to that day's topics, with IDs.
3. Structured output returns: new facts (statement, kind, confidence, subjects, source message IDs), supersessions (old fact ID → new statement, `notable_change`), people to create or update, topic summary updates, project candidates (become `proposed` projects Donna brings up), shared-history moments (facts about Donna), and interview coverage.
4. A polling job checks the batch every 10 minutes; when it ends, apply all results in one transaction, embed new facts and topics, and record costs at batch pricing.
5. Only confident results are written as facts; low-confidence ones are saved with their confidence so Donna can confirm them when they come up.
6. The first run backfills everything since the first message.

### Cost tracking

- Every model call records a `costs` row — replies (each agent step), tagging, extraction, and embeddings. Use `laravel/ai` agent middleware or its events for agent calls; the batch client records its own. A test asserts that each call path records exactly one row per call.
- Price input, cache writes, cache reads, and output separately (the SDK reports cache tokens as a subset of input). Batch requests price at 50%.
- A pricing table in `config/donna.php`, per model, USD per million tokens. As of November 2026 from Anthropic's docs: Sonnet 5.5 $2 in / $10 out / $0.10 cache read; Haiku 5.5 $0.10 in / $0.50 out / $0.01 cache read (up to 100K context). Cache writes are 1.25× input. Confirm these and look up current Voyage and web search prices at build time.
- Set a hard monthly spend cap on the Anthropic key in the console (Josh, not code).

### Deployment

- Forge-provisioned DigitalOcean droplet, Basic 2 GB / 1 vCPU ($12/mo), with a swap file: PHP 8.4, Postgres with the pgvector extension installed, Nginx, Let's Encrypt for `donna.joshevensen.com`.
- Queue worker daemon for `replies,default`; scheduler cron.
- Backups: droplet backups on, plus a nightly database dump to Spaces (Forge database backups, or a scheduled `pg_dump` command if the plan lacks them).
- Env: `ANTHROPIC_API_KEY`, `VOYAGEAI_API_KEY`, Spaces credentials. Credentials are never logged and never sent to a model.
- A `docs/deploy.md` with the steps Josh runs once in Forge, including when to resize: memory regularly above ~80% or steady swap use → CPU/RAM-only resize (reversible; never grow the disk).

### Code vs model in this phase

| Code | Model |
|---|---|
| Context assembly, hybrid retrieval, rank fusion, project boost | Donna's replies (Sonnet 5.5) |
| Name/alias matching for People | Topic tagging (Haiku 5.5) |
| Stale-topic and rabbit-hole counts and thresholds | Daily extraction: facts, supersessions, summaries, candidates (Sonnet 5.5, batch) |
| Every tool's work (writes, supersession, forget, project and profile updates) | Embeddings (Voyage) |
| Cost pricing and the cost report | — |
| Slash commands, polling, pagination, history | — |
| Applying extraction results, retries, scheduling | — |

A tool is called by the model, but what the tool does is always code.

### Testing

- Follow the Testing rules in the root `CLAUDE.md`: each test maps to a story or invariant, one test per behavior, no prompt-wording tests, suite under ~60 seconds.
- Tests run against Postgres with pgvector (not SQLite) so vector and full-text queries are real.
- Fake every model: `Donna::fake()`, `Embeddings::fake()`, and a fake batch client. No test calls a real API.
- Cover: the message lifecycle and polling, reopening after a missed reply, context assembly (recent window, hybrid retrieval, superseded facts, stale topics, project boost never filtering), each tool, supersession vs. forget, character proposal and approval, extraction applying results and backfilling, cost rows for every call path and the cost report, eval flagging, and `donna:chat` against `Http::fake()`.

### Not in this phase

Streaming replies, the app, push, scheduled check-ins, memory curation, an eval runner, and durable workflows.
