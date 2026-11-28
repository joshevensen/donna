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
    client_key: string | null;
    created_at: string;
}

type ReplyPlan = { fails: true } | { fails?: false; bubbles: string[]; sources?: FakeMessage['sources']; actions?: FakeMessage['actions'] };

const COSTS = { today: '0.42', cycle: '12.30' };

/** One period of GET /costs, shaped like App\Costs\CostReport::for. */
export interface FakeCostReport {
    period: string;
    from: string;
    until: string;
    total_usd: string;
    calls: number;
    web_searches: number;
    by_purpose: Record<string, { usd: string; calls: number }>;
}

export class FakeDonna {
    signedIn = true;
    messages: FakeMessage[] = [];

    /** While true, sends never reach the "server": the request fails like a dropped connection. */
    dropSends = false;

    /** What Donna says next; consumed one per reply. Falls back to a one-bubble answer. */
    replies: ReplyPlan[] = [];

    /** How many times a pending reply is polled before it finishes. */
    pollsUntilReply = 1;

    /** Every send the server accepted, with the key it carried. */
    sends: { key: string | null; body: string }[] = [];

    /** The code that signs in. */
    code = '123456';

    /** How many times a sign-in email was asked for. */
    codeRequests = 0;

    /** What /projects answers from: the projects that aren't dropped, as the API sends them. */
    projects: { id: number; name: string; tier: 'main' | 'secondary' | 'fun' | null; status: 'active' | 'parked' | 'proposed'; owner: string | null }[] = [
        { id: 1, name: 'Donna', tier: 'main', status: 'active', owner: null },
        { id: 2, name: 'Kitchen remodel', tier: 'secondary', status: 'active', owner: 'Kristen' },
    ];

    /** What /people answers from: everyone but Josh and Donna. */
    people: { id: number; name: string; relationship: string | null }[] = [
        { id: 3, name: 'Kristen', relationship: 'wife' },
        { id: 4, name: 'Sam', relationship: null },
    ];

    /** What /cost answers from: the two reports GET /costs returns. */
    commandCosts: { today: FakeCostReport; this_month: FakeCostReport } = {
        today: {
            period: 'today',
            from: '2026-11-28T06:00:00+00:00',
            until: '2026-11-29T06:00:00+00:00',
            total_usd: '0.42',
            calls: 18,
            web_searches: 0,
            by_purpose: { embedding: { usd: '0.01', calls: 3 }, reply: { usd: '0.39', calls: 12 }, tagging: { usd: '0.02', calls: 3 } },
        },
        this_month: {
            period: 'this_month',
            from: '2026-11-01T05:00:00+00:00',
            until: '2026-12-01T06:00:00+00:00',
            total_usd: '6.10',
            calls: 241,
            web_searches: 2,
            by_purpose: {
                embedding: { usd: '0.08', calls: 31 },
                extraction: { usd: '1.12', calls: 27 },
                reply: { usd: '4.71', calls: 158 },
                tagging: { usd: '0.19', calls: 25 },
            },
        },
    };

    private nextId = 1;
    private plans = new Map<number, { polls: number; plan: ReplyPlan }>();

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

        if (path === '/projects' && method === 'GET') {
            return json(200, { data: this.projects });
        }

        if (path === '/people' && method === 'GET') {
            return json(200, { data: this.people });
        }

        if (path === '/costs' && method === 'GET') {
            return json(200, { data: this.commandCosts });
        }

        if (path === '/messages' && method === 'GET') {
            return json(200, this.page(url.searchParams));
        }

        if (path === '/messages' && method === 'POST') {
            if (this.dropSends) {
                return route.abort('connectionfailed');
            }

            return json(202, { data: this.accept(request.postDataJSON().body, request.headers()['idempotency-key'] ?? null) });
        }

        const retry = /^\/messages\/(\d+)\/retry$/.exec(path);

        if (retry && method === 'POST') {
            const reply = this.find(Number(retry[1]));

            reply.status = 'pending';
            reply.error = null;
            this.plan(reply);

            return json(202, { data: reply });
        }

        const one = /^\/messages\/(\d+)$/.exec(path);

        if (one && method === 'GET') {
            return json(200, { data: this.poll(this.find(Number(one[1]))) });
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

    /** Save a send, or return the original pair when its key has been seen before. */
    private accept(body: string, key: string | null) {
        const existing = key === null ? undefined : this.messages.find((message) => message.client_key === key);

        if (existing) {
            return { message: existing, reply: this.messages.find((message) => message.in_reply_to_id === existing.id) ?? null };
        }

        this.sends.push({ key, body });

        const now = new Date().toISOString();
        const message = this.said('josh', body, now, { client_key: key });
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
        }

        return message;
    }
}
