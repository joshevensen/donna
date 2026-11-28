# Phase 1 — Foundation, Conversation + Memory

Donna runs on the real server, every call is accounted for, and she remembers. Scope is in [`phases.md`](../phases.md); concepts in [`domain.md`](../domain.md); her character profile lives in the database, seeded as version 1 by [`CharacterProfileSeeder`](../../database/seeders/CharacterProfileSeeder.php).

**Status:** built. The Design section below describes what was implemented; where the build departed from the approved draft, the text has been updated and the change is marked *(as built)*.

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

- Reminders and nudges, including reminding me about events at the right time (Presence, Phase 7)
- A front end — Phase 1 is terminal only (the web chat is Phase 2; the native app comes with Presence)
- Files and attachments (Phase 3)
- Donna doing anything outside the conversation — email, calendar, messages (Phases 4–5 for GitHub, Phase 7 for email and calendar)

---

## Design

The build follows this section. Where it's silent, follow Laravel and Swift best practice, the `CLAUDE.md` file, and the Boost guidelines — and note any notable choice in the PR. If something here turns out to be wrong once you're in the code, deviate, say why in the PR, and update this doc in the same PR.

### Delivery

- Build on a branch and open a draft PR against `main` early, with a progress checklist from this doc's sections. Commit and push after each larger area; tick the checklist as you go. Mark it ready when everything is done and CI is green. Never merge — Josh merges.
- Pest feature tests for every story below, Pint clean, all tests green before the PR.
- A GitHub Actions workflow that runs Pint (check mode) and the full suite against Postgres with pgvector on every PR.
- The PR description lists notable decisions and anything that deviates from this doc.

### Packages and providers

| Need | Choice |
|---|---|
| Conversation, tagging, tools, web search, prompt caching, embeddings, pgvector helpers, fakes | `laravel/ai` (1.x) |
| Message Batches (daily extraction) | Anthropic's official PHP SDK — `laravel/ai` doesn't support the Batch API. Wrap it behind an app interface so tests use a fake. |
| Embeddings | Voyage AI through `laravel/ai`. Use `voyage-4` (general purpose; $0.06/M tokens, first 200M free); set dimensions from its docs and keep model + dimensions in config. Keys come from voyageai.com and call `https://api.voyageai.com/v1`, the driver default (a MongoDB Atlas key would need `https://ai.mongodb.com/v1`). |
| Queue | Laravel's database queue driver — one user, no Redis needed |
| Scheduling | Laravel scheduler. Durable workflows aren't needed until Orchestration (Phase 6). |

- **Not DigitalOcean Inference** — it resells the same models at provider rates, but its batch flow is its own, its web search is a third-party service rather than Anthropic's, and it adds a layer for no gain. Revisit only if one bill becomes worth it.
- **Don't use the SDK's conversation storage** (`RemembersConversations`, `agent_conversations` tables). Donna has exactly one conversation and rebuilds context per message from her own tables. *(as built)* The SDK only publishes those migrations on request, so they were never published and the tables don't exist.
- *(as built)* `laravel/ai` 1.4 sends Anthropic's basic web search tool (`web_search_20250305`), not the newer dynamic-filtering variant. It works on every model; revisit when the SDK moves.

### Models

All model choices live in `config/donna.php`, one entry per job:

| Job | Model | Notes |
|---|---|---|
| Replies | `claude-sonnet-5-5` | effort `medium` (configurable); adaptive thinking (the default) |
| Topic tagging | `claude-haiku-5-5` | effort `low`; structured output |
| Daily extraction | `claude-sonnet-5-5` via Batch API | structured output; judgment-heavy |

Sonnet 5.5 can return `stop_reason: refusal`. *(as built)* Both halves are in place: replies send Anthropic's server-side fallback (`fallbacks: "default"` with its beta header, switchable with `DONNA_REFUSAL_FALLBACK`), and because that only retries some refusal categories, a refusal that still comes back is saved as a short in-character "I can't help with that one" reply and logged. Never surface a raw error as Donna's voice. Daily extraction also runs at effort `medium`.

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
- Facts are never updated in place. A correction creates a new Fact and sets `superseded_by_id`/`superseded_at` on the old one. "Active" means not superseded (`superseded_at` is null). `notable_change` sits on the new fact.
- "Forget it" is the one hard delete: Josh's explicit request deletes the Fact and its subject links. Nothing is deleted automatically. *(as built)* Forgetting a fact also deletes every earlier version it superseded — otherwise the thing he asked her to forget would survive as history.

