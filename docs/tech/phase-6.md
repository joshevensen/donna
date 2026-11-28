# Phase 6 — Orchestration + Code Work

Donna runs the work, not just watches it. Josh hands her a specced issue and comes back to a pull request. Scope is in [`phases.md`](../phases.md); concepts in [`domain.md`](../domain.md); the read-only code awareness this builds on is Phase 4, and the trust and GitHub writes it depends on are Phase 5.

**Status:** draft, started 2026-11-28. Only the code worker has been discussed so far. The user stories below are proposed from `phases.md` and still need Josh's pass; the rest of the design (jobs, workers, durable workflows, budgets) hasn't been written yet. The main proposal here, running code work as Claude Code **cloud scheduled tasks** in place of headless Claude Code on a droplet, changes the Vision and the stack table, so it needs Josh's decision before anything is built. See [Vision changes this needs](#vision-changes-this-needs).

---

## User Stories

*Proposed, for Josh to edit.*

### Handing off code work

- I tell Donna "build #42 while I'm out" or add the label for it on GitHub, and she starts the run. I don't open Claude Code.
- She checks the issue is specced before starting. If it isn't, she says so instead of starting a run that will guess.
- When the run opens a pull request, she tells me in her voice, with the link, what changed, and whether CI is green.
- She never merges. When it's ready, she asks.

### Gates

- When Orc reaches a gate (approving the plan, a walkthrough question), Donna asks me in the web chat, and a push notification says there's a question. My answer moves the work on.
- If I don't answer, nothing happens. The work waits and she doesn't keep pestering me.

### Knowing where things stand

- "What are you working on?" gets every job in flight: which issue, which stage, waiting on me or running, and how long it has been going.
- If a run fails or stalls, she says so plainly and suggests what to do next. She doesn't retry quietly forever.

### Cost

- She tells me what code work costs me, against my subscription or the API, whichever paid for it.
- A runaway job can't burn money or usage unattended. Every job has a budget.

### Not in this phase

- Merging, ever.
- Driving the interactive Claude Code interface. Interactive Orc steps stay with Josh until the token-economics question is settled.

---

## Design

### Code worker: Claude Code cloud scheduled tasks

*Proposed 2026-11-28. Facts here are from Anthropic's docs as of that date, at code.claude.com/docs/en/routines. The feature is a research preview, so recheck before building.*

Anthropic now runs Claude Code sessions on its own infrastructure as **cloud scheduled tasks** (previously called routines). A task is a saved prompt plus one or more repositories, a cloud environment and a set of connectors. Every run is a full, autonomous Claude Code session: it clones the repo, runs shell commands, uses the skills committed to the repo, and pushes to a `claude/`-prefixed branch.

A task can have any mix of three triggers:

- **Schedule**: hourly at most.
- **API**: a POST to the task's own `/fire` endpoint, with that task's bearer token. An optional `text` field carries run-specific context. The response contains the new session's ID and URL.
- **GitHub event**: issues (opened, labeled, and the like), pull requests and releases, with filters. The Claude GitHub App has to be installed on the repo.

**Why this over a droplet:**

- **It runs on the Max subscription, not the API.** Runs draw subscription usage the same way interactive sessions do. The API trigger's token only starts that one task; it isn't an API key and isn't billed as API usage.
- **No second server to run.** No Claude Code install to keep current, no Orc plugin to update, no droplet hardening.
- **Orc comes with the repo.** Skills committed under `.claude/skills` load automatically in each run. For skills that live outside the target repo, the environment's setup script installs them (its result is cached), or a SessionStart hook copies them in from a second cloned repo.
- **It's an Anthropic-provided headless channel**, which keeps it inside the Vision's "No workarounds" rule.

### How Donna starts a run

Donna starts each run through the **API trigger**. GitHub labels show its state.

