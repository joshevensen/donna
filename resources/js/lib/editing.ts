import type { FileDetail } from '../api/types';
import type { EditDraft } from './storage';

/*
 | Edit mode in the viewer, as a state machine: reading, editing, saving, a
 | save that was refused because the file changed underneath, and the two
 | ways out of that. The component sends events and draws the state.
 */

export type EditState =
    | { mode: 'reading' }
    | {
          mode: 'editing';
          text: string;
          /** The file's text when editing began: unchanged text needs no warning on cancel. */
          original: string;
          /** The version this text is based on; a save says so, and is refused if the file has moved on. */
          baseVersion: number;
          saving: boolean;
          /** The file as it now is, when a save was refused. Set until Josh chooses. */
          conflict: FileDetail | null;
          error: string | null;
          /** Cancel was pressed with unsaved changes; waiting for him to confirm. */
          confirmingDiscard: boolean;
          /** The text came back from an earlier, unsaved edit. */
          restored: boolean;
      };

export type EditEvent =
    | { type: 'start'; file: FileDetail; draft: EditDraft | null }
    | { type: 'typed'; text: string }
    | { type: 'saving' }
    | { type: 'saved' }
    | { type: 'conflicted'; current: FileDetail }
    | { type: 'failed'; message: string }
    | { type: 'keep-mine' }
    | { type: 'take-theirs' }
    | { type: 'cancel' }
    | { type: 'keep-editing' };

export const READING: EditState = { mode: 'reading' };

export function isDirty(state: EditState): boolean {
    return state.mode === 'editing' && state.text !== state.original;
}

export function editReducer(state: EditState, event: EditEvent): EditState {
    if (event.type === 'start') {
        const body = event.file.body ?? '';
        // An unsaved edit from before the app was closed comes back, still based on the version it started from.
        const draft = event.draft !== null && event.draft.body !== body ? event.draft : null;

        return {
            mode: 'editing',
            text: draft?.body ?? body,
            original: body,
            baseVersion: draft?.baseVersion ?? event.file.version ?? 1,
            saving: false,
            conflict: null,
            error: null,
            confirmingDiscard: false,
            restored: draft !== null,
        };
    }

    if (state.mode !== 'editing') {
        return state;
    }

    switch (event.type) {
        case 'typed':
            return { ...state, text: event.text, error: null, confirmingDiscard: false };

        case 'saving':
            return { ...state, saving: true, error: null, confirmingDiscard: false };

        case 'saved':
            return READING;

        // The file changed while he was editing: nothing was saved, and his text stays put.
        case 'conflicted':
            return { ...state, saving: false, conflict: event.current };

        case 'failed':
            return { ...state, saving: false, error: event.message };

        // Save his on top of the other change, which stays in the file's history.
        case 'keep-mine':
            return state.conflict === null ? state : { ...state, baseVersion: state.conflict.version ?? state.baseVersion, conflict: null, saving: true, error: null };

        case 'take-theirs':
            return READING;

        case 'cancel':
            return isDirty(state) && !state.confirmingDiscard ? { ...state, confirmingDiscard: true } : READING;

        case 'keep-editing':
            return { ...state, confirmingDiscard: false };
    }
}

/** What the conflict banner says, and what its second button is called. */
export function conflictWords(current: FileDetail): { message: string; take: string } {
    return current.last_author === 'donna'
        ? { message: "Donna changed this file while you were editing, so it wasn't saved.", take: "Take Donna's" }
        : { message: "This file changed somewhere else while you were editing, so it wasn't saved.", take: 'Take the other version' };
}