*(as built)* Small additions to the table above: `messages.meta` (jsonb: citations, responding model, declined flag); `extraction_runs.error`; `topics.name` and `projects.name` are unique; `projects.tier` is null while a project is only proposed; `eval_examples.message_id` is unique (flagging again changes the verdict); a partial unique index allows only one `active` character profile; `facts.provenance` also takes `interview` when extraction marks a fact as coming from the interview; the morph map also holds `user` (Sanctum tokens). `costs.input_tokens` is the whole prompt, with the two cache columns as subsets of it. The cost trigger for everything in an exchange — embeddings, each reply step, tagging — is Josh's message.
- Seed data (a seeder, idempotent, run on first deploy): Josh's user and Person, Donna's Person, Kristen's Person (relationship: wife), character profile v1 = the current text of `resources/prompts/donna.md` (copied into the seeder, then delete the file), and interview areas: life story, work, people, interests, how Josh works, goals, and Josh & Donna.

### Messages and replies

API (all routes `auth:sanctum`, JSON, versioned under `/api/v1`):

| Endpoint | Does |
|---|---|
| `POST /messages` | `{body, source}` → saves Josh's message, creates Donna's reply as `pending`, dispatches `GenerateReply`, returns `202` with both |
| `GET /messages/{message}` | One message — the client polls Donna's pending reply |
| `GET /messages?before={id}&limit=` | History, newest first, cursor-paginated |
| `POST /messages/{message}/evaluation` | `{rating, note}` — flag one of Donna's replies |

`GenerateReply` (queue `replies`, one at a time via `WithoutOverlapping`, 2 retries with backoff):
1. Embed Josh's message (store it). *(as built)* If the embeddings API is down, Donna still replies from full-text memory, and her tools still work — a fact she is told to remember is saved without a vector. An hourly `EmbedMissing` job embeds whatever was saved that way.
2. Build context (below).
3. Prompt the `Donna` agent with tools, max ~8 steps. *(as built)* On the last allowed step tool use is switched off, so a run that is still calling tools ends with an answer instead of silence.
4. Save the reply text (and web-search citations in a `meta` jsonb column), mark `complete`; record costs.
5. On final failure: mark `failed` with a short error. The client shows it; nothing is lost. *(as built)* A reply that hits the job timeout (10 minutes) fails rather than retries, because its tool calls may already have happened; for the same reason `remember` won't write the same statement twice for one message.

After a reply completes, queue `EmbedMessage` for Donna's reply and `TagTopics` for the exchange.

### `donna:chat`

An artisan command in the same app, run from Josh's Mac against the deployed API (`DONNA_API_URL` and `DONNA_API_TOKEN` in his local `.env`). It never touches the database directly.

- On start: fetch the last ~10 messages and print them, including any reply that finished while he was away; if one is still pending, wait for it.
- Input via Laravel Prompts (multi-line textarea, sent with Ctrl+D). *(as built)* A function key sends too (F5 by default, `DONNA_SUBMIT_KEY` to change it, blank to turn it off), for terminals where the Ctrl+D chord is awkward, such as a phone. Send → show a thinking indicator → poll every second until complete or failed. *(as built)* After ten minutes it stops waiting and says the reply will be there next time; the server keeps working on it.
- Slash commands, handled in code, never sent to Donna: `/good [note]` and `/bad [note]` flag her last reply; `/history [n]`; `/quit`. In any chat, asking her to flag the reply before ("flag that as bad, she took my side") uses her `flag_reply` tool, which writes the same eval example.
- Plain-text output with light formatting; no markdown renderer needed in Phase 1.

A server-side `php artisan donna:token` command creates Josh's Sanctum token.

### Context assembly

`ContextBuilder` is plain code — the only model call is the embedding of Josh's message. Order matters for caching:

1. **System, cached (breakpoint 1):** the active character profile + stable operating instructions (how to use memory and tools, when to bring up stale topics, interests, changed minds, the interview).
2. **System, cached (breakpoint 2):** Josh's Person summary and the list of active and parked projects with tier and status. Changes rarely.
3. **Recent conversation:** the last 20 messages verbatim as conversation turns (working memory), capped at ~8k tokens. *(as built)* Josh's turns are prefixed with their local timestamp so gaps inside the window are visible; Donna's are not, so she doesn't start imitating the prefix. Pending and failed replies are left out, and the window always opens on one of Josh's messages.
4. **Final user turn:** a `<memory>` block, then Josh's message. The block holds, each with dates:
   - **Related past messages** — hybrid search over messages outside the recent window: top 40 by vector similarity + top 40 by full-text rank, merged with reciprocal rank fusion, top ~12, each shown with the reply it got.
   - **Related facts** — the same hybrid search over active facts, top ~20, plus the superseded predecessors of any fact retrieved (so Donna sees "used to think X, since March thinks Y"; `notable_change` marks the ones worth raising).
   - **People** — anyone whose name or alias appears in the message (string match, code) or who is a subject of a retrieved fact: their summary and top facts.
   - **Topics** — the topics nearest the message by embedding: summary, `last_discussed_at`, and mention counts over 7/30/90 days. A topic idle for 14+ days is marked stale so Donna opens with a recap. Counts let her comment on rabbit holes. *(as built)* A topic qualifies when the message names it outright or its embedding is within a cosine distance of 0.6, so an unrelated topic is never recapped just for being nearest. Counts are Josh's messages and the distinct days (in his timezone) he brought it up.
   - **Projects boost, never filter** — items linked to a project the message relates to get a fixed score bonus before the final cut. *(as built)* A message relates to a project it names (name or alias) or that one of its topics belongs to. `project_topic` is written in two ways: code links a topic to the project it shares a name with (when either is created), and daily extraction pairs topics with projects where that takes judgment. The memory block also lists every tracked project with when it was last discussed, which is what answers "what am I working on — and what's gone quiet?".
   - **Open interview areas** and any **pending proposals** (character changes, projects she suggested tracking).
   - Current date and time in Josh's timezone (`config('donna.timezone')`, default `America/Chicago`) and time since his last message.
5. The memory block is capped (~10k tokens) by trimming the lowest-scoring items. Retrieved facts get `retrieval_count`/`last_retrieved_at` bumped (for curation later). Token counts are estimated at four characters each.

*(as built)* Two things worth knowing when tuning: full-text search matches any word of the message rather than all of them (a whole chat message is the query), and vector candidates have no similarity floor — with little history, loosely related memories fill the remaining slots and are the first to go when the block is over budget. Facts below the confidence threshold are shown as unconfirmed.

All counts and thresholds above live in `config/donna.php` so they can be tuned without code changes.

### Donna's tools

Memory changes happen through tools Donna calls during a reply, so everything stays conversational. Each tool is a `laravel/ai` tool class doing deterministic work:

| Tool | Does |
|---|---|
| `remember` | Writes a Fact immediately (`provenance: remember`) with its subjects |
| `recall` | Explicit deeper search when the automatic context isn't enough |
| `what_i_know` | Lists active facts about a person, project, or topic — answers "what do you know about…". Newest 50, and says so when there are more |
| `correct_fact` | Supersedes a fact with a corrected statement |
| `forget_fact` | Deletes a fact and its earlier versions — only when Josh asks |
| `save_person` | Creates or updates a Person (name, aliases, relationship, summary) |
| `propose_project` / `update_project` | Suggests tracking a project; sets tier, status, owner after Josh says so. *(as built)* `update_project` also creates a project Josh announces outright ("Bad Frog is Kristen's main thing") |
| `propose_character_change` | Saves a proposed new profile version with a change summary. One proposal at a time |
| `decide_character_change` | Activates or rejects a proposal — only after Josh has said yes or no in the conversation. *(as built)* Enforced in code: a proposal can't be decided in the same reply that made it |
| `update_interview` | Marks interview areas in progress or covered, with notes |
| `cost_report` | Sums `costs` for a period in SQL — no model involved in the maths |
| Web search | Anthropic's server-side web search through `laravel/ai`, `max_uses` capped in config |

### Topic tagging

`TagTopics` (Haiku 5.5, structured output) runs after each exchange. It's given the exchange plus the ~10 nearest existing topics (and any topic the exchange names) and returns which of them apply and any new topic names. Code links the messages, creates new topics (with embeddings), and updates `last_discussed_at`. Topic summaries are refreshed by the daily extraction, not here. This resolves the domain model's open question: topics are assigned at write time and consolidated daily.

### Daily extraction

Scheduled daily at 03:00 Josh's time.

