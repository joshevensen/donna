import { useCallback, useMemo, useRef, useState } from 'react';
import { api } from '../api/client';
import type { Me, Source } from '../api/types';
import { Composer } from '../components/Composer';
import { AlertIcon, OfflineIcon } from '../components/icons';
import { SettingsSheet } from '../components/SettingsSheet';
import { SourcesSheet } from '../components/SourcesSheet';
import { Thread, type ThreadHandle } from '../components/Thread';
import { useAppActions } from '../hooks/useAppActions';
import { useKeyboardViewport } from '../hooks/useKeyboardViewport';
import { useMessages } from '../hooks/useMessages';
import { ANSWER_FAILED, formatCosts, formatPeople, formatProjects } from '../lib/answers';
import { type CommandName, parseCommand } from '../lib/commands';
import { buildRows } from '../lib/timeline';

/** The commands that answer, each from one read-only endpoint, formatted here. */
const ANSWERS: Record<Exclude<CommandName, 'settings'>, () => Promise<string>> = {
    cost: () => api.costs().then(formatCosts),
    people: () => api.people().then(formatPeople),
    projects: () => api.projects().then(formatProjects),
};

/**
 * The whole app: one conversation and an input. No header, no menu.
 */
export function ChatPage({ me }: { me: Me }) {
    const chat = useRef<HTMLDivElement>(null);
    const thread = useRef<ThreadHandle>(null);
    const conversation = useMessages();
    const { run, subscribed } = useAppActions();
    const [sheet, setSheet] = useState<Source[] | null>(null);
    const [settings, setSettings] = useState(false);

    useKeyboardViewport(chat);

    const { items, typing, addNote, send } = conversation;

    const rows = useMemo(() => buildRows(items, { timeZone: me.timezone, now: new Date(), typing }), [items, me.timezone, typing]);

    const onSend = useCallback(
        (text: string) => {
            const command = parseCommand(text);

            // Slash commands are answered here. They never reach Donna and cost nothing.
            if (command === 'settings') {
                setSettings(true);

                return;
            }

            thread.current?.scrollToBottom();

            if (command === null) {
                send(text);

                return;
            }

            // The answer is a note on this device only: gone on reload, never sent.
            ANSWERS[command]()
                .then(addNote)
                .catch(() => addNote(ANSWER_FAILED));
        },
        [addNote, send],
    );

    const closeSheet = useCallback(() => setSheet(null), []);
    const closeSettings = useCallback(() => setSettings(false), []);

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

            <Composer onSend={onSend} costs={conversation.costs} />

            {sheet !== null && <SourcesSheet sources={sheet} onClose={closeSheet} />}

            {settings && <SettingsSheet run={run} subscribed={subscribed} onClose={closeSettings} />}
        </div>
    );
}
