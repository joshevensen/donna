import type { Page, Route } from '@playwright/test';

/*
 | Donna's API, faked in the browser. It keeps a conversation in memory and
 | answers the same routes the Laravel app does, so the flows below run
 | against the real built front end with no server behind it.
 */

export interface FakeMessage {
    id: number;
    author: 'josh' | 'donna';
    body: string;
    bubbles: string[];
    source: string;
    status: 'pending' | 'complete' | 'failed';
    in_reply_to_id: number | null;
    error: string | null;
    citations: unknown[];
    sources: { type: string; label: string; url?: string }[];
    actions: { type: string; label: string }[];
    files: FakeMessageFile[];
    client_key: string | null;
    created_at: string;
}

export type FakeKind = 'markdown' | 'csv' | 'text' | 'pdf' | 'image';

/** A file as the API describes it, with the text the viewer shows. */
export interface FakeFile {
    id: number;
    name: string;
    kind: FakeKind;
    mime: string;
    size_bytes: number;
    kept: boolean;
    deleted: boolean;
    extraction: 'ready' | 'failed';
    extraction_error: string | null;
    meta: Record<string, number | boolean>;
    version: number | null;
    last_author: 'josh' | 'donna' | null;
    editable: boolean;
    created_at: string;
    updated_at: string;
    body: string | null;
    truncated: boolean;
    table: { columns: string[]; rows: string[][]; total_rows: number } | null;
}

export interface FakeMessageFile {
    id: number;
    name: string;
    kind: FakeKind;
    role: 'attached' | 'created' | 'edited' | 'opened';
    kept: boolean;
    deleted: boolean;
    size_bytes: number;
    meta: Record<string, number | boolean>;
    note: string | null;
}

type ReplyPlan =
    | { fails: true }
    | { fails?: false; bubbles: string[]; sources?: FakeMessage['sources']; actions?: FakeMessage['actions']; files?: { id: number; role: FakeMessageFile['role']; note?: string }[] };

/** What the server can't read, by extension: enough to stand in for its content sniffing. */
const UNSUPPORTED = /\.(xlsx?|docx?|zip|pages|numbers|heic)$/i;

const KINDS: [RegExp, FakeKind, string][] = [
    [/\.(md|markdown|txt)$/i, 'markdown', 'text/markdown'],
    [/\.csv$/i, 'csv', 'text/csv'],
    [/\.pdf$/i, 'pdf', 'application/pdf'],
    [/\.(jpe?g|png|webp|gif)$/i, 'image', 'image/jpeg'],
];

/** A 1x1 PNG, served for any image's bytes. */
const PIXEL = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', 'base64');

const COSTS = { today: '0.42', cycle: '12.30' };

export class FakeDonna {
    signedIn = true;
    messages: FakeMessage[] = [];

    /** While true, sends never reach the "server": the request fails like a dropped connection. */
    dropSends = false;

    /** How many sends were dropped that way. */
    droppedSends = 0;

    /** What Donna says next; consumed one per reply. Falls back to a one-bubble answer. */
    replies: ReplyPlan[] = [];

    /** How many times a pending reply is polled before it finishes. */
    pollsUntilReply = 1;

    /** Every send the server accepted, with the key and the files it carried. */
    sends: { key: string | null; body: string; fileIds: number[] }[] = [];

    /** Every file the server holds. */
    files: FakeFile[] = [];

    /** The code that signs in. */
    code = '123456';

    /** How many times a sign-in email was asked for. */
    codeRequests = 0;

    private nextId = 1;
    private nextFileId = 1;
    private plans = new Map<number, { polls: number; plan: ReplyPlan }>();

    /** Put a file on the server, as if it had been uploaded or written earlier. */
    file(name: string, text: string | null, extra: Partial<FakeFile> = {}): FakeFile {
        const [, kind, mime] = KINDS.find(([pattern]) => pattern.test(name)) ?? [null, 'text' as FakeKind, 'text/plain'];
        const now = new Date().toISOString();
        const rows = kind === 'csv' && text !== null ? text.trim().split('\n').map((line) => line.split(',')) : null;

        const file: FakeFile = {
            id: this.nextFileId++,
            name,
            kind,
            mime,
            size_bytes: text?.length ?? 2048,
            kept: false,
            deleted: false,
            extraction: 'ready',
            extraction_error: null,
            meta: rows !== null ? { rows: rows.length - 1, columns: rows[0]!.length } : kind === 'markdown' && text !== null ? { lines: text.split('\n').length } : {},
            version: kind === 'image' ? null : 1,
            last_author: kind === 'image' ? null : 'josh',
            editable: kind === 'markdown',
            created_at: now,
            updated_at: now,
            body: kind === 'csv' || kind === 'image' ? null : text,
            truncated: false,
            table: rows !== null ? { columns: rows[0]!, rows: rows.slice(1), total_rows: rows.length - 1 } : null,
            ...extra,
        };

        this.files.push(file);

        return file;
    }

