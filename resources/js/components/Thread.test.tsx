import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { FileItem, MessageFile } from '../api/types';
import { buildRows, type TimelineItem } from '../lib/timeline';
import { Thread } from './Thread';

const now = new Date('2026-11-27T21:00:00Z');

function card(overrides: Partial<MessageFile> = {}): MessageFile {
    return { id: 7, name: 'stripe-payouts-nov.csv', kind: 'csv', role: 'attached', kept: false, deleted: false, size_bytes: 49_152, meta: { rows: 1284 }, note: null, ...overrides };
}

function listed(overrides: Partial<FileItem> = {}): FileItem {
    return {
        id: 7,
        name: 'stripe-payouts-nov.csv',
        kind: 'csv',
        mime: 'text/csv',
        size_bytes: 49_152,
        kept: false,
        deleted: false,
        extraction: 'ready',
        extraction_error: null,
        meta: { rows: 1284 },
        version: 1,
        last_author: 'josh',
        editable: false,
        created_at: '2026-11-27T20:03:00Z',
        updated_at: '2026-11-27T20:03:00Z',
        ...overrides,
    };
}

let nextId = 1;

function message(author: 'josh' | 'donna', bubbles: string[], files: MessageFile[]): TimelineItem {
    return {
        kind: 'message',
        message: {
            id: nextId++,
            author,
            body: bubbles.join('\n---\n'),
            bubbles,
            status: 'complete',
            in_reply_to_id: null,
            error: null,
            sources: [],
            actions: [],
            files,
            client_key: null,
            created_at: '2026-11-27T20:05:00Z',
        },
    };
}

function show(items: TimelineItem[]) {
    const onOpenFile = vi.fn();
    const onRetrySend = vi.fn();
    const onDiscardSend = vi.fn();

    render(
        <Thread
            rows={buildRows(items, { timeZone: 'America/Chicago', now, typing: false })}
            timeZone="America/Chicago"
            hasOlder={false}
            loadingOlder={false}
            onLoadOlder={vi.fn()}
            onRetrySend={onRetrySend}
            onDiscardSend={onDiscardSend}
            onRetryReply={vi.fn()}
            onShowSources={vi.fn()}
            onOpenFile={onOpenFile}
            runAction={vi.fn()}
        />,
    );

    return { onOpenFile, onRetrySend, onDiscardSend };
}

describe('file cards in the thread', () => {
    it('shows what Josh attached as a card that opens the file, without toggling the bubble’s time', async () => {
        const user = userEvent.setup();
        const { onOpenFile } = show([message('josh', ['Which of these payouts are late?'], [card()])]);

        const open = screen.getByRole('button', { name: 'Open stripe-payouts-nov.csv' });

        expect(within(open).getByText('CSV · 1,284 rows · 48 KB')).toBeTruthy();

        await user.click(open);

        expect(onOpenFile).toHaveBeenCalledExactlyOnceWith(7);
        expect(screen.queryByText('2:05 PM')).toBeNull();
    });

    // A keep Donna made on her own has to be visible without reading her reply.
    it('shows what Donna edited under her reply, with what changed and a Kept badge', () => {
        show([
            message('josh', ['Replace Fibermade Co with Fibermade in the launch plan.'], []),
            message('donna', ['Done, 14 of them.'], [card({ id: 9, name: 'launch-plan.md', kind: 'markdown', role: 'edited', kept: true, note: "Replaced 14 matches of 'Fibermade Co'" })]),
        ]);

        const edited = screen.getByRole('button', { name: 'Open launch-plan.md, kept, edited' });

        expect(within(edited).getByText("Edited · Replaced 14 matches of 'Fibermade Co'")).toBeTruthy();
        expect(within(edited).getByText('Kept')).toBeTruthy();
    });

    it('leaves a deleted file’s card in place, saying so, with nothing to open', () => {
        show([message('josh', ['Old export.'], [card({ deleted: true, kept: true })])]);

        expect(screen.getByText('Deleted')).toBeTruthy();
        expect(screen.queryByRole('button', { name: /Open stripe-payouts-nov.csv/ })).toBeNull();
        expect(screen.queryByText('Kept')).toBeNull();
    });

    it('answers /files with kept files first, then the recent ones, each with when it arrived', async () => {
        const user = userEvent.setup();
        const note = {
            id: 'n1',
            text: '',
            createdAt: '2026-11-27T20:10:00Z',
            files: [
                listed({ id: 1, name: 'launch-plan.md', kind: 'markdown', kept: true, created_at: new Date().toISOString() }),
                listed({ id: 2, name: 'houston-logistics.pdf', kind: 'pdf', kept: true, created_at: '2025-11-24T18:00:00Z' }),
                listed({ id: 3 }),
            ],
        };

        const { onOpenFile } = show([{ kind: 'note', note }]);

        const kept = screen.getByRole('region', { name: 'Kept' });
        const recent = screen.getByRole('region', { name: 'Recent, not kept' });

        expect(within(kept).getAllByRole('button').map((button) => button.getAttribute('aria-label'))).toEqual(['Open launch-plan.md, kept', 'Open houston-logistics.pdf, kept']);
        expect(within(kept).getByText(/^Today, \d+:\d\d [AP]M$/)).toBeTruthy();
        expect(within(kept).getByText('Nov 24, 2025')).toBeTruthy();
        expect(within(recent).getByRole('button', { name: 'Open stripe-payouts-nov.csv' })).toBeTruthy();

        await user.click(within(kept).getByRole('button', { name: 'Open houston-logistics.pdf, kept' }));

        expect(onOpenFile).toHaveBeenCalledExactlyOnceWith(2);
    });
});

describe('a message the server refused', () => {
    // Its files were already sent, or are gone: sending it again would be refused again.
    it('says why and offers to remove it, instead of a retry that can never work', async () => {
        const user = userEvent.setup();
        const { onRetrySend, onDiscardSend } = show([
            {
                kind: 'outbox',
                item: { key: 'k1', body: 'Here it is.', files: [], createdAt: '2026-11-27T20:05:00Z', state: 'failed', refused: 'One of those files can’t be attached.' },
            },
        ]);

        expect(screen.queryByText('Not delivered. Tap to retry')).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Not sent. One of those files can’t be attached. Tap to remove it.' }));

        expect(onDiscardSend).toHaveBeenCalledExactlyOnceWith('k1');
        expect(onRetrySend).not.toHaveBeenCalled();
    });
});
