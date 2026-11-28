import { type ReactNode, useEffect, useRef, useState } from 'react';
import type { AppActionType } from '../api/types';
import type { PushResult } from '../lib/push';

const SIGN_OUT_FAILED = "I couldn't sign you out. Check your connection and try again.";
const NOTIFICATIONS_FAILED = "That didn't work. Check your connection and try again.";

interface Note {
    text: string;
    error: boolean;
}

interface SectionProps {
    run: (type: AppActionType) => Promise<PushResult>;
    subscribed: boolean | null;
    /** The drawer's one result line, shown under the notifications switch. */
    note: Note | null;
    say: (text: string, error: boolean) => void;
}

/**
 * Notifications for this device. The switch's tap runs the action straight
 * away: iOS only asks for permission from inside a tap.
 */
function NotificationsSection({ run, subscribed, note, say }: SectionProps) {
    const toggle = () => {
        run(subscribed ? 'disable_notifications' : 'enable_notifications')
            .then((result) => say(result.message, !result.ok))
            .catch(() => say(NOTIFICATIONS_FAILED, true));
    };

    return (
        <>
            <div className="setting">
                <span className="setting__label">
                    <span>Notifications</span>
                    <span className="setting__hint">On this device</span>
                </span>
                <button type="button" className="switch" role="switch" aria-checked={subscribed === true} aria-label="Notifications" onClick={toggle} />
            </div>
            {note !== null && (
                <p className="settings__note" data-error={note.error}>
                    {note.text}
                </p>
            )}
        </>
    );
}

/** Signing out succeeds by leaving: the app goes back to the sign-in screen. Only a failure is shown. */
function AccountSection({ run, say }: SectionProps) {
    const signOut = (type: 'sign_out' | 'sign_out_everywhere') => {
        run(type).catch(() => say(SIGN_OUT_FAILED, true));
    };

    return (
        <>
            <button type="button" className="button button--wide button--quiet" onClick={() => signOut('sign_out')}>
                Sign out
            </button>
            <button type="button" className="button button--wide button--quiet" onClick={() => signOut('sign_out_everywhere')}>
                Sign out everywhere
            </button>
        </>
    );
}

/** In the order the drawer shows them. A new setting is a new section here. */
const SECTIONS: { key: string; Section: (props: SectionProps) => ReactNode }[] = [
    { key: 'notifications', Section: NotificationsSection },
    { key: 'account', Section: AccountSection },
];

/**
 * The things that belong to this device rather than the conversation, opened
 * by /settings. They run the same actions as the buttons Donna offers.
 */
export function SettingsSheet({
    run,
    subscribed,
    onClose,
}: {
    run: (type: AppActionType) => Promise<PushResult>;
    subscribed: boolean | null;
    onClose: () => void;
}) {
    const done = useRef<HTMLButtonElement>(null);
    const [note, setNote] = useState<Note | null>(null);

    useEffect(() => {
        done.current?.focus();

        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [onClose]);

    const say = (text: string, error: boolean) => setNote({ text, error });

    return (
        <div className="sheet">
            <button type="button" className="sheet__backdrop" aria-label="Close" onClick={onClose} />
            <section className="sheet__panel" role="dialog" aria-modal="true" aria-label="Settings">
                <div className="sheet__grabber" aria-hidden="true" />
                <h2 className="sheet__title">Settings</h2>
                <div className="settings">
                    {SECTIONS.map(({ key, Section }) => (
                        <Section key={key} run={run} subscribed={subscribed} note={note} say={say} />
                    ))}
                </div>
                <button type="button" ref={done} className="button button--wide" onClick={onClose}>
                    Done
                </button>
            </section>
        </div>
    );
}
