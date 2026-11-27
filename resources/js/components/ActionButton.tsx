import { useState } from 'react';
import type { AppAction } from '../api/types';
import type { PushResult } from '../lib/push';
import { storage } from '../lib/storage';

interface Props {
    /** Identifies this button on this reply, to remember that it was used. */
    id: string;
    action: AppAction;
    run: (type: AppAction['type']) => Promise<PushResult>;
}

/**
 * A button Donna put under a reply. She can only offer: the tap is what
 * does it, here on the device. Afterwards it reads "Done".
 */
export function ActionButton({ id, action, run }: Props) {
    const [state, setState] = useState<'idle' | 'running' | 'done'>(() => (storage.doneActions().includes(id) ? 'done' : 'idle'));
    const [note, setNote] = useState<string | null>(null);

    const onClick = async () => {
        setState('running');
        setNote(null);

        try {
            const result = await run(action.type);

            if (result.ok) {
                storage.saveDoneActions([...storage.doneActions(), id]);
            } else {
                setNote(result.message);
            }

            setState(result.ok ? 'done' : 'idle');
        } catch {
            setNote("That didn't work. Check your connection and try again.");
            setState('idle');
        }
    };

    return (
        <div className="action">
            <button type="button" className="button" disabled={state !== 'idle'} onClick={onClick}>
                {state === 'done' ? 'Done' : action.label}
            </button>
            {note !== null && (
                <p className="action__note" role="status">
                    {note}
                </p>
            )}
        </div>
    );
}
