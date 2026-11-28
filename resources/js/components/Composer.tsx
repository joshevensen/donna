import { type KeyboardEvent, type SyntheticEvent, useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import type { Costs } from '../api/types';
import type { CommandInfo } from '../lib/commands';
import { isTouchFirst } from '../lib/platform';
import { storage } from '../lib/storage';
import { composerHeight } from '../lib/viewport';
import { CommandPicker } from './CommandPicker';
import { SendIcon } from './icons';

/** Long enough for a tap's click to land before the field collapses under it. */
const COLLAPSE_DELAY_MS = 80;

/**
 * The input, pinned to the bottom. Empty and unfocused it is a slim bar;
 * tapped, it opens three lines tall and grows to six, then scrolls. Under the
 * text runs a bar: the `/` button that lists the commands at the start, what
 * Donna has cost in the middle, the send button at the end.
 *
 * Picking a command puts it in the field in place of whatever was there and
 * stops: it isn't sent, so Josh decides what happens next.
 *
 * There is only ever this one textarea. iOS opens the keyboard only when
 * focus lands inside the tap itself, so the bar you tap has to be the real
 * field, restyled, not a stand-in that swaps one in.
 */
export function Composer({ onSend, costs }: { onSend: (text: string) => void; costs: Costs | null }) {
    const [text, setText] = useState(() => storage.draft());
    const [focused, setFocused] = useState(false);
    const [width, setWidth] = useState(0);
    const [picking, setPicking] = useState(false);
    const input = useRef<HTMLTextAreaElement>(null);
    const box = useRef<HTMLDivElement>(null);
    const commands = useRef<HTMLButtonElement>(null);

    const open = focused || text.length > 0;
    const empty = text.trim() === '';

    // The draft is saved as it is typed: iOS closes Home Screen apps without warning.
    useEffect(() => storage.saveDraft(text), [text]);

    // Leaving the field collapses it, which moves everything above it. If that happened in the
    // middle of a press, the thing being pressed would slide out from under the pointer and the
    // press would miss. So the collapse waits until the press is over.
    const pressing = useRef(false);
    const collapse = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => {
        const down = () => {
            pressing.current = true;
        };

        const up = () => {
            pressing.current = false;
        };

        window.addEventListener('pointerdown', down, true);
        window.addEventListener('pointerup', up, true);
        window.addEventListener('pointercancel', up, true);

        return () => {
            window.removeEventListener('pointerdown', down, true);
            window.removeEventListener('pointerup', up, true);
            window.removeEventListener('pointercancel', up, true);
            clearTimeout(collapse.current);
        };
    }, []);

    const onFocus = () => {
        clearTimeout(collapse.current);
        setFocused(true);
    };

    const onBlur = () => {
        const settle = () => {
            if (pressing.current) {
                collapse.current = setTimeout(settle, COLLAPSE_DELAY_MS);
            } else if (document.activeElement !== input.current) {
                setFocused(false);
            }
        };

        clearTimeout(collapse.current);
        collapse.current = setTimeout(settle, COLLAPSE_DELAY_MS);
    };

    // Text wraps differently at a new width, so measure again when it changes.
    useEffect(() => {
        const onResize = () => setWidth(window.innerWidth);

        window.addEventListener('resize', onResize);

        return () => window.removeEventListener('resize', onResize);
    }, []);

    // Size the field from the text's real height, not from a count of characters.
    useLayoutEffect(() => {
        const element = input.current;

        if (!element) {
            return;
        }

        if (!open) {
            element.style.height = '';
            element.style.overflowY = '';

            return;
        }

        const style = getComputedStyle(element);
        const lineHeight = Number.parseFloat(style.lineHeight) || 22;
        const padding = (Number.parseFloat(style.paddingTop) || 0) + (Number.parseFloat(style.paddingBottom) || 0);

        element.style.height = 'auto';

        const { height, scrolls } = composerHeight(element.scrollHeight, { lineHeight, padding });

        element.style.height = `${height}px`;
        element.style.overflowY = scrolls ? 'auto' : 'hidden';
    }, [open, text, width]);

    const submit = () => {
        if (empty) {
            return;
        }

        onSend(text.trim());
        setText('');

        // Still inside the tap, so the keyboard stays up for the next message.
        input.current?.focus();
    };

    const onKeyDown = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        // With a keyboard and mouse, Enter sends and Shift+Enter is a new line. On a phone
        // Enter is always a new line: the send button sends.
        if (event.key === 'Enter' && !event.shiftKey && !event.nativeEvent.isComposing && !isTouchFirst()) {
            event.preventDefault();
            submit();
        }
    };

    // Pressing send or `/` must not take focus from the field, or the keyboard would drop.
    const keepFocus = (event: SyntheticEvent) => event.preventDefault();

    const closePicker = useCallback(() => setPicking(false), []);

    const pick = (command: CommandInfo) => {
        setText(command.text);
        setPicking(false);

        // Inside the tap, so iOS opens the keyboard if it wasn't already up.
        input.current?.focus();
    };

    return (
        <div className="composer" ref={box}>
            {picking && <CommandPicker composer={box} toggle={commands} onPick={pick} onClose={closePicker} />}
            <div className="composer__field" data-open={open}>
                <label htmlFor="message" className="visually-hidden">
                    Message Donna
                </label>
                <textarea
                    id="message"
                    ref={input}
                    className="composer__input"
                    rows={1}
                    value={text}
                    placeholder="Message Donna"
                    enterKeyHint="enter"
                    onChange={(event) => setText(event.target.value)}
                    onFocus={onFocus}
                    onBlur={onBlur}
                    onKeyDown={onKeyDown}
                />
                <div className="composer__bar">
                    <button
                        type="button"
                        ref={commands}
                        className="composer__commands"
                        aria-label="Commands"
                        aria-expanded={picking}
                        onPointerDown={keepFocus}
                        onMouseDown={keepFocus}
                        onClick={() => setPicking((current) => !current)}
                    >
                        /
                    </button>
                    {costs && (
                        <p className="composer__costs">
                            Today ${costs.today} · Cycle ${costs.cycle}
                        </p>
                    )}
                    <button type="button" className="composer__send" aria-label="Send" disabled={empty} onPointerDown={keepFocus} onMouseDown={keepFocus} onClick={submit}>
                        <SendIcon size={16} />
                    </button>
                </div>
            </div>
        </div>
    );
}
