import { useCallback, useEffect, useReducer, useRef, useState } from 'react';
import { api, ApiError, NetworkError } from '../api/client';
import type { Costs, FileItem, Message } from '../api/types';
import type { AttachedFile } from '../lib/attachments';
import type { FileState } from '../lib/files';
import { uuid } from '../lib/platform';
import { RevealQueue } from '../lib/reveal';
import { type OutboxItem, storage } from '../lib/storage';
import type { Note, TimelineItem } from '../lib/timeline';
import { useOnline } from './useOnline';

const PAGE = 30;
const POLL_WHILE_WAITING_MS = 2_000;
const POLL_WHILE_IDLE_MS = 10_000;

/** A reply still pending after this long isn't being written any more; stop showing the dots for it. */
const STALE_PENDING_MS = 15 * 60 * 1000;

export interface Conversation {
    items: TimelineItem[];
    /** Donna is writing, or a multi-bubble reply is still arriving. */
    typing: boolean;
    /** No connection: the strip shows and sends wait. */
    offline: boolean;
    /** Background jobs that gave up: the strip says Donna may not be remembering. */
    failedJobs: number;
    /** Spend today and this cycle, as of the last poll; null until the server has said. */
    costs: Costs | null;
    /** The first page has loaded. */
    ready: boolean;
    loadingOlder: boolean;
    hasOlder: boolean;
    /** The latest known name and keep state of every file seen, so each of its cards agrees. */
    fileStates: ReadonlyMap<number, FileState>;
    send: (body: string, files?: AttachedFile[]) => void;
    retrySend: (key: string) => void;
    /** Drop a message the server refused. */
    discardSend: (key: string) => void;
    retryReply: (id: number) => void;
    loadOlder: () => void;
    /** Show something on this device only, as Donna's (the default) or as what Josh typed. With files, the answer to /files. */
    addNote: (text: string, who?: Note['who'], files?: FileItem[]) => void;
    /** Take in a file as the server just described it (the viewer saw it, or changed it). */
    noteFile: (file: FileState & { id: number }) => void;
}

function localTime(item: TimelineItem): string {
    return item.kind === 'outbox' ? item.item.createdAt : item.kind === 'note' ? item.note.createdAt : '';
}

/**
 * The one conversation: what the server has, what this device hasn't
 * managed to send yet, and the polling that keeps the two in step. There are
 * no websockets; a single user doesn't need them.
 */
