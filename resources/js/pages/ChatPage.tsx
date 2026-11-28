import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { api } from '../api/client';
import type { Me, Source } from '../api/types';
import { Composer } from '../components/Composer';
import { FileViewer } from '../components/FileViewer';
import { AlertIcon, OfflineIcon } from '../components/icons';
import { SourcesSheet } from '../components/SourcesSheet';
import { Thread, type ThreadHandle } from '../components/Thread';
import { useAppActions } from '../hooks/useAppActions';
import { useKeyboardViewport } from '../hooks/useKeyboardViewport';
import { useMessages } from '../hooks/useMessages';
import type { AttachedFile } from '../lib/attachments';
import { HELP, parseCommand } from '../lib/commands';
import { buildRows } from '../lib/timeline';

/** Wide enough for the file to sit beside the chat instead of over it. The same line the stylesheet draws. */
const WIDE = '(min-width: 768px)';

function useWide(): boolean {
    const [wide, setWide] = useState(() => window.matchMedia(WIDE).matches);

    useEffect(() => {
        const query = window.matchMedia(WIDE);
        const onChange = () => setWide(query.matches);

        query.addEventListener('change', onChange);

        return () => query.removeEventListener('change', onChange);
    }, []);

    return wide;
}

/**
 * The whole app: one conversation and an input, and beside them (or over
 * them, on a phone) whichever file is open. No header, no menu.
 */
export function ChatPage({ me }: { me: Me }) {
    const shell = useRef<HTMLDivElement>(null);
    const thread = useRef<ThreadHandle>(null);
    const conversation = useMessages();
    const { run, subscribed } = useAppActions();
    const [sheet, setSheet] = useState<Source[] | null>(null);
    const [openFile, setOpenFile] = useState<number | null>(null);
    const wide = useWide();

    // What was pressed to open the file, so closing it puts the keyboard's place back there.
    const opener = useRef<HTMLElement | null>(null);

    // The shell is what rides above the keyboard: the chat's input, and the file's editor too.
    useKeyboardViewport(shell);

    const { items, typing, fileStates, addNote, send, noteFile } = conversation;

    const rows = useMemo(
        () => buildRows(items, { timeZone: me.timezone, now: new Date(), typing, fileStates }),
        [items, me.timezone, typing, fileStates],
    );

    const onSend = useCallback(
        (text: string, files: AttachedFile[] = []) => {
            thread.current?.scrollToBottom();

            // A message with files is always for Donna, whatever its text starts with.
            const command = files.length > 0 ? null : parseCommand(text);

            if (command === null) {
                send(text, files);

                return;
            }

            // Slash commands are answered here. They never reach Donna and cost nothing.
            switch (command.name) {
                case 'help':
                    addNote(HELP);
                    break;

                case 'files':
                    api.files()
                        .then((list) => (list.length > 0 ? addNote('', list) : addNote('No files yet.')))
                        .catch(() => addNote("I couldn't get your files. Check your connection and try again."));
                    break;

                case 'notifications':
                    run(subscribed ? 'disable_notifications' : 'enable_notifications')
                        .then((result) => addNote(result.message))
                        .catch(() => addNote("That didn't work. Check your connection and try again."));
                    break;

                case 'signout':
                    run(command.everywhere ? 'sign_out_everywhere' : 'sign_out').catch(() =>
                        addNote("I couldn't sign you out. Check your connection and try again."),
                    );
                    break;
            }
        },
        [addNote, run, send, subscribed],
    );

    const closeSheet = useCallback(() => setSheet(null), []);

    const openViewer = useCallback((id: number) => {
        const pressed = document.activeElement;

        // Only a button goes back into focus afterwards: focusing the message field would raise the keyboard.
        opener.current = pressed instanceof HTMLButtonElement ? pressed : null;

        setSheet(null);
        setOpenFile(id);
    }, []);

    const closeViewer = useCallback(() => {
        setOpenFile(null);

        if (opener.current?.isConnected) {
            opener.current.focus({ preventScroll: true });
        }

        opener.current = null;
    }, []);

    return (
        <div className="shell" ref={shell} data-keyboard="closed" data-file={openFile !== null}>
            {/* On a phone the file covers the chat, so the chat is out of reach of the keyboard and screen readers until it closes. */}
            <div className="pane" inert={openFile !== null && !wide}>
                <div className="chat">
                    <div className="chat__status-bar" />

                    <Thread
                        ref={thread}
                        rows={rows}
                        timeZone={me.timezone}
                        hasOlder={conversation.hasOlder}
                        loadingOlder={conversation.loadingOlder}
                        onLoadOlder={conversation.loadOlder}
                        onRetrySend={conversation.retrySend}
                        onRetryReply={conversation.retryReply}
                        onShowSources={setSheet}
                        onOpenFile={openViewer}
                        runAction={run}
                    />

                    {conversation.offline && (
                        <div className="offline" role="status">
                            <span className="offline__icon">
                                <OfflineIcon size={18} />
                            </span>
                            <span>You&rsquo;re offline. Messages will send when you&rsquo;re back.</span>
                        </div>
                    )}

                    {conversation.failedJobs > 0 && (
                        <div className="offline" role="status">
                            <span className="offline__icon">
                                <AlertIcon size={18} />
                            </span>
                            <span>Something failed behind the scenes. She may not be remembering everything until it&rsquo;s fixed.</span>
                        </div>
                    )}

                    <Composer onSend={onSend} costs={conversation.costs} />

                    {sheet !== null && <SourcesSheet sources={sheet} onClose={closeSheet} onOpenFile={openViewer} />}
                </div>
            </div>

            {/* Keyed by file, so opening another one starts clean instead of showing the last one's text for a moment. */}
            {openFile !== null && <FileViewer key={openFile} id={openFile} busy={typing} timeZone={me.timezone} onClose={closeViewer} onFile={noteFile} />}
        </div>
    );
}
