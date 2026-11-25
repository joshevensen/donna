# Domain Model

The concepts Donna is built from, how they relate, and the rules that always hold. Covers every phase, so it stays thin: no columns, no indexes. Those belong in each phase's tech doc.

Source of truth is [`vision.md`](vision.md). If this doc disagrees with it, one of them is wrong — fix it, don't diverge.

---

## Concepts

### Conversation

- **Message** — one turn in the single, never-ending conversation. Author is Josh or Donna. Has a **source** (keyboard, dictation, Siri, quick capture, watch, notification reply) — Donna treats every source the same. May carry **Attachments**. Embedded for retrieval. There is no Conversation or Thread entity: there is only one.
- **Topic** — something Josh and Donna talk about over time (Fibermade, a trip, a person). Exists so Donna knows when a topic was last discussed and can open with a refresher. How often Josh comes back to a Topic is what defines his interests — interests are tracked, not declared.

### Memory

- **Fact** — one thing Donna knows. About Josh (Personal Memory), a Person (Social Memory), or Donna herself (shared history, her own opinions). Has a **provenance** (message, daily extraction, interview, import, check-in) and a confidence. Can be superseded by a newer Fact.
- **Person** — anyone in Josh's orbit. Has names/aliases, a relationship to Josh, and Facts.
- **File** — a document that belongs to Donna, not to a message. MD or CSV. Split into **Chunks** at logical boundaries, each embedded (Archive Memory).
- **Skill** — a procedure Donna knows (Agent Skills format: `SKILL.md` + optional scripts/references). Origin: seed, learned, or imported. Status: proposed, active, retired. Integration skills use an Integration.

- **Character Profile** — who Donna is: taste, opinions, history. Versioned; changes are proposed by Donna and approved by Josh.

### Presence

- **Task** — something Josh (or Donna) needs to do. Comes from a Message (explicit capture or noticed in chat). May involve a Person, have a due date, and carry **act-when** conditions (business hours, at the Mac, at home). Status: open, done, snoozed, dropped.
- **Goal** — a commitment Josh made to himself, with a target date. Status: active, done, dropped, stalled. A stall has a reason: blocked, deprioritized, or avoided.
- **Nudge** — one surfacing of a Task or Goal to Josh, at a time and on a channel. Has a **tactic** (plain reminder, spark like an article or a pointed question) and an **outcome** (acted, snoozed, ignored) so timing and tactics can learn.
- **Context Signal** — what Josh is doing right now, reported by a Device: calendar block, location, driving, active device.
- **Device** — an iPhone, iPad, Mac, Watch, or the Home Node. Holds a push token and reports Context Signals.

### Autonomy

- **Action Type** — a kind of thing Donna can do (`email.send`, `calendar.hold`, `file.delete`). Has a **tier**: reversible-internal or external-irreversible.
- **Action** — one attempt to do something. Proposed → (awaiting confirmation) → approved / edited / rejected → executed / failed. Records whether it ran autonomously. This is the audit log.
- **Trust Grant** — permission to perform an Action Type without confirming, optionally scoped to a Person ("scheduling emails to Sarah"). Granted conversationally or accepted after Donna offers it. Revocable.

### Orchestration

- **Job** — a unit of work Donna runs on Josh's behalf, as a durable workflow. Started by a Message (or a schedule/event). Has a budget, a status, and a total cost.
- **Worker** — one step of a Job with scoped context, tools, skills, and permissions. Runs in the cloud or on the Home Node.

### Integrations and cost

- **Integration** — a connection to an outside service (Gmail, Calendar, GitHub, Stripe…). Owns encrypted **Credentials**.
- **Cost** — one LLM call: model, tokens in/out, USD, and what triggered it (a Message, Job, extraction batch, etc.).

---

## Relationships

```
Message ──< Attachment
Message >── Topic (many-to-many)
Message ──< Task / Goal / Fact          (where they came from)

Person ──< Fact
Fact ──> Fact                           (superseded_by)
File ──< Chunk

Task >── Person (optional)
Task / Goal ──< Nudge
Device ──< Context Signal

Action >── Action Type
Action >── Person (optional)
Action >── Job (optional)
Trust Grant >── Action Type, Person (optional)

Job ──< Worker ──< Action
Skill >── Integration (optional)
Integration ──< Credential

Message / Job / extraction batch ──< Cost
```

---

## Invariants

Rules that hold everywhere, regardless of phase or which code path is running.

**Conversation**
- There is exactly one conversation. Nothing creates threads.
- Context is assembled per Message from Memory — never from the raw running history alone.
- Every input surface produces the same Message. A new surface never changes Memory, Skills, or the conversation model.

**Memory**
- Facts are superseded, not silently overwritten, so Donna can explain what changed. *(Proposed — confirm in the Phase 1 tech doc.)*
- Memories, Skills, and Files are never deleted automatically.
- Messages older than 180 days move to cold storage but stay retrievable.

**Autonomy**
- An external-irreversible Action requires confirmation unless a matching Trust Grant exists.
- Moving money and permanent deletion are never offered as Trust Grants — only granted when Josh asks explicitly.
- Every Action Donna takes on her own is reported to Josh.
- A Worker can never do something Donna herself isn't trusted to do.
- Read access never implies write access. Reading email or texts grants nothing on the sending side.
- Enforced server-side. Clients can't bypass it.

**Character**
- Donna's Character Profile never changes without Josh's approval.
- Donna never claims feelings, needs, or a human life she doesn't have, and never engages romantically.

**Cost**
- Every LLM call records exactly one Cost. A Job's cost is the sum of its Workers' Costs.
- Deterministic work (fetch, filter, file ops, totals) never makes an LLM call.

**Privacy**
- Credentials never appear in Messages, Costs, logs, or prompts.
- Raw text messages never leave the Home Node; only extracted commitments and minimal context go up.
- Raw passive capture (email bodies, transcripts, audio) is discarded after a short window; the extracted Task or Fact is what's kept.

---

## Open questions

- **Topic** — how Donna assigns Messages to Topics (at write time, during extraction, or both).
- **Task vs. Action** — is a Task Donna completes herself just a Task with an Action attached, or something else?
- **Check-ins and refreshers** — are they Nudges, or their own concept?
- **Skill trust** — does a new Skill get its own Trust Grants, or inherit from the Action Types it uses?
