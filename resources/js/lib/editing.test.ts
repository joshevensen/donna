import { describe, expect, it } from 'vitest';
import type { FileDetail } from '../api/types';
import { conflictWords, type EditEvent, editReducer, type EditState, isDirty, READING } from './editing';

function file(overrides: Partial<FileDetail> = {}): FileDetail {
    return {
        id: 7,
        name: 'launch-plan.md',
        kind: 'markdown',
        mime: 'text/markdown',
        size_bytes: 3072,
        kept: true,
        deleted: false,
        extraction: 'ready',
        extraction_error: null,
        meta: {},
        version: 4,
        last_author: 'josh',
        editable: true,
        created_at: '2026-11-27T20:14:00Z',
        updated_at: '2026-11-27T20:14:00Z',
        body: '## Plan\n',
        truncated: false,
        table: null,
        ...overrides,
    };
}

const after = (...events: EditEvent[]): EditState => events.reduce(editReducer, READING);

describe('editing a file', () => {
    it('starts from the file’s text and its version', () => {
        expect(after({ type: 'start', file: file(), draft: null })).toMatchObject({ mode: 'editing', text: '## Plan\n', baseVersion: 4, restored: false });
    });

    it('brings back unsaved text from before, still based on the version it started from', () => {
        const state = after({ type: 'start', file: file({ version: 6 }), draft: { body: '## Plan\nMine.', baseVersion: 4 } });

        expect(state).toMatchObject({ text: '## Plan\nMine.', baseVersion: 4, restored: true });
        expect(isDirty(state)).toBe(true);
    });

    it('goes back to reading once saved', () => {
        expect(after({ type: 'start', file: file(), draft: null }, { type: 'typed', text: 'New' }, { type: 'saving' }, { type: 'saved' })).toEqual(READING);
    });

    it('keeps the text and says why when a save fails', () => {
        const state = after({ type: 'start', file: file(), draft: null }, { type: 'typed', text: 'New' }, { type: 'saving' }, { type: 'failed', message: "Couldn't save." });

        expect(state).toMatchObject({ mode: 'editing', text: 'New', saving: false, error: "Couldn't save." });
    });

    it('asks before throwing away changes, and not when there are none', () => {
        const untouched = after({ type: 'start', file: file(), draft: null }, { type: 'cancel' });
        const asked = after({ type: 'start', file: file(), draft: null }, { type: 'typed', text: 'New' }, { type: 'cancel' });

        expect(untouched).toEqual(READING);
        expect(asked).toMatchObject({ mode: 'editing', text: 'New', confirmingDiscard: true });
        expect(editReducer(asked, { type: 'keep-editing' })).toMatchObject({ mode: 'editing', confirmingDiscard: false });
        expect(editReducer(asked, { type: 'cancel' })).toEqual(READING);
    });
});

describe('a save refused because the file changed', () => {
    const theirs = file({ version: 5, last_author: 'donna', body: '## Plan\nHers.' });
    const conflicted = after({ type: 'start', file: file(), draft: null }, { type: 'typed', text: '## Plan\nMine.' }, { type: 'saving' }, { type: 'conflicted', current: theirs });

    it('keeps his text where it is until he chooses', () => {
        expect(conflicted).toMatchObject({ mode: 'editing', text: '## Plan\nMine.', saving: false, baseVersion: 4, conflict: { version: 5 } });
    });

    it('"Keep mine" saves his text on top of the version that got in first', () => {
        expect(editReducer(conflicted, { type: 'keep-mine' })).toMatchObject({ text: '## Plan\nMine.', baseVersion: 5, conflict: null, saving: true });
    });

    it('"Take Donna’s" drops his text and goes back to reading', () => {
        expect(editReducer(conflicted, { type: 'take-theirs' })).toEqual(READING);
    });

    it('names who changed it', () => {
        expect(conflictWords(theirs)).toEqual({ message: "Donna changed this file while you were editing, so it wasn't saved.", take: "Take Donna's" });
        expect(conflictWords(file({ last_author: 'josh' }))).toEqual({
            message: "This file changed somewhere else while you were editing, so it wasn't saved.",
            take: 'Take the other version',
        });
    });
});
