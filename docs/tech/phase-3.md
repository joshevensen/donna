# Phase 3 — Files + Archive Memory

Josh attaches markdown, CSV, PDF and image files in the chat, reads and edits them with Donna in a viewer, and the ones he wants kept become something she finds on her own. Scope is in [`phases.md`](../phases.md); concepts in [`domain.md`](../domain.md); look and feel in [`design.md`](../design.md); the chat this builds on is [`phase-2.md`](phase-2.md).

**Status:** draft for discussion; not approved, not built. Open questions are at the end.

**Mockup:** not yet. The phone attach sheet and chips are already in [`phase-2/mockup.dc.html`](phase-2/mockup.dc.html). Still to draw: the file card in a bubble, the viewer (phone sheet and Mac side panel, view and edit modes) and the `/files` list. They are drawn once the stories below are agreed.

---

## User Stories

### Attaching files

- I tap the plus in the input and choose a file or a photo. Markdown, CSV, PDF and images all work, up to 10 MB each and five to a message.
- The file shows as a chip above the input while it uploads. If it can't be used, the chip says why ("Unsupported. Markdown, CSV, PDF and images only", "Too large. Max 10 MB") and I can remove it.
- I can send a file with no text. Donna looks at it and answers.
- In the thread my message carries the file as a small card at the top of its bubble. Tapping it opens it.

### Working on a file together

- Donna reads what I attach: a Stripe export, a Ravelry pattern, a PDF from a vendor. She answers questions about it, and for a CSV she gets totals and filtered rows right without guessing, because the arithmetic is done by code, not by her.
- I tell her what to change and she changes exactly that: "replace Fibermade Co with Fibermade everywhere", "add a section on returns", "rewrite the pricing section to be shorter". Small edits don't rewrite the whole file.
- I can open any markdown or CSV file and edit it myself, in a view that looks different from reading, then go back to the conversation and she knows what I changed.
- If I say "undo that", she puts the file back.
- Donna can create a file from scratch: "write that up as a plan".
- There's no delete button. I ask her to delete a file. She can bring it back if I change my mind.

### What she remembers

- A file is temporary by default. When I ask her to reformat a knitting pattern, she doesn't remember the pattern afterwards.
- When I want a file remembered, I say so ("keep this"), or she asks when it looks like something I'll come back to (my notes, plans, context documents, an export I'll refer to). A switch in the viewer does the same.
- Kept files are found on their own: when a topic comes up she draws on them, and "what I looked at" shows which file and which section.
- When I stop keeping a file she stops finding it. The file itself stays.

### Finding files

- I ask "show me my files" or "open the Fibermade plan" and she answers with the files as cards. `/files` lists them without involving her.
- Kept files are listed first, then the temporary ones I've used recently.

### Not in this phase

- Folders or tags. Names and Donna's search are the structure.
- A history screen. Versions are kept and Donna can undo, but there's no UI to browse them.
- Editing PDFs or images, OCR as a feature, and editing a PDF's pages. PDFs are read, not changed.
- Sharing files outside Donna, and exporting everything at once. A single file can be downloaded.
- JSON or other formats, and a file viewer in the terminal. The terminal can't attach.
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
| Temporary or kept | Every file starts **working**. Only **kept** files are chunked and embedded. | Josh's rule from the interview: a reformatted pattern shouldn't be remembered. It also keeps embedding cost and retrieval noise down. |
| Where the bytes live | Text of markdown and CSV files in Postgres, as versions. Original PDFs and images in DigitalOcean Spaces. | Edits are small, frequent, versioned and must change a file and its chunks together, which one database transaction does. Binaries never change. This departs from the stack table's "file storage: Spaces"; the table is updated in the build PR. |
| Versions | Every change creates a version: Josh's saves, each of Donna's edits, a revert. | "Undo that" and "I edited it, continue" both need it. Cheap for text of this size. |
| Simple edits | Donna's tools do find-and-replace, append, delete lines and rename in code. Rewriting a section is her judgment, but applying it is code, by heading. | The vision's rule: no tokens spent on deterministic work, and no rewriting a whole file for a small edit. |
| CSV maths | A `query_csv` tool: filter, sort, group, count, sum, average, evaluated in PHP. | `CLAUDE.md`: totals and filtering are always code. A Shopify export with thousands of rows can't go into a prompt anyway. |
| PDFs | Text extracted by code and stored as the file's one version. A PDF with no text (a scan) is shown to the model directly in the turn it arrives. | Most PDFs Josh uploads (Stripe, Shopify) have text. The model only reads pixels when there are no words to extract. |
| Images | Stored, shown in the thread and viewer, and shown to the model in the turn they arrive. Not indexed. | Nothing to chunk. Kept images are for the thread and for Donna to re-open, not for search. |
| Uploading | Two steps: `POST /files` per file, then `POST /messages` with the file IDs. | Matches the chips in the mockup (progress and a per-file error) and keeps the message request small. |
| Archive search | The same hybrid search as messages and facts, over chunks, using the embedding already made for Josh's message. | No new kind of model call. |

