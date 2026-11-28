import { useCallback, useMemo, useRef, useState } from 'react';
import type { Me, Source } from '../api/types';
import { Composer } from '../components/Composer';
import { AlertIcon, OfflineIcon } from '../components/icons';
import { SourcesSheet } from '../components/SourcesSheet';
import { Thread, type ThreadHandle } from '../components/Thread';
import { useAppActions } from '../hooks/useAppActions';
import { useKeyboardViewport } from '../hooks/useKeyboardViewport';
import { useMessages } from '../hooks/useMessages';
import { HELP, parseCommand } from '../lib/commands';
import { buildRows } from '../lib/timeline';

/**
 * The whole app: one conversation and an input. No header, no menu.
 */
export function ChatPage({ me }: { me: Me }) {
    const chat = useRef<HTMLDivElement>(null);
    const thread = useRef<ThreadHandle>(null);
    const conversation = useMessages();
    const { run, subscribed } = useAppActions();
    const [sheet, setSheet] = useState<Source[] | null>(null);

    useKeyboardViewport(chat);

    const { items, typing, addNote, send } = conversation;

    const rows = useMemo(() => buildRows(items, { timeZone: me.timezone, now: new Date(), typing }), [items, me.timezone, typing]);

    const onSend = useCallback(
        (text: string) => {
            thread.current?.scrollToBottom();

            const command = parseCommand(text);

            if (command === null) {
                send(text);

                return;
            }

            // Slash commands are answered here. They never reach Donna and cost nothing.
            switch (command.name) {
                case 'help':
                    addNote(HELP);
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

    return (
        <div className="chat" ref={chat} data-keyboard="closed">
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

            <Composer onSend={onSend} />

            {sheet !== null && <SourcesSheet sources={sheet} onClose={closeSheet} />}
        </div>
    );
}
