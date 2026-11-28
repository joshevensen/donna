import type { AttachedFile } from './attachments';

/*
 | What has to outlive the app being closed: the text in the input and the
 | files uploaded with it, messages that haven't been delivered, an edit to a
 | file that hasn't been saved, and which of Donna's buttons were used. iOS
 | kills Home Screen apps freely, so anything only in memory is lost.
 */

/** A message Josh sent that the server hasn't confirmed. */
export interface OutboxItem {
    /** The Idempotency-Key: a retry with it can never send the message twice. */
    key: string;
    body: string;
    /** The uploaded files it carries; a retry sends the same ones. */
    files?: AttachedFile[];
    createdAt: string;
    state: 'sending' | 'failed';
    /** It failed for lack of a connection, so it goes out by itself when there is one. */
    waitingForConnection?: boolean;
}

/** Text Josh typed into a file's edit mode and hasn't saved, with the version it started from. */
export interface EditDraft {
    body: string;
    baseVersion: number;
}

const DRAFT = 'donna.draft';
const ATTACHMENTS = 'donna.draft.files';
const EDIT = 'donna.edit.';
const OUTBOX = 'donna.outbox';
const DONE_ACTIONS = 'donna.actions.done';
const LOGIN = 'donna.login';
const ME = 'donna.me';

function read<T>(key: string, fallback: T): T {
    try {
        const stored = localStorage.getItem(key);

        return stored === null ? fallback : (JSON.parse(stored) as T);
    } catch {
        return fallback;
    }
}

function write(key: string, value: unknown): void {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch {
        // Storage is full or turned off: the app still works, it just forgets on reload.
    }
}

function remove(key: string): void {
    try {
        localStorage.removeItem(key);
    } catch {
        // As above.
    }
}

export const storage = {
    draft: () => read<string>(DRAFT, ''),
    saveDraft: (text: string) => write(DRAFT, text),

    /** The files already uploaded for the message being written. */
    attachments: () => read<AttachedFile[]>(ATTACHMENTS, []),
    saveAttachments: (files: AttachedFile[]) => write(ATTACHMENTS, files),

    editDraft: (fileId: number) => read<EditDraft | null>(`${EDIT}${fileId}`, null),
    saveEditDraft: (fileId: number, draft: EditDraft | null) => (draft === null ? remove(`${EDIT}${fileId}`) : write(`${EDIT}${fileId}`, draft)),

    outbox: () => read<OutboxItem[]>(OUTBOX, []),
    saveOutbox: (items: OutboxItem[]) => write(OUTBOX, items),

    doneActions: () => read<string[]>(DONE_ACTIONS, []),
    saveDoneActions: (ids: string[]) => write(DONE_ACTIONS, ids.slice(-200)),

    /** Who was signed in last time, so the app opens straight to the chat instead of a blank wait. */
    me: <T,>() => read<T | null>(ME, null),
    saveMe: (me: unknown) => write(ME, me),

    /** The sign-in in progress, so leaving for the Mail app and coming back lands on the code field. */
    login: () => read<{ email: string; requestedAt: number } | null>(LOGIN, null),
    saveLogin: (login: { email: string; requestedAt: number } | null) => write(LOGIN, login),
};
