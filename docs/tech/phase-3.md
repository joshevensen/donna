# Phase 3 — Files + Archive Memory

Josh attaches files in the chat as context for Donna: text, CSV, PDF and images. He reads them in a viewer and edits markdown files together with her. Donna works out which files are worth keeping, and those become something she finds on her own. Scope is in [`phases.md`](../phases.md); concepts in [`domain.md`](../domain.md); look and feel in [`design.md`](../design.md); the chat this builds on is [`phase-2.md`](phase-2.md).

**Status:** built; awaiting daily use. Where the build had to depart from this doc, the text below has been corrected and the change is listed under [What changed while building](#what-changed-while-building). The pickers, edit mode's keyboard, the viewer and "Open original" have not been checked on a real iPhone, and PDF text has not been tried on Josh's real Stripe and Shopify files. The open question at the end (skills' store) is deferred to Phase 4.

**Mockup:** eight screens on one canvas, [open the artifact](https://claude.ai/artifact/4R8ZFyMEguk2GUAeidqBj3) (private to Josh). The source is kept in [`phase-3/mockup/`](phase-3/mockup/) so the design survives if the link doesn't. They are static drawings of: the attach sheet, the upload chips (uploading, ready, refused), file cards in the thread with `/files`, the viewer for a kept markdown file, its edit mode with the save conflict, a CSV that isn't kept, a PDF, and the Mac layout with the chat and the file side by side. Where the mockup and this doc disagree, the doc wins. Boards 1, 2, 3 and 8 draw the input as it was before the full-width input with its bottom bar (spend line, small send button); the plus belongs in that bar's left column.

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
| Where the bytes live | Text files in Postgres, as versions. Original PDFs and images in the app's DigitalOcean Space (`donna-joshevensen`, already set up in [`storage.md`](../instructions/storage.md)). | Edits are small, frequent, versioned and must change a file and its chunks together, which one database transaction does. Binaries never change, and Spaces keeps them off the droplet's disk, which can't be shrunk. Text living in the database departs from the stack table's "file storage: Spaces" for that part; the table is updated in the build PR. |
| Versions | Every change creates a version: Josh's saves, each of Donna's edits, a revert. | "Undo that" and "I edited it, continue" both need it. Cheap for text of this size. |
| Simple edits | Donna's tools do find-and-replace, append, delete lines and rename in code. Rewriting a section is her judgment, but applying it is code, by heading. | The vision's rule: no tokens spent on deterministic work, and no rewriting a whole file for a small edit. |
| CSV maths | A `query_csv` tool: filter, sort, group, count, sum, average, evaluated in PHP. | `CLAUDE.md`: totals and filtering are always code. A Shopify export with thousands of rows can't go into a prompt anyway. |
| PDFs | Text extracted by code (`pdftotext`, with a PHP parser as the fallback) and stored as the file's one version, pages separated by form feeds. A PDF with no text (a scan) is shown to the model directly in the turn it arrives. | Most PDFs Josh uploads (Stripe, Shopify) have text. The model only reads pixels when there are no words to extract. |
| Images | Stored, shown in the thread and viewer, and shown to the model in the turn they arrive. Not indexed, and can't be kept. Donna can view one again later with `view_file`. A large photo gets a smaller copy on upload (longest side 1568px, turned upright), which is what the thread, the viewer and the model are given; the original is untouched. | Nothing to chunk, and Josh doesn't need image search. But she can't answer about a screenshot she saw once, so she can look again on request. A phone photo can be bigger than the model accepts, and it reads nothing above 1568px anyway. |
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
| `files` | `id`, `name`, `kind`, `mime`, `size_bytes` (a text file's is its current text's), `storage_path` (null for text), `current_version_id`, `kept_at` (null while working), `deleted_at`, `extraction` (`ready`, `failed`), `extraction_error`, `meta` (jsonb: page count, which pages of a PDF have no text, row count, whether a PDF has a text layer, an image's width and height and where its smaller copy is, and when keeping was last turned off), timestamps |
| `file_versions` | `id`, `file_id`, `number`, `body` (text), `content_hash`, `author` (`josh`, `donna`), `message_id` (the reply Donna made the change in; null for Josh's), `operation_hash` (identifies the change within that reply; null for Josh's), `summary` (what changed, written by code, e.g. "Replaced 14 matches of "Fibermade Co""), `created_at`. A PDF has one; an image has none |
| `file_chunks` | `id`, `file_id`, `position`, `heading_path` (e.g. "Pricing › Wholesale"), `body`, `content_hash`, `embedding` (vector 1024), `search` (tsvector, generated), timestamps. Current version only; rebuilt on change |
| `file_subjects` | `file_id`, `subject_type`, `subject_id`: a file linked to people, projects or topics, the same polymorphic shape as `fact_subjects`. Written by `link_file`; read by retrieval (the boost), by the files-in-play line Donna is given ("about Anita, Fibermade") and by the viewer's Details |
| `file_message` | `file_id`, `message_id`, `role` (`attached`, `created`, `edited`, `opened`). What Josh attached and what Donna touched in a turn |

Every table is written by something: `files` and `file_versions` by uploads, manual saves and Donna's tools; `file_chunks` by the indexing job; `file_subjects` by `link_file`; `file_message` by the message endpoint and the tools.

**Provenance** is already in these rows: when a file arrived (`created_at`) and in which message (`file_message`). **Originals** are kept: version 1 of a text file is exactly what was uploaded or first written, and is never edited or removed; the original bytes of a PDF or image are never touched. Nothing is defined that nothing fills.

### Upload and file API

All under `/api/v1`, JSON, `auth:sanctum`.

| Endpoint | Detail |
|---|---|
| `POST /files` | Multipart, one file. Validates size and content, stores it, extracts text, returns the file. Throttled (30 a minute). The file belongs to no message until one names it |
| `GET /files` | The list for `/files` and Donna's cards: kept first, then working files used in the last 30 days; `?q=` filters by name and also finds older working files. An upload that was never sent with a message isn't listed |
| `GET /files/{id}` | Metadata, the current version's number and who wrote it, and what the viewer draws: `body` for markdown, text and PDF (cut at 500 KB, with `truncated`), or `table` (`columns`, the first 200 `rows`, `total_rows`) for a CSV, parsed by the server |
| `GET /files/{id}/content` | The bytes, for image display, "Open original" and downloading a text file. Served by the app after the session check, never as a public Spaces URL, with `X-Content-Type-Options: nosniff` and, for anything but images and PDFs, `Content-Disposition: attachment` as `text/plain`. Images and PDFs are read from the Spaces bucket; `?variant=preview` gives an image's smaller copy |
| `PUT /files/{id}` | Manual save: `{body, base_version}`. 409 with the current version if `base_version` is stale. Markdown only |
| `PATCH /files/{id}` | `{kept?, name?}`: the keep switch and rename |
| `POST /messages` | Gains optional `file_ids` (up to five of the user's uploaded, unattached files). `body` may be empty when there are files. A message with no text is not embedded or tagged (nothing to embed); its prompt is the memory block and the attached files |
| `MessageResource` | Gains `files: [{id, name, kind, role, kept, deleted, size_bytes, meta, note}]` for what was attached and what Donna created, edited or opened; `note` is an edit's summary. A `file` source in `sources` carries its `file_id` |

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
- **A retried reply doesn't repeat its edits.** A reply job that fails partway and runs again would append the same text again, and the model rarely words an edit the same way twice. So each edit records its reply (`message_id`) and a hash of the operation (`operation_hash`); a reply that is attempted again is told in its prompt which changes it has already made, and an edit identical to one an earlier attempt made is refused and reported as already done, as `remember` does for facts. A repeat inside one attempt is hers to make: deleting line 2 twice is two edits.
- **Undo:** `revert_file` restores a version by writing it as a new one. "Undo that" reverts to the version before the last reply in which she edited the file, whether that reply made one change or three.

### Donna's tools

Each does its work in code; none calls a model. `edit_file` works on markdown files only, and refuses anything else. The four edit operations are one tool with an `operation`, not four, because tool definitions are most of her input tokens and one tool is harder to pick wrongly.

| Tool | What it does |
|---|---|
| `create_file` | `{name, content}`. Creates a markdown file. The reply gets a file card |
| `read_file` | `{file, heading?, from_line?, lines?}`. Returns a part of any text file, so a long file isn't read whole |
| `edit_file` | `{file, operation, ...}` with an `operation` of `replace_section` (`heading`, `new_text`: for "rewrite this section"), `replace` (`find`, `replace`, `all?`: exact text; fails with the match count if the text isn't there or isn't unique and `all` isn't set), `append` (`text`) or `delete_lines` (a number range, or exact content) |
| `query_csv` | `{file, where?, group_by?, aggregate?, order_by?, limit?}`, run in PHP. Also returns the header and row count, so she can plan a query |
| `rename_file` | Rename |
| `keep_file` | `{file, keep}`. Keep or un-keep. She calls it herself for context files, and on Josh's say-so either way |
| `link_file` | `{file, subjects}`. Link a file to people, projects or topics by name (resolved like facts' subjects), or unlink |
| `delete_file`, `restore_file` | Soft delete (`deleted_at`) and bring back. Deleting removes the chunks; restoring re-indexes if it was kept. Only on Josh's say-so, and an explicit permanent delete arrives with Autonomy (Phase 5) |
| `revert_file` | Restore a previous version as a new one |
| `list_files` | `{q?}`. What is there, for "show my files". Puts file cards on the reply |
| `view_file` | `{file, page?}`. Looks at an image or a PDF again (the whole PDF is attached and the page she asked for is named: cutting one page out would need another library). The SDK can't return an image from a tool (checked against `laravel/ai` 1.4.2: a tool returns a string, and images only go in as attachments on a prompt), so the tool records the request and answers "I'll look at it now". When the first call finishes, the reply job makes one more call in the same reply, with the file attached as the SDK expects, and that call's answer is the reply. At most two looks per reply. Nothing is stored; the original is read from Spaces. The second call mostly reads her cached system prompt, so it is cheap |

These are all reversible and internal, so they need no confirmation (Graduated Autonomy, `vision.md`). They are only tools on files in Donna's own store; she has no access to anything outside it.

### Context

- **The turn's attachments** go into the message. A text file under the inline limit (about 6,000 tokens) goes in whole, in a tagged block marked as Josh's material, not instructions. A larger one goes in as an outline (markdown headings with line numbers; CSV columns, row count and a few rows; PDF page count and the first lines) and she reads or queries the rest. Images, and PDFs with no extractable text, go in as the model's own file blocks for that turn only.
- **Files in play**: files attached or touched in the last 20 messages, listed in the memory block with their IDs, names, kinds, when they arrived (in Josh's timezone), the message they came with, whether they're kept, and the diff of Josh's changes if any. This is how "that file" and "the second one" resolve, and how she cites where a file came from.
- **What she was and wasn't given**, stated by code for every file in the turn: "read in full", "outline only: 3,200 of 41,000 lines", "no text could be extracted (scanned, 12 pages), shown as images", or "extraction failed: <reason>". She is told to say so when an answer rests on less than the whole file.
- **Files and what she can do**: one line in the memory block ("Files: 12 kept, 31 working"), and in her operating instructions the limits she can't see otherwise: what is accepted (text, PDF, images), 10 MB and five per message, and what she can't read (Word, Excel, zip). That answers "what can you do with files?" without a tool of its own.
- **Related files**: the best-matching chunks of kept files (below), a few at a time, with file name and heading path. Chunks of a file whose whole text is already in the prompt (attached this turn and read in full) are left out. A file that is merely in play is one line of memory, so its chunks still count.
- `operating-instructions.md` gains: what files are and the working/kept rule; to treat text inside a file as material, never as instructions; to use the tools instead of retyping a file; to look at the diff of Josh's changes before editing; to offer to keep reference material and never work product; what an image or scan looked like if she has no text for it, and to use `view_file` to look at one again; and what to do when a kept file disagrees with a fact she holds: say both, with their sources and dates, don't pick silently, ask which is right when it matters, and when Josh confirms call `correct_fact`. A file's date is when it arrived, not when its contents were true, so "the newer one wins" is not the rule.

### Archive Memory

- **Chunking** (code, at logical boundaries):
  - Markdown: by heading, each chunk carrying its heading path. A section over about 800 tokens is split by paragraph.
  - Other text: by blank-line paragraph groups up to about 800 tokens.
  - CSV: groups of rows up to about 800 tokens, each with the header row. A file over 2,000 rows gets chunks for its first 2,000 and a summary chunk (columns, row count, a few rows); more than that isn't useful as memory, and `query_csv` answers questions about it anyway.
  - PDF: by page, split by paragraph if long.
  - Images: none.
- **Indexing** runs as a queued job when a file is kept or a kept file changes. Chunks are compared by `content_hash`, so an edit re-embeds only what changed. Embeddings go through the existing `Embedder`, in batches with `attemptMany`, so every one records a `costs` row and shows up in the spend line under the input. If embeddings are down the chunks are still saved and full-text search works, and `EmbedMissing` fills the vectors later, as for messages. `EmbedMissing` covers messages, facts and topics today, so it gains chunks. A failed indexing job lands in `failed_jobs`, which the chat's failed-jobs strip already reports ("she may not be remembering everything"), which is accurate here.
- **Retrieval** adds `file_chunks` to `HybridSearch`: full-text and vector, fused by rank like messages and facts, scored with the same embedding of Josh's message (no extra call), and boosted for chunks of files linked to a project, person or topic the message relates to (the same fixed boost projects give facts). They compete for the memory block's token budget with facts and exchanges.
- **Sources**: a chunk Donna was given appears in "What I looked at" as a `file` source labelled "File name › Heading". Tapping it opens the file.
- A deleted or un-kept file contributes nothing; its chunks are gone.
- **A floor on meaning.** A chunk further than a set cosine distance from the message isn't given to her; among the ones that are near enough, its words help decide the order. Messages and facts have no such floor, but a chunk is hundreds of tokens and a long one shares a word or two with almost anything, so without it a few unrelated chunks went into every prompt. When the message has no embedding (embeddings were down), words are used alone. `recall` searches kept files too, with no floor: when she searches on purpose, anything that matches is wanted.

### The web app

- **The plus returns** in the input's bottom bar, in its empty left column: the bar is a three-column grid with the spend line centered and the send button at the right, so the plus sits opposite send. Chips go above the text inside the field. The sheet ("Choose a file", "Choose a photo") is as drawn. The mockups predate the full-width input and still draw the plus beside the text; the layout on `main` wins. The two choices open the file picker and the photo picker. This reverses Phase 2's "no plus".
- **Chips** above the text, one per file, with a remove button. Upload starts on picking; a chip shows progress, then ready, or an Error border with the reason. Send is disabled while any chip is uploading or has an error. A message may be only chips.
- **A message of only files** has no text, and the API returns `bubbles: []` for blank text, which the thread draws as nothing. The thread draws a bubble for any message that has files, with just its cards. (Found on review of `main`; a test covers it.)
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
| Diffs of Josh's edits, version summaries, conflict detection | Reading a scan or a photo, in the call it arrives in or, for a later look, in the follow-up call `view_file` triggers |
| The kept and working counts, the "Kept" badge | Noticing that a file disagrees with a fact, and saying so |

No new kind of model call. Embedding chunks is the existing embedding call and records its own `costs` row.

### Data model changes in existing tables

`messages` gains nothing; `file_message` links them. The memory block, `ReplyContext` and `ContextSources` gain the file sections above.

### Deployment and instructions

- **Storage setup is already written**: [`storage.md`](../instructions/storage.md) covers the private bucket, a separate `donna-app` access key, and the `AWS_*` variables with `FILESYSTEM_DISK=s3`, which are Laravel's standard names. The build uses that default disk, so there are no new variables and no new instruction file. It adds `league/flysystem-aws-s3-v3`, and updates `storage.md` to drop "unused until Phase 3" and the "don't set `FILESYSTEM_DISK=s3` yet" caveat, so it can be followed as a live setup. Tests use `Storage::fake`; local development stays on the local disk.
- **Backups.** Spaces is durable on its own, and the database dumps hold all text files and versions. `backups.md` already says the backup key is separate from the app's, and should note the app's Space isn't part of the dumps.
- **Upload limits on the server**: PHP's `upload_max_filesize` and `post_max_size` and Nginx's `client_max_body_size` must allow 10 MB per file and five files *(unverified: Forge's defaults are higher than this; check)*. Written into `forge-site.md`.
- **PDF text extraction**: `pdftotext` (poppler-utils), installed by the server recipe in `forge-server.md`, with the pure-PHP `smalot/pdfparser` as the fallback when it isn't there. Decided while building: the PHP parser read every word of a test statement but ran its table cells together, and `pdftotext -layout` kept the columns *(unverified: neither has been tried on Josh's real Stripe and Shopify PDFs)*.
- **Updated**: `storage.md` (above), `environment-variables.md` (its file-storage section: `FILESYSTEM_DISK` is `s3` once this deploys), `forge-server.md` (`pdftotext` in the recipe, and the GD check), `forge-site.md` (upload limits), `backups.md`, `verify-setup.md` (attach a file, edit a markdown file, see it kept, get it back), and `operating.md` (what working files accumulate and the storage bill).
- `CLAUDE.md`'s stack row for file storage, `domain.md`'s File concept and invariants (working and kept, versions), `design.md`'s viewer and attachment components (and its old "Chat input" bullet, which the input change on `main` superseded) and `vision.md`'s Files section where the build departs from it are updated in the build PR.
- The build PR's "Before deploying" lists these in order.

### Testing

Follow the Testing rules in `CLAUDE.md`. Fake every model and `Storage`; no real network.

**API (Pest feature tests):**
- Upload: each kind accepted and extracted (including an unusual text file by content, not extension); a Word or zip file, a wrong type, a file over 10 MB, and a file whose content disagrees with its extension refused; throttling.
- Attaching: files go with the message; another message can't reuse them; a message with only files works.
- Working vs kept: a working file is never chunked or embedded; keeping it queues indexing; a file kept by Donna's tool is reported in her reply; un-keeping and deleting drop its chunks; restoring a kept file re-indexes.
- Tools, through the reply job: each operation writes a version with its summary; `edit_file` `replace` fails on zero and on ambiguous matches; `replace_section` replaces only its section; `revert_file` restores; delete and restore.
- Manual save: writes a version; a stale `base_version` returns 409 and changes nothing; a CSV or PDF is refused. A Donna edit after Josh's save keeps his change.
- Context: Josh's changes since Donna last saw the file appear as a diff; an attached file is inlined under the limit and outlined over it.
- `query_csv` (a unit test: real branching): filter, group, aggregate and order against a fixture.
- Chunking (a unit test): markdown by heading and by size, CSV groups with the header, PDF pages; unchanged chunks keep their hash and aren't re-embedded.
- Retrieval: a kept file's chunk comes back for a related message and is recorded as a source; a working file's never does; a chunk of a file linked to a related project is ranked above an otherwise equal one.
- Provenance and honesty: the context states when a file arrived and with which message, and whether it was read whole, as an outline, as images, or failed; a scanned PDF is flagged rather than passed as empty text.
- Originals: after any number of edits and reverts, version 1 is unchanged and retrievable.
- `view_file`, through the reply job: the follow-up call is made with the image or PDF page attached and its answer becomes the reply; a text file or a missing file is refused; the first call's tool effects happen once; at most two looks; the second call records its own cost.
- The message resource marks each file card kept or not, and a keep or un-keep changes it.
- The memory block carries the kept and working counts; they change when a file is kept.
- A message of only files: the API returns it with its files, it isn't embedded or tagged, and the reply job runs.
- A reply job retried after `append` applied once doesn't append again.
- Chunk vectors missing when embeddings were down are filled by `EmbedMissing`.
- Content endpoint: session required; correct headers.

**Web (Vitest):** chip states and errors, the card, viewer modes and the conflict banner, `/files`. **Playwright with a mocked API:** attach a file and send, a bad file's chip, open a file and edit it, a save conflict. The photo picker and the keyboard in edit mode are device checks listed in the PR.

### Risks to check early

- **`view_file` costs a second model call** when she uses it (same purpose, `reply`, so it records its own `costs` row). The first call's text before it is discarded, and its tool effects (edits, keeps) must not run twice; test this carefully.
- **PDF text from real files.** Stripe and Shopify PDFs vary; try the extractor on a handful of Josh's before building on it.
- **The photo picker on iOS**, in Safari and the installed app: which formats arrive, and whether the file input works from the Home Screen app. Device check.
- **The viewer beside the chat on a Mac** changes the Phase 2 layout; the keyboard and scroll behavior shouldn't regress. Re-run the Phase 2 device checks.
- **Token cost of files in context.** The inline limit and outlines exist for this; watch real `costs` rows in the first week and move the limit.
- **Spaces from the server.** The bucket, key and region have to be right before the first upload can work; `storage.md` ends with a check that stores and reads back a file; run it before the first upload.
- **Embedding cost of large kept files.** The CSV caps exist for this; the first big kept export will show whether they're right.

### What changed while building

Small things the build added or changed, each for a reason found in the code. The sections above already say the same; this is the list in one place.

- **PDF text is read with `pdftotext`, the PHP parser is the fallback.** Tried early, as Risks asks. The PHP parser ran table cells together (`Nov 1po_1Qx0 $100.00In transit`), which is ambiguous for exactly the statements this is for. The server recipe installs `poppler-utils`; without it nothing breaks, tables just read worse. The viewer shows PDF text in monospace so the columns line up.
- **Images get a smaller copy on upload**, made with GD: longest side 1568px, turned upright from the phone's EXIF rotation. It is what the thread, the viewer and the model get (`?variant=preview`); "Open original" serves the untouched upload. Without it a photo over the model's size limit could never be shown to her. Images can't be kept.
- **`file_versions.operation_hash`.** "Its content hash on the version's `message_id`" became a hash of the operation (kind and arguments) stored beside `message_id`. `create_file` uses it too, so a retried reply doesn't write the file twice.
- **A relevance floor for chunks** (`context.chunk_max_distance`), and `recall` searches kept files without it. See Archive Memory.
- **`view_file` works for any PDF, and attaches the whole document**, naming the page asked for. Looking at the original of a PDF that has text is strictly more than the doc asked for, and splitting a PDF needs another library. A PDF over 100 pages isn't shown; she is told why. A second look keeps the first picture in front of her, so two can be compared, which makes up to three model calls for one reply. What the first call's tools returned is handed to the next call in a marked-off block.
- **One request only carries so much.** Images and scans travel as base64; past about 24 MB in one call, the rest aren't sent and she is told to look at them one at a time. An image with no smaller copy that is over 8000px or 3.5 MB isn't shown at all.
- **Pages of a PDF with no text are named.** A statement with two scanned pages is "read in full, except pages 7, 9", not "read in full"; `read_file` says the same, and says so when a whole PDF is a scan.
- **An upload never sent with a message isn't listed or counted.** Removing a chip can't delete the file (nothing is deleted automatically, and there is no `DELETE`), so it stays in storage, out of sight.
- **`keep_file`, `rename_file`, `delete_file` and `restore_file` put the file's card on her reply** (as `opened`), not only `list_files`. That is how a keep she made on her own shows its Kept badge under the reply that made it.
- **Files in play also include any file Josh edited by hand since her last reply**, however long ago it was attached. Otherwise an edit to an older file would reach her with no diff.
- **"Donna last saw" is worked out, not stored**: the newest version she wrote herself or that existed when her last reply was started. Everything after it is Josh's, and that is what the diff covers.
- **The viewer gets a CSV as a parsed table** (`table`), not as text, and long text cut at 500 KB (`truncated`). A markdown file over that size can't be edited by hand.
- **`PUT /files/{id}` is left out of the middleware that trims strings**, so a file is saved exactly as typed.
- **Past messages name their attachments in the conversation she is shown** (`[attached: plan.md (file #12)]`), and a message of only files reaches her as "(Josh sent this with no message.)".
- **CSV sums use exact decimal arithmetic** (`brick/math`, already installed with Laravel and now required directly), and say how many values weren't numbers.
- **The side panel moved the keyboard pinning** from the chat to an outer shell, so edit mode's textarea rides above the keys as well. The Phase 2 keyboard checks need re-running on the phone.
- **File links are visible.** The viewer's Details has an "About" line, and the files-in-play line says what a file is linked to. Without that neither Josh nor Donna could check a link.
- **Only binary is refused as "not text".** The doc said a text file whose encoding can't be detected is refused. In practice text that isn't UTF-8 (and isn't UTF-16 with a byte-order mark) is read as Windows-1252, which is what spreadsheet exports almost always are; anything with binary bytes is refused. A `.tsv` is treated as a CSV, and a HEIC photo is refused with its own reason.
- **Which tools put a card on her reply**: `create_file`, `edit_file`, `revert_file`, `list_files`, `keep_file`, `rename_file`, `delete_file` and `restore_file`. Reading, querying, linking and viewing don't.
- **"Used in the last 30 days" means changed in the last 30 days** (`updated_at`). Opening or re-reading a file doesn't count; `?q=` finds the rest.
- **`content_hash` is what a manual save is compared with**, so saving text that hasn't changed writes no version. A CSV's column count was dropped from `meta`: nothing read it.
- **A send the server refuses for good** (a file it names was already sent, or is gone) shows the reason and "Tap to remove it" instead of a retry that could never work.
- **"Choose a file" has no `accept` list**, so nothing is unselectable and the server decides. On iOS that may show the system's own menu (Photo Library, Take Photo, Choose Files) before Files. A device check.
- **Edit mode keeps unsaved text on the device** per file, and Save is disabled until the text differs.

### Open questions

Settled in the discussion: any text file is accepted but only markdown is edited; Josh doesn't use Word, Excel or Pages, and rarely Google Docs, so there are no extractors for them (a Google Doc is attached as PDF or markdown); Donna decides what to keep and says so; originals live in Spaces; provenance is when and which message, not who; **nothing is deleted automatically and working files don't expire**, so deleting unneeded files is left for a later phase; no lock on files, the preserved original is enough; PDFs are shown as extracted text plus "Open original" for now; images aren't searchable.

1. **Skills (Phase 4) are files too.** I've assumed they get their own store, not this one.