    /** Donna changes a file, as one of her tools would: a new version that a save based on the old one collides with. */
    donnaEdits(file: FakeFile, body: string): void {
        file.body = body;
        file.version = (file.version ?? 0) + 1;
        file.last_author = 'donna';
        file.updated_at = new Date().toISOString();
    }

    /** A file as a message carries it. Its name and keep state are read when asked for, as the API does. */
    attachment(file: FakeFile, role: FakeMessageFile['role'] = 'attached', note: string | null = null): FakeMessageFile {
        return { id: file.id, name: file.name, kind: file.kind, role, kept: file.kept, deleted: file.deleted, size_bytes: file.size_bytes, meta: file.meta, note };
    }

    /** Add a finished message to the history. */
    said(author: 'josh' | 'donna', body: string, at: string, extra: Partial<FakeMessage> = {}): FakeMessage {
        const message: FakeMessage = {
            id: this.nextId++,
            author,
            body,
            bubbles: author === 'donna' ? body.split('\n---\n') : [body],
            source: 'web',
            status: 'complete',
            in_reply_to_id: null,
            error: null,
            citations: [],
            sources: [],
            actions: [],
            files: [],
            client_key: null,
            created_at: at,
            ...extra,
        };

        this.messages.push(message);

        return message;
    }

    async install(page: Page): Promise<void> {
        await page.route('**/sanctum/csrf-cookie', (route) =>
            route.fulfill({ status: 204, headers: { 'Set-Cookie': 'XSRF-TOKEN=fake-token; Path=/' } }),
        );

        await page.route('**/api/v1/**', (route) => this.handle(route));
    }

    private async handle(route: Route): Promise<void> {
        const request = route.request();
        const url = new URL(request.url());
        const path = url.pathname.replace('/api/v1', '');
        const method = request.method();
        const json = (status: number, body: unknown = null) =>
            route.fulfill({ status, contentType: 'application/json', body: body === null ? '' : JSON.stringify(body) });

        if (path === '/auth/login-code' && method === 'POST') {
            this.codeRequests += 1;

            return json(202);
        }

        if (path === '/auth/verify' && method === 'POST') {
            if (request.postDataJSON().code.replace(/\s/g, '') !== this.code) {
                return json(422, { message: 'Nope.', errors: { code: ["That code didn't work. Check it, or ask for a new one."] } });
            }

            this.signedIn = true;

            return json(200, { data: this.me() });
        }

        if (path === '/auth/verify-link' && method === 'POST') {
            this.signedIn = true;

            return json(200, { data: this.me() });
        }

        if (path === '/auth/revoke' && method === 'POST') {
            this.signedIn = false;

            return json(204);
        }

        if (!this.signedIn) {
            return json(401, { message: 'Unauthenticated.' });
        }

        if (path === '/auth/logout' || path === '/auth/logout-everywhere') {
            this.signedIn = false;

            return json(204);
        }

        if (path === '/presence' || path === '/push-subscriptions') {
            return json(204);
        }

        if (path === '/me') {
            return json(200, { data: this.me() });
        }

        if (path === '/messages' && method === 'GET') {
            return json(200, this.page(url.searchParams));
        }

        if (path === '/messages' && method === 'POST') {
            if (this.dropSends) {
                this.droppedSends += 1;

                return route.abort('connectionfailed');
            }

            const sent = request.postDataJSON();

            return json(202, { data: this.accept(sent.body, request.headers()['idempotency-key'] ?? null, sent.file_ids ?? []) });
        }

        if (path === '/files' && method === 'POST') {
            return this.upload(route);
        }

        if (path === '/files' && method === 'GET') {
            const listed = this.files.filter((file) => !file.deleted);

            return json(200, { data: [...listed.filter((file) => file.kept), ...listed.filter((file) => !file.kept)].map(({ body, truncated, table, ...item }) => item) });
        }

        const content = /^\/files\/(\d+)\/content$/.exec(path);

        if (content && method === 'GET') {
            const file = this.files.find((candidate) => candidate.id === Number(content[1]));

            return file?.kind === 'image'
                ? route.fulfill({ status: 200, contentType: 'image/png', body: PIXEL })
                : route.fulfill({ status: 200, contentType: 'text/plain', body: file?.body ?? '' });
        }

        const one = /^\/files\/(\d+)$/.exec(path);

        if (one) {
            const file = this.files.find((candidate) => candidate.id === Number(one[1]) && !candidate.deleted);

            if (!file) {
                return json(404, { message: 'Not found.' });
            }

            if (method === 'GET') {
                return json(200, { data: file });
            }

            if (method === 'PATCH') {
                Object.assign(file, request.postDataJSON());

                return json(200, { data: file });
            }

            if (method === 'PUT') {
                const save = request.postDataJSON();

                // Someone changed the file since this text was started: nothing is saved.
                if (save.base_version !== file.version) {
                    return json(409, { message: 'The file changed while you were editing.', data: file });
                }

                file.body = save.body;
                file.version = (file.version ?? 0) + 1;
                file.last_author = 'josh';
                file.updated_at = new Date().toISOString();

                return json(200, { data: file });
            }
        }

        const retry = /^\/messages\/(\d+)\/retry$/.exec(path);

        if (retry && method === 'POST') {
            const reply = this.find(Number(retry[1]));

            reply.status = 'pending';
            reply.error = null;
            this.plan(reply);

            return json(202, { data: reply });
        }

        const message = /^\/messages\/(\d+)$/.exec(path);

        if (message && method === 'GET') {
            return json(200, { data: this.poll(this.find(Number(message[1]))) });
        }

        return json(404, { message: `The fake API has no ${method} ${path}.` });
    }