export function useMessages(): Conversation {
    const online = useOnline();
    const [, render] = useReducer((count: number) => count + 1, 0);

    // Messages by id. Kept in a ref so polling always reads the latest, with a render forced on change.
    const messages = useRef(new Map<number, Message>());
    const reveal = useRef<RevealQueue | null>(null);
    reveal.current ??= new RevealQueue(render);

    const [outbox, setOutbox] = useState<OutboxItem[]>(() => storage.outbox());
    const [notes, setNotes] = useState<Note[]>([]);
    const [ready, setReady] = useState(false);
    const [nextBefore, setNextBefore] = useState<number | null>(null);
    const [loadingOlder, setLoadingOlder] = useState(false);
    const [unreachable, setUnreachable] = useState(false);
    const [failedJobs, setFailedJobs] = useState(0);
    const [costs, setCosts] = useState<Costs | null>(null);
    const unreachableRef = useRef(unreachable);
    unreachableRef.current = unreachable;

    const outboxRef = useRef(outbox);
    outboxRef.current = outbox;

    // When each retried reply was retried: it is old, but she is writing it now.
    const retriedAt = useRef(new Map<number, number>());

    // What each file is called and whether it is kept, as of the last time the server said.
    // Every message carries its files' state as it was when fetched, so the latest fetch wins.
    const [fileStates, setFileStates] = useState<ReadonlyMap<number, FileState>>(() => new Map());

    const noteFiles = useCallback((files: Array<FileState & { id: number }>) => {
        if (files.length === 0) {
            return;
        }

        setFileStates((known) => {
            const next = new Map(known);

            for (const file of files) {
                next.set(file.id, { name: file.name, kept: file.kept, deleted: file.deleted });
            }

            return next;
        });
    }, []);

    const noteFile = useCallback((file: FileState & { id: number }) => noteFiles([file]), [noteFiles]);

    /** Is Donna writing this one right now? */
    const isWaiting = useCallback((message: Message): boolean => {
        const since = retriedAt.current.get(message.id) ?? Date.parse(message.created_at);

        return message.author === 'donna' && message.status === 'pending' && Date.now() - since < STALE_PENDING_MS;
    }, []);

    const updateOutbox = useCallback((change: (items: OutboxItem[]) => OutboxItem[]) => {
        setOutbox((items) => {
            const next = change(items);

            storage.saveOutbox(next);

            return next;
        });
    }, []);

    /** Take in messages from the server, noticing replies that finished while the app was watching. */
    const merge = useCallback(
        (incoming: Message[]) => {
            if (incoming.length === 0) {
                return;
            }

            const delivered = new Set<string>();

            for (const message of incoming) {
                const known = messages.current.get(message.id);

                // Arriving a text at a time is for a reply that finishes while he is waiting
                // on it. One that finished long ago, found on coming back, shows whole.
                if (known !== undefined && isWaiting(known) && message.status === 'complete') {
                    reveal.current?.enqueue(message.id, message.bubbles);
                }

                if (message.client_key !== null) {
                    delivered.add(message.client_key);
                }

                messages.current.set(message.id, message);
            }

            // A send whose answer was lost shows up here as delivered: it is no longer unsent.
            if (outboxRef.current.some((item) => delivered.has(item.key))) {
                updateOutbox((items) => items.filter((item) => !delivered.has(item.key)));
            }

            noteFiles(incoming.flatMap((message) => message.files ?? []));

            render();
        },
        [isWaiting, noteFiles, updateOutbox],
    );

    /** Run a request, tracking whether the server can be reached at all. */
    const reach = useCallback(async <T,>(call: () => Promise<T>): Promise<T> => {
        try {
            const result = await call();

            setUnreachable(false);

            return result;
        } catch (error) {
            if (error instanceof NetworkError) {
                setUnreachable(true);
            }

            throw error;
        }
    }, []);

    const deliver = useCallback(
        async (item: OutboxItem) => {
            updateOutbox((items) => items.map((other) => (other.key === item.key ? { ...other, state: 'sending' } : other)));

            try {
                const sent = await reach(() =>
                    api.send(
                        item.body,
                        item.key,
                        (item.files ?? []).map((file) => file.id),
                    ),
                );

                updateOutbox((items) => items.filter((other) => other.key !== item.key));
                merge(sent.reply === null ? [sent.message] : [sent.message, sent.reply]);
            } catch (error) {
                const waitingForConnection = error instanceof NetworkError;

                // A 422 is the server saying this message can never be sent as it is (a file
                // it names is gone, or already went with another message). Retrying can't help.
                const refused = error instanceof ApiError && error.status === 422 ? error.firstMessage : undefined;

                updateOutbox((items) =>
                    items.map((other) => (other.key === item.key ? { ...other, state: 'failed', waitingForConnection, refused } : other)),
                );
            }
        },
        [merge, reach, updateOutbox],
    );

    const send = useCallback(
        (body: string, files: AttachedFile[] = []) => {
            const item: OutboxItem = { key: uuid(), body, ...(files.length > 0 ? { files } : {}), createdAt: new Date().toISOString(), state: 'sending' };

            updateOutbox((items) => [...items, item]);

            if (!navigator.onLine) {
                updateOutbox((items) =>
                    items.map((other) => (other.key === item.key ? { ...other, state: 'failed', waitingForConnection: true } : other)),
                );

                return;
            }

            void deliver(item);
        },
        [deliver, updateOutbox],
    );

    const retrySend = useCallback(
        (key: string) => {
            const item = outboxRef.current.find((other) => other.key === key);

            if (item !== undefined && item.state === 'failed') {
                void deliver(item);
            }
        },
        [deliver],
    );

    /** Take a message that can't be sent out of the thread. */
    const discardSend = useCallback(
        (key: string) => updateOutbox((items) => items.filter((other) => other.key !== key)),
        [updateOutbox],
    );

    const retryReply = useCallback(
        (id: number) => {
            const failed = messages.current.get(id);

            if (failed === undefined) {
                return;
            }

            // Show the dots at once; put the failure back if the request doesn't land.
            retriedAt.current.set(id, Date.now());
            messages.current.set(id, { ...failed, status: 'pending', error: null });
            render();

            reach(() => api.retryReply(id))
                .then((reply) => merge([reply]))
                .catch(() => {
                    retriedAt.current.delete(id);
                    messages.current.set(id, failed);
                    render();
                });
        },
        [merge, reach],
    );

    const loadOlder = useCallback(() => {
        if (nextBefore === null || loadingOlder) {
            return;
        }

        setLoadingOlder(true);

        reach(() => api.messages({ before: nextBefore, limit: PAGE }))
            .then((page) => {
                merge(page.data);
                setNextBefore(page.meta.next_before ?? null);
            })
            .catch(() => undefined)
            .finally(() => setLoadingOlder(false));
    }, [loadingOlder, merge, nextBefore, reach]);

    const addNote = useCallback(
        (text: string, who: Note['who'] = 'donna', files?: FileItem[]) => {
            setNotes((existing) => [...existing, { id: uuid(), who, text, ...(files ? { files } : {}), createdAt: new Date().toISOString() }]);
            noteFiles(files ?? []);
        },
        [noteFiles],
    );

    // The first page, then anything left unsent from last time.
    useEffect(() => {
        let cancelled = false;

        const load = () =>
            reach(() => api.messages({ limit: PAGE }))
                .then((page) => {
                    if (cancelled) {
                        return;
                    }

                    merge(page.data);
                    setNextBefore(page.meta.next_before ?? null);
                    setFailedJobs(page.meta.failed_jobs ?? 0);
                    setCosts(page.meta.costs ?? null);
                    setReady(true);

                    // A send that was in flight when the app closed never got its answer. Sending
                    // it again is safe: the key returns the original if it did arrive.
                    for (const item of outboxRef.current.filter((other) => other.state === 'sending')) {
                        void deliver(item);
                    }
                })
                .catch(() => {
                    if (!cancelled) {
                        retry = setTimeout(load, POLL_WHILE_WAITING_MS);
                    }
                });

        let retry: ReturnType<typeof setTimeout> | undefined;

        void load();

        return () => {
            cancelled = true;
            clearTimeout(retry);
            reveal.current?.dispose();
        };
        // Runs once: everything it uses is stable for the life of the page.
    }, [deliver, merge, reach]);

    const waiting = [...messages.current.values()].some(isWaiting);

    // Polling: every 2s while a reply is being written (or the server can't be reached, so the
    // strip clears promptly), every 10s otherwise, and never while the tab is hidden (push
    // covers that). This is also how another device's messages arrive.
    useEffect(() => {
        if (!ready) {
            return;
        }

        let timer: ReturnType<typeof setTimeout> | undefined;
        let stopped = false;
        let inFlight: Promise<void> | null = null;

        const poll = async () => {
            if (stopped || document.visibilityState !== 'visible') {
                return;
            }

            const known = [...messages.current.values()];
            const newest = known.reduce((max, message) => Math.max(max, message.id), 0);

            // Every reply still pending here is asked about, however old. The app may have
            // been asleep in the background for an hour while she finished it; only the
            // dots, not the checking, give up on an old one.
            const pending = known.filter((message) => message.author === 'donna' && message.status === 'pending');

            try {
                const [fresh, ...replies] = await reach(() =>
                    Promise.all([api.messages({ after: newest, limit: 100 }), ...pending.map((message) => api.message(message.id))]),
                );

                if (!stopped) {
                    merge([...replies, ...fresh.data]);
                    setFailedJobs(fresh.meta.failed_jobs ?? 0);
                    setCosts((current) => fresh.meta.costs ?? current);
                }
            } catch {
                // Offline or signed out; both are handled where they are detected.
            }

            if (!stopped) {
                timer = setTimeout(run, delay());
            }
        };

        const delay = () =>
            unreachableRef.current || [...messages.current.values()].some(isWaiting) ? POLL_WHILE_WAITING_MS : POLL_WHILE_IDLE_MS;

        // One poll at a time: a second chain would double every request from then on.
        const run = () => {
            inFlight ??= poll().finally(() => {
                inFlight = null;
            });
        };

        const onVisible = () => {
            if (document.visibilityState === 'visible') {
                clearTimeout(timer);
                run();

                return;
            }

            // Tell the server nobody is watching, so a reply that lands a moment from now is
            // pushed instead of being held back by the last poll. A poll still on its way
            // would mark him as watching again when it lands, so say it once more after it.
            const away = () => api.away().catch(() => undefined);

            void away();
            void inFlight?.finally(away);
        };

        timer = setTimeout(run, delay());
        document.addEventListener('visibilitychange', onVisible);

        return () => {
            stopped = true;
            clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisible);
        };
    }, [isWaiting, merge, reach, ready, waiting, unreachable]);

    const offline = !online || unreachable;

    // Back online: what was only waiting for a connection goes out by itself.
    useEffect(() => {
        if (offline || !ready) {
            return;
        }

        for (const item of outboxRef.current.filter((other) => other.state === 'failed' && other.waitingForConnection)) {
            void deliver(item);
        }
    }, [deliver, offline, ready]);

    const fromServer: TimelineItem[] = [...messages.current.values()]
        .sort((one, other) => one.id - other.id)
        .map((message) => ({
            kind: 'message',
            message,
            visibleBubbles: reveal.current?.visibleCount(message.id, message.bubbles.length),
        }));

    // Local things have no place in the server's order: they sit at the end, oldest first.
    const local: TimelineItem[] = [
        ...outbox.map((item): TimelineItem => ({ kind: 'outbox', item })),
        ...notes.map((note): TimelineItem => ({ kind: 'note', note })),
    ].sort((one, other) => localTime(one).localeCompare(localTime(other)));

    return {
        items: [...fromServer, ...local],
        typing: waiting || (reveal.current?.typing ?? false) || outbox.some((item) => item.state === 'sending'),
        offline,
        failedJobs,
        costs,
        ready,
        loadingOlder,
        hasOlder: nextBefore !== null,
        fileStates,
        send,
        retrySend,
        discardSend,
        retryReply,
        loadOlder,
        addNote,
        noteFile,
    };
}
