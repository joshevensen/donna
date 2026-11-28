import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError } from '../api/client';
import type { Message } from '../api/types';
import { useMessages } from './useMessages';

vi.mock('../api/client', async (original) => ({
    ...(await original<typeof import('../api/client')>()),
    api: { messages: vi.fn(), message: vi.fn(), send: vi.fn(), retryReply: vi.fn(), away: vi.fn() },
}));

function message(id: number, author: 'josh' | 'donna', overrides: Partial<Message> = {}): Message {
    return {
        id,
        author,
        body: '',
        bubbles: [],
        status: 'complete',
        in_reply_to_id: null,
        error: null,
        sources: [],
        actions: [],
        files: [],
        client_key: null,
        created_at: new Date().toISOString(),
        ...overrides,
    };
}

describe('useMessages', () => {
    beforeEach(() => {
        vi.useFakeTimers({ shouldAdvanceTime: true });
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    // Josh sends, locks the phone, and opens the app from the notification twenty minutes
    // later. iOS resumes the page where it was: the reply is still "pending" on the device,
    // and it has to be asked about again however long ago it was sent.
    it('picks up a reply that finished while the app was asleep, long after the dots gave up', async () => {
        const twentyMinutesAgo = new Date(Date.now() - 20 * 60 * 1000).toISOString();
        const question = message(1, 'josh', { body: 'Did it ship?', bubbles: ['Did it ship?'], created_at: twentyMinutesAgo });
        const pending = message(2, 'donna', { status: 'pending', in_reply_to_id: 1, created_at: twentyMinutesAgo });
        const finished = { ...pending, status: 'complete' as const, body: 'Tuesday.\n---\nTracking is in your email.', bubbles: ['Tuesday.', 'Tracking is in your email.'] };

        vi.mocked(api.messages).mockImplementation(async (query = {}) =>
            'after' in query ? { data: [], meta: { next_after: null } } : { data: [pending, question], meta: { next_before: null } },
        );
        vi.mocked(api.message).mockResolvedValue(finished);

        const { result } = renderHook(() => useMessages());

        await waitFor(() => expect(result.current.ready).toBe(true));
        expect(result.current.typing).toBe(false);

        await act(() => vi.advanceTimersByTimeAsync(10_000));

        expect(api.message).toHaveBeenCalledWith(2);

        const reply = result.current.items.find((item) => item.kind === 'message' && item.message.id === 2);

        // It finished long ago, so it shows whole rather than arriving a text at a time.
        expect(reply).toMatchObject({ message: { status: 'complete' }, visibleBubbles: 2 });
        expect(result.current.typing).toBe(false);
    });

    it('reports failed background jobs from the first page and from every poll after it', async () => {
        vi.mocked(api.messages).mockImplementation(async (query = {}) =>
            'after' in query ? { data: [], meta: { next_after: null, failed_jobs: 0 } } : { data: [], meta: { next_before: null, failed_jobs: 3 } },
        );

        const { result } = renderHook(() => useMessages());

        await waitFor(() => expect(result.current.failedJobs).toBe(3));

        // Josh clears them; the next poll takes the strip away.
        await act(() => vi.advanceTimersByTimeAsync(60_000));
        await waitFor(() => expect(result.current.failedJobs).toBe(0));
    });

    // A retry must carry exactly what the first try did: the same key, so it can't send twice, and the same files.
    it('retries an unsent message with the same key and the same files', async () => {
        const file = { id: 7, name: 'stripe-payouts-nov.csv', kind: 'csv' as const, size_bytes: 49_152, meta: { rows: 1284 } };
        const delivered = message(1, 'josh', { files: [{ ...file, role: 'attached', kept: false, deleted: false, note: null }] });

        vi.mocked(api.messages).mockResolvedValue({ data: [], meta: { next_before: null, next_after: null } });
        vi.mocked(api.send).mockRejectedValueOnce(new ApiError(500, 'Oops')).mockResolvedValueOnce({ message: delivered, reply: null });

        const { result } = renderHook(() => useMessages());

        await waitFor(() => expect(result.current.ready).toBe(true));

        act(() => result.current.send('', [file]));

        const unsent = await waitFor(() => {
            const item = result.current.items.find((candidate) => candidate.kind === 'outbox');

            expect(item).toMatchObject({ item: { state: 'failed', files: [file] } });

            return item as Extract<(typeof result.current.items)[number], { kind: 'outbox' }>;
        });

        act(() => result.current.retrySend(unsent.item.key));

        await waitFor(() => expect(result.current.items.some((candidate) => candidate.kind === 'outbox')).toBe(false));

        expect(vi.mocked(api.send).mock.calls).toEqual([
            ['', unsent.item.key, [7]],
            ['', unsent.item.key, [7]],
        ]);
    });

    // The Kept badge follows the file, not the message it happened to arrive on.
    it('remembers the latest name and keep state the server gave for each file, from messages and from the viewer', async () => {
        const kept = { id: 7, name: 'launch-plan.md', kind: 'markdown' as const, role: 'edited' as const, kept: true, deleted: false, size_bytes: 3072, meta: {}, note: null };

        vi.mocked(api.messages).mockResolvedValue({ data: [message(2, 'donna', { files: [kept] })], meta: { next_before: null, next_after: null } });

        const { result } = renderHook(() => useMessages());

        await waitFor(() => expect(result.current.fileStates.get(7)).toEqual({ name: 'launch-plan.md', kept: true, deleted: false }));

        act(() => result.current.noteFile({ id: 7, name: 'launch-plan.md', kept: false, deleted: false }));

        expect(result.current.fileStates.get(7)).toMatchObject({ kept: false });
    });
});
