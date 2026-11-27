import type { Me, Message, MessagePage } from './types';

/** The server answered, and the answer was an error. */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** The first validation message, or the error's own. */
    get firstMessage(): string {
        return Object.values(this.errors)[0]?.[0] ?? this.message;
    }
}

/** The request never got an answer: offline, or the server is unreachable. */
export class NetworkError extends Error {
    constructor() {
        super('Could not reach Donna.');
        this.name = 'NetworkError';
    }
}

type UnauthorizedHandler = () => void;

let onUnauthorized: UnauthorizedHandler | null = null;

/** Called on any 401: the session ran out, so the app goes back to sign-in. */
export function setUnauthorizedHandler(handler: UnauthorizedHandler | null): void {
    onUnauthorized = handler;
}

function cookie(name: string): string | null {
    const match = document.cookie.split('; ').find((entry) => entry.startsWith(`${name}=`));

    return match ? decodeURIComponent(match.slice(name.length + 1)) : null;
}

async function fetchCsrfCookie(): Promise<void> {
    try {
        await fetch('/sanctum/csrf-cookie', { credentials: 'same-origin', headers: { Accept: 'application/json' } });
    } catch {
        throw new NetworkError();
    }
}

interface RequestOptions {
    body?: unknown;
    headers?: Record<string, string>;
    /** A 401 here is an answer, not a session ending (asking who is signed in). */
    quietUnauthorized?: boolean;
}

async function request<T>(method: 'GET' | 'POST' | 'DELETE', path: string, options: RequestOptions = {}, retried = false): Promise<T> {
    if (method !== 'GET' && cookie('XSRF-TOKEN') === null) {
        await fetchCsrfCookie();
    }

    const token = cookie('XSRF-TOKEN');
    let response: Response;

    try {
        response = await fetch(`/api/v1${path}`, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                ...(options.body !== undefined ? { 'Content-Type': 'application/json' } : {}),
                ...(token !== null ? { 'X-XSRF-TOKEN': token } : {}),
                ...options.headers,
            },
            body: options.body !== undefined ? JSON.stringify(options.body) : undefined,
        });
    } catch {
        throw new NetworkError();
    }

    // A stale CSRF token: get a fresh one and try once more.
    if (response.status === 419 && !retried) {
        await fetchCsrfCookie();

        return request<T>(method, path, options, true);
    }

    if (response.status === 401 && !options.quietUnauthorized) {
        onUnauthorized?.();
    }

    const text = await response.text();
    const json = text === '' ? null : safeJson(text);

    if (!response.ok) {
        throw new ApiError(response.status, json?.message ?? `Donna answered with an error (${response.status}).`, json?.errors ?? {});
    }

    return json as T;
}

function safeJson(text: string): any {
    try {
        return JSON.parse(text);
    } catch {
        return null;
    }
}

export const api = {
    me: () => request<{ data: Me }>('GET', '/me', { quietUnauthorized: true }).then((response) => response.data),

    messages: (query: { before?: number; after?: number; limit?: number } = {}) => {
        const params = new URLSearchParams();

        for (const [key, value] of Object.entries(query)) {
            if (value !== undefined) {
                params.set(key, String(value));
            }
        }

        return request<MessagePage>('GET', `/messages?${params}`);
    },

    message: (id: number) => request<{ data: Message }>('GET', `/messages/${id}`).then((response) => response.data),

    /** The key makes a repeat of the same send return the original pair. */
    send: (body: string, key: string) =>
        request<{ data: { message: Message; reply: Message | null } }>('POST', '/messages', {
            body: { body, source: 'web' },
            headers: { 'Idempotency-Key': key },
        }).then((response) => response.data),

    retryReply: (id: number) => request<{ data: Message }>('POST', `/messages/${id}/retry`).then((response) => response.data),

    requestLoginCode: (email: string) => request<null>('POST', '/auth/login-code', { body: { email } }),

    verifyCode: (email: string, code: string) =>
        request<{ data: Me }>('POST', '/auth/verify', { body: { email, code } }).then((response) => response.data),

    verifyLink: (token: string) =>
        request<{ data: Me }>('POST', '/auth/verify-link', { body: { token } }).then((response) => response.data),

    logout: () => request<null>('POST', '/auth/logout'),

    logoutEverywhere: () => request<null>('POST', '/auth/logout-everywhere'),

    revoke: (expires: string, signature: string) =>
        request<null>('POST', `/auth/revoke?expires=${encodeURIComponent(expires)}&signature=${encodeURIComponent(signature)}`),

    savePushSubscription: (subscription: PushSubscriptionJSON) =>
        request<null>('POST', '/push-subscriptions', {
            body: { endpoint: subscription.endpoint, keys: subscription.keys, content_encoding: preferredEncoding() },
        }),

    deletePushSubscription: (endpoint: string) => request<null>('DELETE', '/push-subscriptions', { body: { endpoint } }),
};

function preferredEncoding(): string {
    return ('PushManager' in window ? PushManager.supportedContentEncodings?.[0] : undefined) ?? 'aes128gcm';
}
