import type { FileDetail, FileItem, Me, Message, MessagePage } from './types';

/** The server answered, and the answer was an error. */
export class ApiError extends Error {
    constructor(
        public readonly status: number,
        message: string,
        public readonly errors: Record<string, string[]> = {},
        /** The `data` the error came with, if any: a refused save carries the file as it now is. */
        public readonly data: unknown = undefined,
    ) {
        super(message);
        this.name = 'ApiError';
    }

    /** The first validation message, or the error's own. */
    get firstMessage(): string {
        return Object.values(this.errors)[0]?.[0] ?? this.message;
    }
}

/** An upload Josh cancelled by removing its chip. Not a failure. */
export class UploadCancelled extends Error {
    constructor() {
        super('The upload was cancelled.');
        this.name = 'UploadCancelled';
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
    /** Let the request finish even if the page is being hidden or closed. */
    keepalive?: boolean;
}

async function request<T>(method: 'GET' | 'POST' | 'PUT' | 'PATCH' | 'DELETE', path: string, options: RequestOptions = {}, retried = false): Promise<T> {
    if (method !== 'GET' && cookie('XSRF-TOKEN') === null) {
        await fetchCsrfCookie();
    }

    const token = cookie('XSRF-TOKEN');
    let response: Response;

    try {
        response = await fetch(`/api/v1${path}`, {
            method,
            credentials: 'same-origin',
            keepalive: options.keepalive,
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
        throw new ApiError(response.status, json?.message ?? `Donna answered with an error (${response.status}).`, json?.errors ?? {}, json?.data);
    }

    return json as T;
}

/** An upload in flight: its result, and a way to stop it. */
export interface Upload {
    done: Promise<FileItem>;
    cancel: () => void;
}

/**
 * Upload one file. This is the one request made with XMLHttpRequest instead
 * of fetch: fetch can't report how much of an upload has gone, and the chip
 * shows it. Everything else (the CSRF header, a stale token, a 401) is
 * handled the way `request` handles it.
 */
function upload(file: File, onProgress: (fraction: number) => void): Upload {
    let current: XMLHttpRequest | null = null;
    let cancelled = false;

    const attempt = () =>
        new Promise<{ status: number; text: string }>((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const token = cookie('XSRF-TOKEN');
            const form = new FormData();

            form.append('file', file, file.name);

            current = xhr;
            xhr.open('POST', '/api/v1/files');
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

            if (token !== null) {
                xhr.setRequestHeader('X-XSRF-TOKEN', token);
            }

            xhr.upload.onprogress = (event) => {
                if (event.lengthComputable && event.total > 0) {
                    onProgress(event.loaded / event.total);
                }
            };

            xhr.onload = () => resolve({ status: xhr.status, text: xhr.responseText });
            xhr.onerror = () => reject(new NetworkError());
            xhr.ontimeout = () => reject(new NetworkError());
            xhr.onabort = () => reject(new UploadCancelled());
            xhr.send(form);
        });

    /** Cancelling between two steps has no request to abort, so each step checks first. */
    const send = () => (cancelled ? Promise.reject(new UploadCancelled()) : attempt());

    const run = async (): Promise<FileItem> => {
        if (cookie('XSRF-TOKEN') === null) {
            await fetchCsrfCookie();
        }

        let response = await send();

        // A stale CSRF token: get a fresh one and try once more.
        if (response.status === 419) {
            await fetchCsrfCookie();
            response = await send();
        }

        if (response.status === 401) {
            onUnauthorized?.();
        }

        const json = response.text === '' ? null : safeJson(response.text);

        if (response.status < 200 || response.status >= 300) {
            throw new ApiError(response.status, json?.message ?? `Donna answered with an error (${response.status}).`, json?.errors ?? {}, json?.data);
        }

        return json.data as FileItem;
    };

    return {
        done: run().catch((error) => {
            throw cancelled ? new UploadCancelled() : error;
        }),
        cancel: () => {
            cancelled = true;
            current?.abort();
        },
    };
}

/** Where a file's bytes are served from. The preview is a smaller copy of an image. */
export function fileContentUrl(id: number, variant?: 'preview'): string {
    return `/api/v1/files/${id}/content${variant === 'preview' ? '?variant=preview' : ''}`;
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
    send: (body: string, key: string, fileIds: number[] = []) =>
        request<{ data: { message: Message; reply: Message | null } }>('POST', '/messages', {
            body: { body, source: 'web', ...(fileIds.length > 0 ? { file_ids: fileIds } : {}) },
            headers: { 'Idempotency-Key': key },
        }).then((response) => response.data),

    uploadFile: upload,

    /** Kept files first, then the working files used recently. */
    files: (query = '') =>
        request<{ data: FileItem[] }>('GET', `/files${query === '' ? '' : `?q=${encodeURIComponent(query)}`}`).then((response) => response.data),

    file: (id: number) => request<{ data: FileDetail }>('GET', `/files/${id}`).then((response) => response.data),

    /** A manual save. Refused with a 409, carrying the file as it now is, when someone changed it since `baseVersion`. */
    saveFile: (id: number, body: string, baseVersion: number) =>
        request<{ data: FileDetail }>('PUT', `/files/${id}`, { body: { body, base_version: baseVersion } }).then((response) => response.data),

    updateFile: (id: number, changes: { kept?: boolean; name?: string }) =>
        request<{ data: FileDetail }>('PATCH', `/files/${id}`, { body: changes }).then((response) => response.data),

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

    /** The app is going to the background: a reply from here on should notify. */
    away: () => request<null>('DELETE', '/presence', { keepalive: true, quietUnauthorized: true }),
};

function preferredEncoding(): string {
    return ('PushManager' in window ? PushManager.supportedContentEncodings?.[0] : undefined) ?? 'aes128gcm';
}
