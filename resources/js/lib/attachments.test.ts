import { describe, expect, it } from 'vitest';
import { ApiError, NetworkError } from '../api/client';
import type { FileItem } from '../api/types';
import { addPicked, blockedReason, canSend, type Chip, chipLine, progressed, readyFiles, refusalReason, refused, removed, restored, uploaded } from './attachments';

function keys(): () => string {
    let next = 0;

    return () => `k${++next}`;
}

function item(overrides: Partial<FileItem> = {}): FileItem {
    return {
        id: 7,
        name: 'launch-plan.md',
        kind: 'markdown',
        mime: 'text/markdown',
        size_bytes: 3072,
        kept: false,
        deleted: false,
        extraction: 'ready',
        extraction_error: null,
        meta: { lines: 42 },
        version: 1,
        last_author: 'josh',
        editable: true,
        created_at: '2026-11-27T20:14:00Z',
        updated_at: '2026-11-27T20:14:00Z',
        ...overrides,
    };
}

const MB = 1024 * 1024;

describe('picking files', () => {
    it('starts an upload for each file, and shows how far it has got', () => {
        const picked = addPicked([], [{ name: 'stripe-payouts-nov.csv', size: 4.8 * MB }], keys());

        expect(picked.uploads).toEqual(['k1']);
        expect(picked.overflow).toBe(false);

        const chips = progressed(picked.chips, 'k1', 0.62);

        expect(chipLine(chips[0]!)).toBe('Uploading… 62% of 4.8 MB');
    });

    it('refuses a file over 10 MB on the spot, without uploading it', () => {
        const { chips, uploads } = addPicked([], [{ name: 'export.csv', size: 10 * MB + 1 }, { name: 'ok.csv', size: 10 * MB }], keys());

        expect(uploads).toEqual([null, 'k2']);
        expect(chips[0]).toMatchObject({ state: 'error', reason: 'Too large. Max 10 MB' });
        expect(chips[1]).toMatchObject({ state: 'uploading' });
    });

    it('takes five files at most, counting the ones already there, and says the rest were left out', () => {
        const first = addPicked([], [{ name: 'a', size: 1 }, { name: 'b', size: 1 }, { name: 'c', size: 1 }], keys());
        const second = addPicked(first.chips, [{ name: 'd', size: 1 }, { name: 'e', size: 1 }, { name: 'f', size: 1 }], keys());

        expect(second.chips.map((chip) => chip.name)).toEqual(['a', 'b', 'c', 'd', 'e']);
        expect(second.uploads).toEqual(['k1', 'k2', null]);
        expect(second.overflow).toBe(true);
    });
});

describe('a chip as its upload ends', () => {
    const uploading = addPicked([], [{ name: 'launch-plan.md', size: 3000 }], keys()).chips;

    it('becomes ready with the file the server made, and shows its size', () => {
        const chips = uploaded(uploading, 'k1', item());

        expect(chips[0]).toMatchObject({ state: 'ready', file: { id: 7, name: 'launch-plan.md', kind: 'markdown', size_bytes: 3072 } });
        expect(chipLine(chips[0]!)).toBe('3 KB');
        expect(readyFiles(chips)).toEqual([{ id: 7, name: 'launch-plan.md', kind: 'markdown', size_bytes: 3072, meta: { lines: 42 } }]);
    });

    it('becomes refused with the reason, and stays until removed', () => {
        const chips = refused(uploading, 'k1', 'Unsupported. Text, PDF and images only');

        expect(chipLine(chips[0]!)).toBe('Unsupported. Text, PDF and images only');
        expect(readyFiles(chips)).toEqual([]);
        expect(removed(chips, 'k1')).toEqual([]);
    });

    it('ignores progress that arrives after it has finished', () => {
        const chips = progressed(uploaded(uploading, 'k1', item()), 'k1', 0.5);

        expect(chips[0]!.state).toBe('ready');
    });
});

describe('whether send is allowed', () => {
    const ready: Chip = { key: 'a', name: 'a.md', size: 1, state: 'ready', file: { id: 1, name: 'a.md', kind: 'markdown', size_bytes: 1, meta: {} } };
    const uploading: Chip = { key: 'b', name: 'b.csv', size: 1, state: 'uploading', progress: 0.2 };
    const bad: Chip = { key: 'c', name: 'c.xlsx', size: 1, state: 'error', reason: 'Unsupported. Text, PDF and images only' };

    it.each([
        ['words and no files', [], 'Hello', true, null],
        ['nothing at all', [], '   ', false, null],
        ['a ready file and no words', [ready], '', true, null],
        ['a file still uploading', [ready, uploading], 'Look', false, 'Send waits until the upload finishes.'],
        ['a refused file', [ready, bad], 'Look', false, 'Send waits until the refused file is removed.'],
        ['both', [uploading, bad], 'Look', false, 'Send waits until the upload finishes and the refused file is removed.'],
        ['several of each', [uploading, uploading, bad, bad], '', false, 'Send waits until the uploads finish and the refused files are removed.'],
    ] as const)('with %s', (_case, chips, text, allowed, reason) => {
        expect(canSend([...chips], text)).toBe(allowed);
        expect(blockedReason([...chips])).toBe(reason);
    });
});

describe('refusalReason', () => {
    it.each([
        ['the server refusing the file', new ApiError(422, 'Nope', { file: ['Unsupported. Text, PDF and images only'] }), 'Unsupported. Text, PDF and images only'],
        ['the web server refusing its size', new ApiError(413, 'Too big'), 'Too large. Max 10 MB'],
        ['too many uploads', new ApiError(429, 'Slow down'), 'Too many uploads at once. Remove it and try again in a minute.'],
        ['no connection', new NetworkError(), "Couldn't upload. Remove it and try again."],
        ['anything else', new ApiError(500, 'Oops'), "Couldn't upload. Remove it and try again."],
    ])('explains %s', (_case, error, reason) => {
        expect(refusalReason(error)).toBe(reason);
    });
});

describe('restored', () => {
    // iOS closes Home Screen apps freely: files already uploaded come back with the draft.
    it('brings ready files back as chips', () => {
        const chips = restored([{ id: 7, name: 'launch-plan.md', kind: 'markdown', size_bytes: 3072, meta: {} }]);

        expect(chips).toMatchObject([{ state: 'ready', name: 'launch-plan.md', size: 3072 }]);
        expect(canSend(chips, '')).toBe(true);
    });
});