### Kinds and limits

| Kind | Types accepted | Stored | Viewer |
|---|---|---|---|
| `markdown` | `.md`, `.markdown`, `.txt` | Postgres | Rendered; edit mode |
| `csv` | `.csv` | Postgres | Table; edit mode (raw text) |
| `pdf` | `.pdf` | Original in Spaces; extracted text in Postgres | The extracted text, with "Open original" |
| `image` | JPEG, PNG, WebP, GIF | Spaces | The image |

- 10 MB per file, five per message, the same limits in the client and the server. The server decides by sniffing the content (`finfo`), never by the extension or the browser's claimed type.
- iPhone photos arrive as JPEG when picked through the file input *(unverified: iOS converts HEIC when the input accepts `image/jpeg`; check on the device)*.
- Text files must be valid UTF-8. A different encoding is converted if it can be detected, otherwise the file is refused with a message.

### Data model

| Table | Columns and notes |
|---|---|
| `files` | `id`, `name`, `kind`, `mime`, `size_bytes`, `storage_path` (null for text), `current_version_id`, `kept_at` (null while working), `deleted_at`, `created_by` (`josh` or `donna`), `extraction` (`ready`, `failed`), `extraction_error`, timestamps |
| `file_versions` | `id`, `file_id`, `number`, `body` (text), `content_hash`, `author` (`josh`, `donna`), `message_id` (null), `summary` (what changed, written by code, e.g. "Replaced 14 matches of 'Fibermade Co'"), `created_at`. A PDF has one; an image has none |
| `file_chunks` | `id`, `file_id`, `position`, `heading_path` (e.g. "Pricing › Wholesale"), `body`, `content_hash`, `embedding` (vector 1024), `search` (tsvector, generated), timestamps. Current version only; rebuilt on change |
| `file_message` | `file_id`, `message_id`, `role` (`attached`, `created`, `edited`, `opened`). What Josh attached and what Donna touched in a turn |

Every table is written by something: `files` and `file_versions` by uploads, manual saves and Donna's tools; `file_chunks` by the indexing job; `file_message` by the message endpoint and the tools. Nothing is defined that nothing fills.

### Upload and file API

All under `/api/v1`, JSON, `auth:sanctum`.

