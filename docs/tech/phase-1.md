# Phase 1 — Foundation, Conversation + Memory

Donna runs on the real server, every call is accounted for, and she remembers. Scope is in [`phases.md`](../phases.md); concepts in [`domain.md`](../domain.md); her character in [`api/resources/prompts/donna.md`](../../api/resources/prompts/donna.md).

**Status:** user stories drafted — awaiting Josh's approval. Design not started.

---

## User Stories

### Talking to Donna

- I talk to Donna from a terminal on my Mac. She answers in her own voice, as defined in her character profile.
- There's one conversation. I never start a new chat, and nothing is siloed by thread or project.
- Closing the terminal never loses anything. Every message is saved the moment it's sent, and if I close mid-reply, Donna still finishes it.
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

## Design

*Full design written after the user stories are approved. Decisions made so far:*

### Replies run on the server

Josh's message is saved on receipt; Donna's reply is generated in a queued job and saved when done. The client waits for it, but closing the client never stops it.

### Cost

Phase 1 does the cheap, structural things and leaves the rest until the `costs` table shows where the money actually goes.

**In this phase**
- **Cost tracking** — every LLM call records a row in `costs`
- **Prompt caching** — the character profile and other stable context go first in the prompt and are cached
- **Batch API** — the daily extraction runs through it
- **One sensible model per job** — Sonnet for conversation; Haiku for small mechanical work (topic tagging, classification). A setting per job, not a routing system.
- **Spend cap** — a hard monthly limit on the Anthropic API key, set in the Anthropic console (not code)

**Not in this phase**
- Routing logic and token-trimming context tricks — the "absorb" layer; prices keep falling and one user's volume is small
- Per-job budgets — needed once Donna runs autonomous jobs (Phase 5)

Optimize later against real `costs` data, not guesses.
