# Phase 3 — Files + Archive Memory

Josh attaches files in the chat as context for Donna: text, CSV, PDF and images. He reads them in a viewer and edits markdown files together with her. Donna works out which files are worth keeping, and those become something she finds on her own. Scope is in [`phases.md`](../phases.md); concepts in [`domain.md`](../domain.md); look and feel in [`design.md`](../design.md); the chat this builds on is [`phase-2.md`](phase-2.md).

**Status:** approved by Josh; not yet built. The open question at the end (skills' store) is deferred to Phase 4.

**Mockup:** eight screens on one canvas, [open the artifact](https://claude.ai/artifact/4R8ZFyMEguk2GUAeidqBj3) (private to Josh). The source is kept in [`phase-3/mockup/`](phase-3/mockup/) so the design survives if the link doesn't. They are static drawings of: the attach sheet, the upload chips (uploading, ready, refused), file cards in the thread with `/files`, the viewer for a kept markdown file, its edit mode with the save conflict, a CSV that isn't kept, a PDF, and the Mac layout with the chat and the file side by side. Where the mockup and this doc disagree, the doc wins.

---

## User Stories

### Attaching files

- I tap the plus in the input and choose a file or a photo. Any plain-text file (markdown, text, CSV, JSON, code), PDFs and images work, up to 10 MB each and five to a message. Files she can't read, like Word, Excel or zip, are refused with a reason.
- The file shows as a chip above the input while it uploads. If it can't be used, the chip says why ("Unsupported. Text, PDF and images only", "Too large. Max 10 MB") and I can remove it.
- I can send a file with no text. Donna looks at it and answers.
- In the thread my message carries the file as a small card at the top of its bubble. Tapping it opens it.

### Working on a file together

- Donna reads what I attach: a Stripe export, a Ravelry pattern, a PDF from a vendor. She answers questions about it, and for a CSV she gets totals and filtered rows right without guessing, because the arithmetic is done by code, not by her.
- I tell her what to change and she changes exactly that: "replace Fibermade Co with Fibermade everywhere", "add a section on returns", "rewrite the pricing section to be shorter". Small edits don't rewrite the whole file.
- I can open a markdown file and edit it myself, in a view that looks different from reading, then go back to the conversation and she knows what I changed. Other files I read; they are context, not documents we write.
- If I say "undo that", she puts the file back.
- Donna can create a file from scratch: "write that up as a plan".
- There's no delete button. I ask her to delete a file. She can bring it back if I change my mind.

### What she can tell me about a file

- She can look at an image or a scanned PDF again later, not only in the turn it arrived: "look at that screenshot again".
- She knows when a file arrived and which message it came with, so she can say "the screenshot you sent Nov 27 at 3:01", not "something you showed me". (There is one user, so who added it isn't recorded.)
- The original is never lost. What I uploaded is kept as the file's first version however much it is edited, and a PDF or image original is never changed at all.
- She tells me what she could and couldn't read. A scanned PDF with no text layer, a file too long for her to have seen all of, a page that didn't extract: she says so instead of answering as if she'd read everything.
- When a file is about a person, project or topic ("the Houston vendor PDF", "the Anita screenshots"), I can link it, and it comes up with them.

### What she remembers

- She works out for herself what is temporary. When I ask her to reformat a knitting pattern, she doesn't remember the pattern afterwards. When I give her a file as context (my notes, a plan, a vendor PDF, an export I'll refer to), she keeps it, and says so in a line, so I can tell her to forget it.
- I can still tell her ("keep this", "forget that one"), and a switch in the viewer does the same.
- A kept file shows a **Kept** badge on its card wherever it appears, so a wrong keep is easy to spot without reading her reply. Files about people or money are where that matters most.
- When a kept file disagrees with something she already knows, she says so, naming both and where each came from, and asks which is right when it matters. She doesn't quietly pick one.
- Kept files are found on their own: when a topic comes up she draws on them, and "what I looked at" shows which file and which section.
- When I stop keeping a file she stops finding it. The file itself stays.
- I can ask her what she can do with files, and what's there, and she answers accurately.

### Finding files

- I ask "show me my files" or "open the Fibermade plan" and she answers with the files as cards. `/files` lists them without involving her.
- Kept files are listed first, then the temporary ones I've used recently.

### Not in this phase

- Folders or tags. Names, links to people and projects, and Donna's search are the structure.
- A history screen. Versions are kept and Donna can undo, but there's no UI to browse them.
- Editing anything but markdown and plain text: CSVs, PDFs and images are read, not changed. Word, Excel and zip files, and OCR as a feature.
- Sharing files outside Donna, and exporting everything at once. A single file can be downloaded.
- A file viewer in the terminal. The terminal can't attach.
- Skills, which are also files but arrive with the skills loader (Phase 4).
- Deleting forever, and any automatic deletion. See the open questions.

---

## Design

Where this section is silent, follow Laravel and React best practice, `CLAUDE.md` and the Boost guidelines, and note notable choices in the PR. If something turns out to be wrong in the code, deviate, say why in the PR, and update this doc in the same PR.

### Delivery

- Build on a branch with a draft PR and a checklist from this doc, ticked per push, as `CLAUDE.md` says. Never merge.
- Build order for the checklist: (1) storage, the `files` tables and the upload API; (2) extraction by kind and the attach flow in the web app; (3) Donna's file tools, versions and manual editing; (4) the viewer, cards and `/files`; (5) Archive Memory: chunking, indexing, retrieval and sources; (6) instruction files.
- API in Pest feature tests, web in Vitest and a few Playwright flows with the API mocked, as in Phase 2.

### What's decided and why

| Decision | Choice | Reason |
|---|---|---|
| Temporary or kept | Every file starts **working**. Donna decides which become **kept**, and says so; only kept files are chunked and embedded. | A reformatted pattern shouldn't be remembered, a context document should. Keeping is reversible and internal, so she needs no confirmation, and a wrong keep costs less than a wrong skip. |
| Where the bytes live | Text files in Postgres, as versions. Original PDFs and images in DigitalOcean Spaces, in a private bucket of their own. | Edits are small, frequent, versioned and must change a file and its chunks together, which one database transaction does. Binaries never change, and Spaces keeps them off the droplet's disk, which can't be shrunk. Text living in the database departs from the stack table's "file storage: Spaces" for that part; the table is updated in the build PR. |
| Versions | Every change creates a version: Josh's saves, each of Donna's edits, a revert. | "Undo that" and "I edited it, continue" both need it. Cheap for text of this size. |
| Simple edits | Donna's tools do find-and-replace, append, delete lines and rename in code. Rewriting a section is her judgment, but applying it is code, by heading. | The vision's rule: no tokens spent on deterministic work, and no rewriting a whole file for a small edit. |
| CSV maths | A `query_csv` tool: filter, sort, group, count, sum, average, evaluated in PHP. | `CLAUDE.md`: totals and filtering are always code. A Shopify export with thousands of rows can't go into a prompt anyway. |
| PDFs | Text extracted by code and stored as the file's one version. A PDF with no text (a scan) is shown to the model directly in the turn it arrives. | Most PDFs Josh uploads (Stripe, Shopify) have text. The model only reads pixels when there are no words to extract. |
| Images | Stored, shown in the thread and viewer, and shown to the model in the turn they arrive. Not indexed. Donna can view one again later with `view_file`. | Nothing to chunk, and Josh doesn't need image search. But she can't answer about a screenshot she saw once, so she can look again on request. |
| Uploading | Two steps: `POST /files` per file, then `POST /messages` with the file IDs. | Matches the chips in the mockup (progress and a per-file error) and keeps the message request small. |
| Archive search | The same hybrid search as messages and facts, over chunks, using the embedding already made for Josh's message. | No new kind of model call. |

### Kinds and limits

| Kind | Types accepted | Stored | Viewer |
|---|---|---|---|
| `markdown` | `.md`, `.markdown`, `.txt` | Postgres | Rendered; **edit mode** |
| `csv` | `.csv` | Postgres | Table, read-only |
| `text` | Any other plain text: JSON, HTML, code, logs | Postgres | Monospace, read-only |
| `pdf` | `.pdf` | Original in Spaces; extracted text in Postgres | The extracted text, with "Open original" |
| `image` | JPEG, PNG, WebP, GIF | Spaces | The image |

- 10 MB per file, five per message, the same limits in the client and the server. The server decides by sniffing the content (`finfo`), never by the extension or the browser's claimed type.
- iPhone photos arrive as JPEG when picked through the file input *(unverified: iOS converts HEIC when the input accepts `image/jpeg`; check on the device)*.
- A file counts as text if its content is valid UTF-8 with no binary bytes, whatever its extension. Anything else that isn't a PDF or image is refused. Text files must be valid UTF-8. A different encoding is converted if it can be detected, otherwise the file is refused with a message.

### Data model

| Table | Columns and notes |
|---|---|
| `files` | `id`, `name`, `kind`, `mime`, `size_bytes`, `storage_path` (null for text), `current_version_id`, `kept_at` (null while working), `deleted_at`, `extraction` (`ready`, `failed`), `extraction_error`, timestamps |
| `file_versions` | `id`, `file_id`, `number`, `body` (text), `content_hash`, `author` (`josh`, `donna`), `message_id` (null), `summary` (what changed, written by code, e.g. "Replaced 14 matches of 'Fibermade Co'"), `created_at`. A PDF has one; an image has none |
| `file_chunks` | `id`, `file_id`, `position`, `heading_path` (e.g. "Pricing › Wholesale"), `body`, `content_hash`, `embedding` (vector 1024), `search` (tsvector, generated), timestamps. Current version only; rebuilt on change |
| `file_subjects` | `file_id`, `subject_type`, `subject_id`: a file linked to people, projects or topics, the same polymorphic shape as `fact_subjects`. Written by `link_file`; read by retrieval and the viewer |
| `file_message` | `file_id`, `message_id`, `role` (`attached`, `created`, `edited`, `opened`). What Josh attached and what Donna touched in a turn |

Every table is written by something: `files` and `file_versions` by uploads, manual saves and Donna's tools; `file_chunks` by the indexing job; `file_subjects` by `link_file`; `file_message` by the message endpoint and the tools.

**Provenance** is already in these rows: when a file arrived (`created_at`) and in which message (`file_message`). **Originals** are kept: version 1 of a text file is exactly what was uploaded or first written, and is never edited or removed; the original bytes of a PDF or image are never touched. Nothing is defined that nothing fills.

### Upload and file API

All under `/api/v1`, JSON, `auth:sanctum`.

| Endpoint | Detail |
|---|---|
| `POST /files` | Multipart, one file. Validates size and content, stores it, extracts text, returns the file. Throttled (30 a minute). The file belongs to no message until one names it |
| `GET /files` | The list for `/files` and Donna's cards: kept first, then working files from the last 30 days; `?q=` filters by name and also finds older working files |
| `GET /files/{id}` | Metadata, the current version's number and, for text kinds, its body |
| `GET /files/{id}/content` | The bytes, for image display and "Open original". Served by the app after the session check, never as a public Spaces URL, with `X-Content-Type-Options: nosniff` and, for anything but images and PDFs, `Content-Disposition: attachment`. Read from the Spaces bucket |
| `PUT /files/{id}` | Manual save: `{body, base_version}`. 409 with the current version if `base_version` is stale. Markdown only |
| `PATCH /files/{id}` | `{kept?, name?}`: the keep switch and rename |
| `POST /messages` | Gains optional `file_ids` (up to five of the user's uploaded, unattached files). `body` may be empty when there are files |
| `MessageResource` | Gains `files: [{id, name, kind, role}]` for what was attached and what Donna created, edited or opened |

There is no `DELETE`. Deleting is a tool of Donna's.

### Working files and kept files

- A new file is working: stored, visible, usable in the conversation, never indexed.
- It becomes kept when Donna calls `keep_file`, or Josh flips the switch in the viewer. Both set `kept_at` and queue indexing.
- **Donna decides, and says so.** `operating-instructions.md` tells her: a file Josh gives her as context or reference (notes, plans, vendor documents, exports he'll return to) is kept; a file he asks her to transform, or one she writes as a one-off, is not; and when she keeps one she says so in a line, so he can tell her to forget it. She judges from what he asked, in her normal reply, with no extra model call.
- Un-keeping removes its chunks and nothing else. If Josh un-keeps a file she kept, she doesn't keep it again unless he asks.
- Facts learned from a conversation about a file are still extracted from the conversation by the daily run, as for anything else Josh says. Files themselves are never read by extraction; Archive Memory and Facts stay separate layers.
- **Nothing is deleted automatically**, working files included (`domain.md` invariant). See the open questions.

### Editing

- **Donna's edits** go through tools, each of which writes a new version with a `summary`. Her tools are listed below. A tool works on the file's current content when it runs, so an edit Josh made a moment ago is never overwritten by a stale copy.
- **Josh's edits** happen in the viewer's edit mode: a monospace textarea on Inset, so it is obvious which mode he is in. Saving posts `base_version`. If Donna changed the file in the meantime the save is refused with a 409 and the viewer offers "Keep mine" (saved as a new version on top, hers still in history) or "Take Donna's".
- **Donna learns what Josh changed.** For files in play, the context holds, in code-written text, a diff of Josh's changes since Donna last saw the file (capped), so she knows without being told. If it is too large to include, she gets the headings that changed.
- The viewer refreshes by itself while Donna is writing a reply that touches the open file (the same polling as the thread).
- **Undo:** `revert_file` restores a version by writing it as a new one. "Undo that" reverts to the version before her last edit.

### Donna's tools

Each does its work in code; none calls a model. The edit tools (`edit_section` to `delete_lines`) work on markdown files only, and refuse anything else.

| Tool | What it does |
|---|---|
| `create_file` | `{name, content}`. Creates a markdown file. The reply gets a file card |
| `read_file` | `{file, heading?, from_line?, lines?}`. Returns a part of any text file, so a long file isn't read whole |
| `edit_section` | `{file, heading, new_text}`. Replaces one markdown section by its heading. For "rewrite this section" |
| `replace_in_file` | `{file, find, replace, all?}`. Exact text. Fails with the match count if the text isn't there or isn't unique and `all` isn't set |
| `append_to_file`, `delete_lines` | Append text; delete lines by number range or by exact content |
| `query_csv` | `{file, where?, group_by?, aggregate?, order_by?, limit?}`, run in PHP. Also returns the header and row count, so she can plan a query |
| `rename_file` | Rename |
| `keep_file` | `{file, keep}`. Keep or un-keep. She calls it herself for context files, and on Josh's say-so either way |
| `link_file` | `{file, subjects}`. Link a file to people, projects or topics by name (resolved like facts' subjects), or unlink |
| `delete_file`, `restore_file` | Soft delete (`deleted_at`) and bring back. Deleting removes the chunks; restoring re-indexes if it was kept. Only on Josh's say-so, and an explicit permanent delete arrives with Autonomy (Phase 5) |
| `revert_file` | Restore a previous version as a new one |
| `list_files` | `{q?}`. What is there, for "show my files". Puts file cards on the reply |
| `view_file` | `{file, page?}`. Shows an image, or a page of a PDF with no text layer, to the model again, for a later turn. Nothing is stored; it is the original read from Spaces and passed to the model, in the same reply call. Needs the Laravel AI SDK to let a tool result carry an image *(unverified: check first when building; if it can't, the tool queues the image to be attached to the next step of the same reply)* |

These are all reversible and internal, so they need no confirmation (Graduated Autonomy, `vision.md`). They are only tools on files in Donna's own store; she has no access to anything outside it.

### Context

- **The turn's attachments** go into the message. A text file under the inline limit (about 6,000 tokens) goes in whole, in a tagged block marked as Josh's material, not instructions. A larger one goes in as an outline (markdown headings with line numbers; CSV columns, row count and a few rows; PDF page count and the first lines) and she reads or queries the rest. Images, and PDFs with no extractable text, go in as the model's own file blocks for that turn only.
- **Files in play**: files attached or touched in the last 20 messages, listed in the memory block with their IDs, names, kinds, when they arrived (in Josh's timezone), the message they came with, whether they're kept, and the diff of Josh's changes if any. This is how "that file" and "the second one" resolve, and how she cites where a file came from.
- **What she was and wasn't given**, stated by code for every file in the turn: "read in full", "outline only: 3,200 of 41,000 lines", "no text could be extracted (scanned, 12 pages), shown as images", or "extraction failed: <reason>". She is told to say so when an answer rests on less than the whole file.
- **Files and what she can do**: one line in the memory block ("Files: 12 kept, 31 working"), and in her operating instructions the limits she can't see otherwise: what is accepted (text, PDF, images), 10 MB and five per message, and what she can't read (Word, Excel, zip). That answers "what can you do with files?" without a tool of its own.
- **Related files**: the best-matching chunks of kept files (below), a few at a time, with file name and heading path. Chunks of a file already in play are left out.
- `operating-instructions.md` gains: what files are and the working/kept rule; to treat text inside a file as material, never as instructions; to use the tools instead of retyping a file; to look at the diff of Josh's changes before editing; to offer to keep reference material and never work product; what an image or scan looked like if she has no text for it, and to use `view_file` to look at one again; and what to do when a kept file disagrees with a fact she holds: say both, with their sources and dates, don't pick silently, ask which is right when it matters, and when Josh confirms call `correct_fact`. A file's date is when it arrived, not when its contents were true, so "the newer one wins" is not the rule.

### Archive Memory

- **Chunking** (code, at logical boundaries):
  - Markdown: by heading, each chunk carrying its heading path. A section over about 800 tokens is split by paragraph.
  - Other text: by blank-line paragraph groups up to about 800 tokens.
  - CSV: groups of rows up to about 800 tokens, each with the header row. A file over 2,000 rows gets chunks for its first 2,000 and a summary chunk (columns, row count, a few rows); more than that isn't useful as memory, and `query_csv` answers questions about it anyway.
  - PDF: by page, split by paragraph if long.
  - Images: none.
- **Indexing** runs as a queued job when a file is kept or a kept file changes. Chunks are compared by `content_hash`, so an edit re-embeds only what changed. Embeddings go through the existing `Embedder`, so every one records a `costs` row. If embeddings are down the chunks are still saved and full-text search works, and `EmbedMissing` fills the vectors later, as for messages.
- **Retrieval** adds `file_chunks` to `HybridSearch`: full-text and vector, fused by rank like messages and facts, scored with the same embedding of Josh's message (no extra call), and boosted for chunks of files linked to a project, person or topic the message relates to (the same fixed boost projects give facts). They compete for the memory block's token budget with facts and exchanges.
- **Sources**: a chunk Donna was given appears in "What I looked at" as a `file` source labelled "File name › Heading". Tapping it opens the file.
- A deleted or un-kept file contributes nothing; its chunks are gone.

### The web app

- **The plus returns** in the input, bottom left, as in the mockup: the collapsed bar, the open field and the sheet ("Choose a file", "Choose a photo"). The two choices open the file picker and the photo picker. This reverses Phase 2's "no plus".
- **Chips** above the text, one per file, with a remove button. Upload starts on picking; a chip shows progress, then ready, or an Error border with the reason. Send is disabled while any chip is uploading or has an error. A message may be only chips.
- **Sent files** show as a card at the top of the bubble: icon, name, size. Files Donna created, edited or opened show the same card under her bubble. Tapping a card opens the viewer. An edit's card says what changed ("Edited · 14 replacements"). A kept file's card carries a small **Kept** badge, in the thread and in `/files`, following the keep switch, so a keep Donna made on her own is visible without reading her reply.
- **The viewer.** On a phone, a full-screen sheet over the chat. At 768px and wider, a side panel: the chat keeps its width on the left and the file takes the right half, so the 600px centred column becomes two columns while a file is open. Header: the file's name, a **Keep** switch, Edit and Close. Body by kind: rendered markdown (the same safe renderer as replies; images and raw HTML are not shown), a table for CSV, extracted text with "Open original" for a PDF, the image. Edit mode (markdown only): Inset background, monospace, Save and Cancel, and a conflict banner when a save is refused. Metadata (created, modified, size, who edited last, version) expands at the bottom.
- **`/files`** is a slash command handled in the client, no model call: it lists files as cards in a local message. Anything else about files goes through Donna.
- The CSP stays as is: images and PDFs load from the app's own address through `GET /files/{id}/content`.
- The keyboard behavior from Phase 2 applies to edit mode's textarea too; it is a second input that has to sit above the keyboard.

### Security

- Uploaded files are untrusted: content-sniffed, size-limited, never served with a type the sniff didn't confirm, and served only to a signed-in session. Names are shown as text and never used as paths; storage paths are generated.
- Text inside a file is data, not instructions. This is stated to Donna in her operating instructions and the file's text is delimited in the prompt. Her file tools can only touch files in her own store, so the worst a hostile file can do this phase is change other files; the rules for acting outside arrive with Autonomy.
- Markdown renders without raw HTML or images, as in replies.
- Files are Josh's financial and business data (Stripe, Shopify). They go to Anthropic when a turn includes them, as anything he types does; they never go in a log.

### Code vs model in this phase

| Code | Model |
|---|---|
| Type sniffing, limits, text extraction, encoding | Understanding what Josh wants done to a file |
| Every file operation: replace, append, delete lines, rename, section replace, revert | The new text for "rewrite this section", "write that up as a plan" |
| CSV queries and totals | Which question to ask of a CSV |
| Chunking, hashing, indexing, hybrid search, token budgeting | Deciding whether a file looks like reference material worth offering to keep |
| Diffs of Josh's edits, version summaries, conflict detection | Reading a scan or a photo (in the same reply call), including looking at one again |
| The kept and working counts, the "Kept" badge | Noticing that a file disagrees with a fact, and saying so |

No new kind of model call. Embedding chunks is the existing embedding call and records its own `costs` row.

### Data model changes in existing tables

`messages` gains nothing; `file_message` links them. The memory block, `ReplyContext` and `ContextSources` gain the file sections above.

### Deployment and instructions

- **New `docs/instructions/spaces.md`**: a DigitalOcean Space for the app's files, separate from the backup Space, with its own access key limited to that bucket (so the app's key can't touch the backups); private, because files are served through the app; the environment variables (`DONNA_SPACES_KEY`, `DONNA_SPACES_SECRET`, `DONNA_SPACES_REGION`, `DONNA_SPACES_BUCKET`, `DONNA_SPACES_ENDPOINT`); and a check. Adds `league/flysystem-aws-s3-v3` and a `files` disk in `config/filesystems.php`. In tests and local development the disk is the local one.
- **Backups.** Spaces is durable on its own, and the database dumps hold all text files and versions. `backups.md` notes the app's Space isn't part of the dumps.
- **Upload limits on the server**: PHP's `upload_max_filesize` and `post_max_size` and Nginx's `client_max_body_size` must allow 10 MB per file and five files *(unverified: Forge's defaults are higher than this; check)*. Written into `forge-site.md`.
- **PDF text extraction**: a pure-PHP package, chosen when building after trying it on real Stripe and Shopify PDFs *(unverified: quality on those)*; if it isn't good enough, `pdftotext` (poppler) is installed on the droplet and `forge-server.md` says how.
- **Updated**: `environment-variables.md` (the `DONNA_SPACES_*` variables), `forge-site.md`, `backups.md`, `verify-setup.md` (attach a file, edit a markdown file, see it kept, get it back), `operating.md` (what working files accumulate and the storage bill), and a `README.md` row for `spaces.md`.
- `CLAUDE.md`'s stack row for file storage, `domain.md`'s File concept and invariants (working and kept, versions), `design.md`'s viewer and attachment components and `vision.md`'s Files section where the build departs from it are updated in the build PR.
- The build PR's "Before deploying" lists these in order.

### Testing

Follow the Testing rules in `CLAUDE.md`. Fake every model and `Storage`; no real network.

**API (Pest feature tests):**
- Upload: each kind accepted and extracted (including an unusual text file by content, not extension); a Word or zip file, a wrong type, a file over 10 MB, and a file whose content disagrees with its extension refused; throttling.
- Attaching: files go with the message; another message can't reuse them; a message with only files works.
- Working vs kept: a working file is never chunked or embedded; keeping it queues indexing; a file kept by Donna's tool is reported in her reply; un-keeping and deleting drop its chunks; restoring a kept file re-indexes.
- Tools, through the reply job: each operation writes a version with its summary; `replace_in_file` fails on zero and on ambiguous matches; `edit_section` replaces only its section; `revert_file` restores; delete and restore.
- Manual save: writes a version; a stale `base_version` returns 409 and changes nothing; a CSV or PDF is refused. A Donna edit after Josh's save keeps his change.
- Context: Josh's changes since Donna last saw the file appear as a diff; an attached file is inlined under the limit and outlined over it.
- `query_csv` (a unit test: real branching): filter, group, aggregate and order against a fixture.
- Chunking (a unit test): markdown by heading and by size, CSV groups with the header, PDF pages; unchanged chunks keep their hash and aren't re-embedded.
- Retrieval: a kept file's chunk comes back for a related message and is recorded as a source; a working file's never does; a chunk of a file linked to a related project is ranked above an otherwise equal one.
- Provenance and honesty: the context states when a file arrived and with which message, and whether it was read whole, as an outline, as images, or failed; a scanned PDF is flagged rather than passed as empty text.
- Originals: after any number of edits and reverts, version 1 is unchanged and retrievable.
- `view_file`: returns the image or the requested PDF page for a kept or working file; refuses a text file and a file that isn't there.
- The message resource marks each file card kept or not, and a keep or un-keep changes it.
- The memory block carries the kept and working counts; they change when a file is kept.
- Content endpoint: session required; correct headers.

**Web (Vitest):** chip states and errors, the card, viewer modes and the conflict banner, `/files`. **Playwright with a mocked API:** attach a file and send, a bad file's chip, open a file and edit it, a save conflict. The photo picker and the keyboard in edit mode are device checks listed in the PR.

### Risks to check early

- **Images in tool results.** `view_file` depends on the SDK letting a tool return an image; check before building, and use the fallback in its row if not.
- **PDF text from real files.** Stripe and Shopify PDFs vary; try the extractor on a handful of Josh's before building on it.
- **The photo picker on iOS**, in Safari and the installed app: which formats arrive, and whether the file input works from the Home Screen app. Device check.
- **The viewer beside the chat on a Mac** changes the Phase 2 layout; the keyboard and scroll behavior shouldn't regress. Re-run the Phase 2 device checks.
- **Token cost of files in context.** The inline limit and outlines exist for this; watch real `costs` rows in the first week and move the limit.
- **Spaces from the server.** The bucket, key and region have to be right before the first upload can work; `spaces.md` ends with a check that stores and reads back a file.
- **Embedding cost of large kept files.** The CSV caps exist for this; the first big kept export will show whether they're right.

### Open questions

Settled in the discussion: any text file is accepted but only markdown is edited; Josh doesn't use Word, Excel or Pages, and rarely Google Docs, so there are no extractors for them (a Google Doc is attached as PDF or markdown); Donna decides what to keep and says so; originals live in Spaces; provenance is when and which message, not who; **nothing is deleted automatically and working files don't expire**, so deleting unneeded files is left for a later phase; no lock on files, the preserved original is enough; PDFs are shown as extracted text plus "Open original" for now; images aren't searchable.

1. **Skills (Phase 4) are files too.** I've assumed they get their own store, not this one.