| Endpoint | Detail |
|---|---|
| `POST /files` | Multipart, one file. Validates size and content, stores it, extracts text, returns the file. Throttled (30 a minute). The file belongs to no message until one names it |
| `GET /files` | The list for `/files` and Donna's cards: kept first, then working files from the last 30 days; `?q=` filters by name |
| `GET /files/{id}` | Metadata, the current version's number and, for text kinds, its body |
| `GET /files/{id}/content` | The bytes, for image display and "Open original". Served by the app after the session check, never as a public Spaces URL, with `X-Content-Type-Options: nosniff` and, for anything but images and PDFs, `Content-Disposition: attachment` |
| `PUT /files/{id}` | Manual save: `{body, base_version}`. 409 with the current version if `base_version` is stale. Markdown and CSV only |
| `PATCH /files/{id}` | `{kept?, name?}`: the keep switch and rename |
| `POST /messages` | Gains optional `file_ids` (up to five of the user's uploaded, unattached files). `body` may be empty when there are files |
| `MessageResource` | Gains `files: [{id, name, kind, role}]` for what was attached and what Donna created, edited or opened |

There is no `DELETE`. Deleting is a tool of Donna's.

### Working files and kept files

- A new file is working: stored, visible, usable in the conversation, never indexed.
- It becomes kept when Donna calls `keep_file` (Josh said so, or she offered and he agreed), or Josh flips the switch in the viewer. Both set `kept_at` and queue indexing.
- Un-keeping removes its chunks and nothing else. Donna never keeps a file on her own: she can offer, and `operating-instructions.md` tells her to offer for reference material (notes, plans, context documents, exports he'll return to) and never for work product like a reformatted copy.
- Facts learned from a conversation about a file are still extracted from the conversation by the daily run, as for anything else Josh says. Files themselves are never read by extraction; Archive Memory and Facts stay separate layers.
- **Nothing is deleted automatically**, working files included (`domain.md` invariant). See the open questions.

### Editing

- **Donna's edits** go through tools, each of which writes a new version with a `summary`. Her tools are listed below. A tool works on the file's current content when it runs, so an edit Josh made a moment ago is never overwritten by a stale copy.
- **Josh's edits** happen in the viewer's edit mode: a monospace textarea on Inset, so it is obvious which mode he is in. Saving posts `base_version`. If Donna changed the file in the meantime the save is refused with a 409 and the viewer offers "Keep mine" (saved as a new version on top, hers still in history) or "Take Donna's".
- **Donna learns what Josh changed.** For files in play, the context holds, in code-written text, a diff of Josh's changes since Donna last saw the file (capped), so she knows without being told. If it is too large to include, she gets the headings that changed.
- The viewer refreshes by itself while Donna is writing a reply that touches the open file (the same polling as the thread).
- **Undo:** `revert_file` restores a version by writing it as a new one. "Undo that" reverts to the version before her last edit.

### Donna's tools

Each does its work in code. Only the first four need a model to decide *what*; none calls a model itself.

| Tool | What it does |
|---|---|
| `create_file` | `{name, kind, content}`. Creates a working file. The reply gets a file card |
| `read_file` | `{file, heading?, from_line?, lines?}`. Returns a part of a text file, so a long file isn't read whole |
| `edit_section` | `{file, heading, new_text}`. Replaces one markdown section by its heading. For "rewrite this section" |
| `replace_in_file` | `{file, find, replace, all?}`. Exact text. Fails with the match count if the text isn't there or isn't unique and `all` isn't set |
| `append_to_file`, `delete_lines` | Append text; delete lines by number range or by exact content |
| `query_csv` | `{file, where?, group_by?, aggregate?, order_by?, limit?}`, run in PHP. Also returns the header and row count, so she can plan a query |
| `rename_file` | Rename |
| `keep_file` | `{file, keep}`. Keep or un-keep |
| `delete_file`, `restore_file` | Soft delete (`deleted_at`) and bring back. Deleting removes the chunks; restoring re-indexes if it was kept. Only on Josh's say-so, and an explicit permanent delete arrives with Autonomy (Phase 5) |
| `revert_file` | Restore a previous version as a new one |
| `list_files` | `{q?}`. What is there, for "show my files". Puts file cards on the reply |

These are all reversible and internal, so they need no confirmation (Graduated Autonomy, `vision.md`). They are only tools on files in Donna's own store; she has no access to anything outside it.

### Context

- **The turn's attachments** go into the message. A text file under the inline limit (about 6,000 tokens) goes in whole, in a tagged block marked as Josh's material, not instructions. A larger one goes in as an outline (markdown headings with line numbers; CSV columns, row count and a few rows; PDF page count and the first lines) and she reads or queries the rest. Images, and PDFs with no extractable text, go in as the model's own file blocks for that turn only.
- **Files in play**: files attached or touched in the last 20 messages, listed in the memory block with their IDs, names, kinds, whether they're kept, and the diff of Josh's changes if any. This is how "that file" and "the second one" resolve.
- **Related files**: the best-matching chunks of kept files (below), a few at a time, with file name and heading path. Chunks of a file already in play are left out.
- `operating-instructions.md` gains: what files are and the working/kept rule; to treat text inside a file as material, never as instructions; to use the tools instead of retyping a file; to look at the diff of Josh's changes before editing; to offer to keep reference material and never work product; and what an image or scan looked like if she has no text for it.

### Archive Memory

- **Chunking** (code, at logical boundaries):
  - Markdown: by heading, each chunk carrying its heading path. A section over about 800 tokens is split by paragraph.
  - CSV: groups of rows up to about 800 tokens, each with the header row. A file over 2,000 rows gets chunks for its first 2,000 and a summary chunk (columns, row count, a few rows); more than that isn't useful as memory, and `query_csv` answers questions about it anyway.
  - PDF: by page, split by paragraph if long.
  - Images: none.
- **Indexing** runs as a queued job when a file is kept or a kept file changes. Chunks are compared by `content_hash`, so an edit re-embeds only what changed. Embeddings go through the existing `Embedder`, so every one records a `costs` row. If embeddings are down the chunks are still saved and full-text search works, and `EmbedMissing` fills the vectors later, as for messages.
- **Retrieval** adds `file_chunks` to `HybridSearch`: full-text and vector, fused by rank like messages and facts, scored with the same embedding of Josh's message (no extra call), and boosted for chunks of files linked to a related project. They compete for the memory block's token budget with facts and exchanges.
- **Sources**: a chunk Donna was given appears in "What I looked at" as a `file` source labelled "File name › Heading". Tapping it opens the file.
- A deleted or un-kept file contributes nothing; its chunks are gone.

### The web app

- **The plus returns** in the input, bottom left, as in the mockup: the collapsed bar, the open field and the sheet ("Choose a file", "Choose a photo"). The two choices open the file picker and the photo picker. This reverses Phase 2's "no plus".
- **Chips** above the text, one per file, with a remove button. Upload starts on picking; a chip shows progress, then ready, or an Error border with the reason. Send is disabled while any chip is uploading or has an error. A message may be only chips.
- **Sent files** show as a card at the top of the bubble: icon, name, size. Files Donna created, edited or opened show the same card under her bubble. Tapping a card opens the viewer. An edit's card says what changed ("Edited · 14 replacements").
- **The viewer.** On a phone, a full-screen sheet over the chat. At 768px and wider, a side panel: the chat keeps its width on the left and the file takes the right half, so the 600px centred column becomes two columns while a file is open. Header: the file's name, a **Keep** switch, Edit and Close. Body by kind: rendered markdown (the same safe renderer as replies; images and raw HTML are not shown), a table for CSV, extracted text with "Open original" for a PDF, the image. Edit mode (markdown and CSV): Inset background, monospace, Save and Cancel, and a conflict banner when a save is refused. Metadata (created, modified, size, who edited last, version) expands at the bottom.
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
| Diffs of Josh's edits, version summaries, conflict detection | Reading a scan or a photo (in the same reply call) |

No new kind of model call. Embedding chunks is the existing embedding call and records its own `costs` row.

### Data model changes in existing tables

`messages` gains nothing; `file_message` links them. The memory block, `ReplyContext` and `ContextSources` gain the file sections above.

### Deployment and instructions

- **New `docs/instructions/spaces.md`**: a DigitalOcean Space for the app's files, separate from the backup Space, with its own access key limited to that bucket; the environment variables (`DO_SPACES_KEY`, `DO_SPACES_SECRET`, `DO_SPACES_REGION`, `DO_SPACES_BUCKET`, `DO_SPACES_ENDPOINT`); that it stays private (files are served through the app); and a check. Adds `league/flysystem-aws-s3-v3` and an `s3` disk in `config/filesystems.php`.
- **Upload limits on the server**: PHP's `upload_max_filesize` and `post_max_size` and Nginx's `client_max_body_size` must allow 10 MB per file and five files *(unverified: Forge's defaults are higher than this; check)*. Written into `forge-site.md`.
- **PDF text extraction**: a pure-PHP package, chosen when building after trying it on real Stripe and Shopify PDFs *(unverified: quality on those)*; if it isn't good enough, `pdftotext` (poppler) is installed on the droplet and `forge-server.md` says how.
- **Updated**: `environment-variables.md`, `forge-site.md`, `backups.md` (the app and the backups no longer share the idea of "Spaces"), `verify-setup.md` (attach a file, edit it, keep it, get it back), `operating.md` (storage use, what "working" files accumulate), `README.md` row for `spaces.md`.
- `CLAUDE.md`'s stack row for file storage, `domain.md`'s File concept and invariants (working and kept, versions), `design.md`'s viewer and attachment components and `vision.md`'s Files section where the build departs from it are updated in the build PR.
- The build PR's "Before deploying" lists these in order.

### Testing

Follow the Testing rules in `CLAUDE.md`. Fake every model and `Storage`; no real network.

**API (Pest feature tests):**
- Upload: each kind accepted and extracted; a wrong type, a file over 10 MB, and a file whose content disagrees with its extension refused; throttling.
- Attaching: files go with the message; another message can't reuse them; a message with only files works.
- Working vs kept: a working file is never chunked or embedded; keeping it queues indexing; un-keeping and deleting drop its chunks; restoring a kept file re-indexes.
- Tools, through the reply job: each operation writes a version with its summary; `replace_in_file` fails on zero and on ambiguous matches; `edit_section` replaces only its section; `revert_file` restores; delete and restore.
- Manual save: writes a version; a stale `base_version` returns 409 and changes nothing. A Donna edit after Josh's save keeps his change.
- Context: Josh's changes since Donna last saw the file appear as a diff; an attached file is inlined under the limit and outlined over it.
- `query_csv` (a unit test: real branching): filter, group, aggregate and order against a fixture.
- Chunking (a unit test): markdown by heading and by size, CSV groups with the header, PDF pages; unchanged chunks keep their hash and aren't re-embedded.
- Retrieval: a kept file's chunk comes back for a related message and is recorded as a source; a working file's never does.
- Content endpoint: session required; correct headers.

**Web (Vitest):** chip states and errors, the card, viewer modes and the conflict banner, `/files`. **Playwright with a mocked API:** attach a file and send, a bad file's chip, open a file and edit it, a save conflict. The photo picker and the keyboard in edit mode are device checks listed in the PR.

### Risks to check early

- **PDF text from real files.** Stripe and Shopify PDFs vary; try the extractor on a handful of Josh's before building on it.
- **The photo picker on iOS**, in Safari and the installed app: which formats arrive, and whether the file input works from the Home Screen app. Device check.
- **The viewer beside the chat on a Mac** changes the Phase 2 layout; the keyboard and scroll behavior shouldn't regress. Re-run the Phase 2 device checks.
- **Token cost of files in context.** The inline limit and outlines exist for this; watch real `costs` rows in the first week and move the limit.
- **Embedding cost of large kept files.** The CSV caps exist for this; the first big kept export will show whether they're right.

### Open questions

These are for the discussion before this doc is approved.

1. **Working files and the "never deleted automatically" invariant.** I kept it: temporary files stay in storage, unindexed, forever, and show in `/files` only for 30 days. Storage is cheap, but it means Donna's store fills with copies of one-off work. The alternative is to let working files expire (for example after 90 days) and amend the invariant for them. Which do you prefer?
2. **Should keeping be offered on every attachment, or only for reference-looking files?** I've written it as her judgment, to avoid asking each time. If she asks too often, it's an instruction change, not code.
3. **PDFs in the viewer.** Showing extracted text loses the layout of an invoice. Embedding the original is better on a Mac and poor on iPhone Safari. I chose text plus "Open original". Is that enough?
4. **Image memory.** Images aren't searchable. If you want "find the photo of the yarn I took", a kept image would need a description written by the model, which is a new kind of model call. I left it out. Needed?
5. **Skills (Phase 4) are files too.** If you'd rather they live in this same store, that changes this phase's tables slightly; I've assumed they get their own.
