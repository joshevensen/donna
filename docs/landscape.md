# Landscape

Dated facts that inform Donna's design but go stale: where models are heading, current prices and hardware, and the projects worth studying. The vision holds the decisions; this holds the evidence. Revisit every major model generation.

---

## Where Models Are Heading

*Snapshot as of November 2026 — revisit with every major model generation.*
- **Longer autonomous work** — the length of task frontier agents can finish on their own (at 50% reliability) has been doubling fast: about every 7 months over six years, and closer to every 4 months on METR's current post-2023 fit. Multi-hour and multi-day jobs become routine, which is exactly what Orchestration and durable workflows are for.
- **Much cheaper intelligence** — inference at a fixed capability level has been falling roughly 5–10× per year. Today's careful model routing matters less every year; what costs Opus money today costs Haiku money soon.
- **Memory moving into the platform** — Anthropic shipped file-based memory for Managed Agents (April 2026) and "dreaming," a background pass where agents review past sessions and consolidate what they learned (May 2026). Generic memory mechanics are becoming a commodity.
- **Continual learning is still mostly context** — models that genuinely learn after deployment are an active research area, but forecasters expect mid-2027 commercial practice to still be retrieval and memory injection, not weight updates. Donna's memory layer stays load-bearing for the foreseeable future.
- **Voice is going native** — OpenAI and Google now ship speech-to-speech models with reasoning in the voice loop and sub-second turn-taking. Anthropic offers voice in its own apps but not a speech-to-speech API.
- **Computer use: short tasks yes, long tasks no** — agents score around 85% on the standard desktop benchmark, but on long, messy real-world workflows the best still finish only about one in five. APIs remain the reliable path; computer use is a fallback for services with no door.
- **On-device and frontier converging** — Apple's Foundation Models framework now spans its on-device model and server models like Claude behind one Swift API; local-for-private, frontier-for-judgment is becoming a platform feature.

---

## Current Figures

- **Agent SDK credit** — Josh's Max 5x plan includes $100/month, non-rolling, billed at API rates
- **Droplet** — ~$12/month to start
- **Home Node** — base M6 Mac mini, $899 with 16GB / 256GB; bump RAM to 24–32GB for headroom (~$900–1,300 total). M5 Pro (up to 64GB) only if running larger open-weight models locally ever becomes worth it — not needed for this design.

---

## Inspiration

The personal AI agent space has moved fast. Donna exists because Josh wants one built around him — his memory model, his assistant's personality, native on his devices, on the stack he knows best — not because nothing else exists.

**Hermes Agent** (Nous Research) — the closest thing to Donna, and the first thing to study. Open source (MIT), self-hosted, Python. Persistent agent-curated memory, user modeling across sessions, a closed learning loop that creates skills from experience and refines them during use, isolated subagents for parallel work, programmatic tool calling, built-in cron, MCP support, Agent Skills–compatible, and a gateway to 20+ messaging platforms. Supports Anthropic models.

What to borrow:
- **The learning loop** — how and when it decides to write a skill, how it refines skills after use, how it nudges itself to persist memory. Port the logic to Laravel; it's the single most valuable idea for Donna.
- **Skills** — Donna and Hermes share the Agent Skills format, so skills from the Hermes Skills Hub can be imported into Donna directly. Each one still gets reviewed before use.
- **Session search** — FTS5 full-text search with LLM summarization for cross-session recall. Donna's equivalent is Postgres full-text plus pgvector.
- **Programmatic tool calling** — collapsing multi-step pipelines into one code-execution step instead of many LLM round-trips.
- **The code itself** — MIT-licensed, so prompts, heuristics, and logic can be adapted freely with attribution. It's Python, so this means porting, not importing.

**Vellum** — open source, layered persistent memory (semantic + keyword hybrid retrieval), proactive reach-outs, multi-channel, isolated credential handling. Study the memory architecture before designing Donna's retrieval layer.

**Khoj** — open source, self-hostable "second brain." Strong document indexing over your own files, on Postgres + pgvector — same stack as Donna. Directly relevant to Files and Archive Memory.

**OpenClaw** — self-hostable, many messaging channels, large community plugin ecosystem. A reference for extensibility. Honest limitation: credentials live in a flat JSON file with model-level access — a security approach Donna improves on.

**OpenBrain** — open source personal memory database on Postgres/Supabase that exposes memory as an MCP server. A reference for making Personal Memory a queryable, portable layer — and an option for letting other AI tools (Claude, Claude Code) read Donna's memory.

**Mem0** — the leading memory library for AI agents, with published benchmarks (ECAI 2025) and a token-efficient algorithm (April 2026) that improved temporal query accuracy by 29.6 points. Worth understanding when designing passive extraction and curation.

**alfred_** (get-alfred.ai) — reference for proactive scheduling UX patterns in Presence.

**BlueBubbles and imessage-rs** — open-source Mac servers that expose iMessage over a local API. References (or components) for the Home Node's Messages access.

**Bee and Omi** — always-on wearables that turn conversations into reminders and tasks. Bee for the UX of passive capture; Omi (open source) as the likely hardware path for Passive Capture stage 3.

**Anthropic Managed Agents memory and dreaming** — file-based agent memory with change logs and rollback, plus scheduled self-review that consolidates past sessions into long-term memory. A reference for Donna's curation job — and a signal of what the platforms will commoditize.

**AlterEgo** — silent-speech wearable from MIT Media Lab research. The reference for Donna's eventual private input surface.