1. Josh asks, or Donna proposes and Josh confirms (Phase 5's Graduated Autonomy; starting a run is a new Action Type).
2. Donna adds the stage label to the issue and POSTs to the task's `/fire` endpoint with the issue number and stage in `text`.
3. She stores the returned session ID and URL on the Job's Worker.
4. Phase 4's GitHub watching picks up the branch, the PR and CI, and Donna reports.

The API trigger comes first, not a GitHub label trigger, because a durable workflow needs Donna to know a run started. A GitHub trigger would start sessions she never hears about, and GitHub events above an hourly cap are dropped without notice. The label still records the stage, so Josh can see it on GitHub. Whether a label trigger is also worth adding (for example, so Josh can start a run from GitHub on his phone) is an open question.

The saved prompt has to tell Claude to act on the fire payload explicitly. `text` arrives wrapped in a `<routine-fire-payload>` block marked as untrusted data, and Claude ignores instructions inside it unless the prompt says to follow them. The prompt should treat the payload as an issue number and stage only, and take the work itself from the issue.

### Gates: one run per Orc stage

As far as the docs show, there is no API for sending an answer into a running session. A run that stops for approval waits in the Claude Code web UI, out of Donna's reach. So **each Orc gate ends a run**:

- **Plan run**: reads the issue, writes the plan as an issue comment (or into Orc's decision record), and stops.
- **The gate**: Donna asks Josh in the web chat. His answer is a durable-workflow signal.
- **Build run**: starts with the approved plan, builds, opens the PR, and stops.
- Further gates follow the same pattern.

The issue, its comments and its labels carry state between runs, so nothing depends on a session living on. This satisfies "Orc's gates become Donna questions in the web chat" without a long-lived session. Whether Orc's stages split cleanly at its gates needs checking against hexbyte-plugins.

### What this doesn't give us

- **Per-run cost.** Nothing returns tokens or dollars for a run. Josh can only see overall usage at claude.ai/settings/usage. The Vision's rule that every LLM call writes a row to `costs` can't hold for these runs. Proposal: record a `costs` row per run with the trigger and duration and no token figures, so Donna can count runs and time but not dollars. Per-job budgets become limits on runs and time, not USD.
- **Run status.** The docs describe no API for reading a session's status. Donna works it out from what she can see: the session ID she started, the branch, the PR and CI. A run that pushes nothing within a time limit counts as stalled, and she says so. *(unverified: whether any status endpoint exists)*
- **Rate limits.** 30 API fires per hour per task, 100 per hour per account. Parallel jobs fit easily, but a queue of jobs has to respect the limits.
- **Subscription limits.** When usage runs out, runs are rejected until the window resets, unless usage credits are turned on, in which case runs bill as metered overage.
- **Identity.** Everything a run does on GitHub appears as Josh.

### Vision changes this needs

These conflict with `vision.md` as written. Josh decides each one; the Vision is updated before building.

1. **Code Work → Unattended runs**: "headless on a droplet with Orc installed" becomes cloud scheduled tasks. The stack table's **Code worker** row changes to match.
2. **Running Costs → Subscription credit first**: says Donna spends the subscription's Agent SDK credit first and tracks the balance. Cloud tasks draw from the main Max usage pool instead, and there's no balance Donna can read. Needs rewording.
3. **Nothing depends on the subscription path**: the Vision requires an API-key fallback because Anthropic changes these rules. Proposal: put the code worker behind one interface with two drivers, the cloud-task driver now and a headless-Claude-Code-with-an-API-key driver later. Build the second only if the first goes away, so the fallback is designed for but not built. Josh should confirm that's enough.
4. **Cost tracking**: the per-call `costs` rule can't be met for these runs (see above).
5. **Self-hosted**: these runs execute on Anthropic's infrastructure, not Josh's server. Claude Code already ran there for interactive work, so this may not matter, but it's a change in where code work happens.

### Token economics

Phase 6 was to answer this with real usage data. This proposal mostly settles it: Donna's code work runs on the subscription, so the question becomes how many runs fit in Max usage alongside Josh's own interactive work, not subscription versus API. The data to collect: runs per week, run duration, and how often a run is rejected for usage limits.

---

### Open questions

1. **Adopt cloud scheduled tasks as the code worker?** The Vision changes above follow from this.
2. **Gates as separate runs**, or accept that mid-run questions get answered in the Claude Code web UI?
3. **One task per repo or one per stage?** A task's prompt is fixed, so either one prompt routes on the stage in the payload, or each stage gets its own task and token.
4. **Where Orc's skills live**: committed in each target repo, or one shared skills repo installed by the setup script or a hook?
5. **A GitHub label trigger as well**, so Josh can start work from GitHub without Donna?
6. **Budgets in runs and minutes** instead of dollars: acceptable?