1. Collect messages since the last run's `window_end`. None → done. *(as built)* The window stops just short of any exchange Donna is still answering, so its reply is extracted with it next time.
2. Build Batch API requests. *(as built)* Always one request per day of conversation, in Josh's timezone. Each carries the messages, the People list, active projects, and the active facts most related to that day's topics, with IDs — chosen in code, most specific first: facts about the day's topics, the projects they belong to, and the people and projects the day names; then facts sharing the day's words (full-text); then the newest facts about Josh and Donna, up to 60.
3. Structured output returns: new facts (statement, kind, confidence, subjects, source message IDs), supersessions (old fact ID → new statement, `notable_change`; applied to the fact's current version, since an earlier day in the same run may already have replaced it), people to create or update, topic summary updates, topic–project pairings, project candidates (become `proposed` projects Donna brings up), shared-history moments (facts about Donna), and interview coverage.
4. A polling job checks the batch every 10 minutes; when it ends, apply all results in one transaction, embed new facts and topics, and record costs at batch pricing. *(as built)* If a day's request fails, the days before it are applied and the run's window ends just before the failed day, so the next run retries from there — nothing is skipped and nothing is extracted twice. If no day succeeds the run is marked `failed` and the next run covers the same window. A new run never starts while one is still in flight. Embedding happens before the transaction opens; if it fails the run is retried at the next poll, and the batch's cost is only recorded once.
5. Only confident results are written as facts; low-confidence ones are saved with their confidence so Donna can confirm them when they come up.
6. The first run backfills everything since the first message.

### Cost tracking

- Every model call records a `costs` row — replies (each agent step), tagging, extraction, and embeddings. *(as built)* Agent calls are recorded by a listener on the SDK's `StepCompleted` event; embeddings all go through one `Embedder`, which records its own; batch results are recorded when a run is polled. An architecture test requires every agent to declare what its calls are for.
- Price input, cache writes, cache reads, and output separately (the SDK reports cache tokens as a subset of input). Batch requests price at 50%.
- A pricing table in `config/donna.php`, per model, USD per million tokens. As of November 2026 from Anthropic's docs: Sonnet 5.5 $2 in / $10 out / $0.10 cache read; Haiku 5.5 $0.10 in / $0.50 out / $0.01 cache read (up to 100K context). Cache writes are 1.25× input. *(as built)* Confirmed against Anthropic's pricing page in November 2026. Also in the table: Haiku 5.5's higher card for prompts over 100K tokens, Sonnet 5 (where a refusal fallback can land), web search at $10 per 1,000 searches, and `voyage-4` at $0.06/M — priced at list even while its first 200M tokens are free. A model missing from the table is still recorded, at $0, and reported. One known gap: when Anthropic's refusal fallback kicks in, only the attempt that produced the answer is recorded; the declined attempt is reported separately by the API and is not yet read.
- Set a hard monthly spend cap on the Anthropic key in the console (Josh, not code).

### Deployment

- Forge-provisioned DigitalOcean droplet, Basic 2 GB / 1 vCPU ($12/mo), with a swap file: PHP 8.4, Postgres with the pgvector extension installed, Nginx, Let's Encrypt for `donna.joshevensen.com`.
- Queue worker daemon for `replies,default`; scheduler cron.
- Backups: droplet backups on, plus a nightly database dump to Spaces (Forge database backups, or a scheduled `pg_dump` command if the plan lacks them).
- Env: `ANTHROPIC_API_KEY`, `VOYAGEAI_API_KEY`. Credentials are never logged and never sent to a model. *(as built)* Spaces credentials live in Forge's backup configuration, not the app's environment: the app doesn't touch Spaces until Files (Phase 3).
- Instructions in `docs/instructions/` for everything outside the repo (keys, Forge, DNS, backups, devices), including when to resize: memory regularly above ~80% or steady swap use → CPU/RAM-only resize (reversible; never grow the disk).

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

- Follow the Testing rules in the root `CLAUDE.md`: each test maps to a story or invariant, one test per behavior, no prompt-wording tests, run the narrowest tests after each change and the full suite after each larger area.
- Tests run against Postgres with pgvector (not SQLite) so vector and full-text queries are real.
- Fake every model: `Donna::fake()`, `Embeddings::fake()`, and a fake batch client. No test calls a real API.
- Cover: the message lifecycle and polling, reopening after a missed reply, context assembly (recent window, hybrid retrieval, superseded facts, stale topics, project boost never filtering), each tool, supersession vs. forget, character proposal and approval, extraction applying results and backfilling, cost rows for every call path and the cost report, eval flagging, and `donna:chat` against `Http::fake()`.

### Not in this phase

Streaming replies, the app, push, scheduled check-ins, memory curation, an eval runner, and durable workflows.
