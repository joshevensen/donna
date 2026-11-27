import { act, renderHook, waitFor } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { api } from '../api/client';
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
});