    private me() {
        return { name: 'Josh', timezone: 'America/Chicago', push: { configured: false, public_key: null } };
    }

    private find(id: number): FakeMessage {
        const message = this.messages.find((candidate) => candidate.id === id);

        if (!message) {
            throw new Error(`The fake API has no message ${id}.`);
        }

        return message;
    }

    private page(query: URLSearchParams) {
        const limit = Number(query.get('limit') ?? 20);

        if (query.has('after')) {
            const newer = this.messages.filter((message) => message.id > Number(query.get('after')));

            return { data: newer.slice(0, limit), meta: { next_after: newer.length > limit ? newer[limit - 1]!.id : null, costs: COSTS } };
        }

        const before = query.has('before') ? Number(query.get('before')) : Infinity;
        const older = this.messages.filter((message) => message.id < before).reverse();

        return { data: older.slice(0, limit), meta: { next_before: older.length > limit ? older[limit - 1]!.id : null, costs: COSTS } };
    }

    /**
     * Take an upload. The file's name and text are read out of the multipart
     * body; what the real server would refuse by sniffing is refused here by
     * its extension.
     */
    private async upload(route: Route): Promise<void> {
        const raw = route.request().postDataBuffer()?.toString('latin1') ?? '';
        const name = /filename="([^"]*)"/.exec(raw)?.[1] ?? 'upload';
        const text = /\r\n\r\n([\s\S]*?)\r\n--/.exec(raw.slice(raw.indexOf('filename=')))?.[1] ?? '';

        if (UNSUPPORTED.test(name)) {
            return route.fulfill({
                status: 422,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Unsupported. Text, PDF and images only', errors: { file: ['Unsupported. Text, PDF and images only'] } }),
            });
        }

        const { body, truncated, table, ...item } = this.file(name, text);

        return route.fulfill({ status: 201, contentType: 'application/json', body: JSON.stringify({ data: item }) });
    }

    /** Save a send, or return the original pair when its key has been seen before. */
    private accept(body: string, key: string | null, fileIds: number[] = []) {
        const existing = key === null ? undefined : this.messages.find((message) => message.client_key === key);

        if (existing) {
            return { message: existing, reply: this.messages.find((message) => message.in_reply_to_id === existing.id) ?? null };
        }

        this.sends.push({ key, body, fileIds });

        const now = new Date().toISOString();
        const attached = fileIds.map((id) => this.attachment(this.files.find((file) => file.id === id)!));

        // The real API returns no bubbles for a message with no text.
        const message = this.said('josh', body, now, { client_key: key, files: attached, bubbles: body === '' ? [] : [body] });
        const reply = this.said('donna', '', now, { status: 'pending', bubbles: [], in_reply_to_id: message.id });

        this.plan(reply);

        return { message, reply };
    }

    private plan(reply: FakeMessage): void {
        this.plans.set(reply.id, { polls: 0, plan: this.replies.shift() ?? { bubbles: ['Got it.'] } });
    }

    /** A pending reply finishes after it has been polled enough times. */
    private poll(message: FakeMessage): FakeMessage {
        const planned = this.plans.get(message.id);

        if (message.status !== 'pending' || !planned) {
            return message;
        }

        planned.polls += 1;

        if (planned.polls < this.pollsUntilReply) {
            return message;
        }

        this.plans.delete(message.id);

        if (planned.plan.fails) {
            message.status = 'failed';
            message.error = 'Donna could not reply.';
        } else {
            message.status = 'complete';
            message.bubbles = planned.plan.bubbles;
            message.body = planned.plan.bubbles.join('\n---\n');
            message.sources = planned.plan.sources ?? [];
            message.actions = planned.plan.actions ?? [];
            message.files = (planned.plan.files ?? []).map((touched) =>
                this.attachment(this.files.find((file) => file.id === touched.id)!, touched.role, touched.note ?? null),
            );
        }

        return message;
    }
}
