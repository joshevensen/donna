import { type RefObject, type SyntheticEvent, useCallback, useEffect, useLayoutEffect, useRef } from 'react';
import { COMMANDS, type CommandInfo } from '../lib/commands';
import { pickerMaxHeight } from '../lib/viewport';

/**
 * Every command, opened from the `/` button and shown just above the input.
 * Always the whole list, alphabetical, whatever has been typed: it is how
 * the commands are found, not autocomplete.
 *
 * It is only as tall as the space between the top of what's visible and the
 * input, and scrolls inside itself past that. On an iPhone with the keyboard
 * up that space is the visual viewport, not the window, so it is measured
 * again whenever the visual viewport moves or changes size.
 *
 * Pressing a command doesn't take focus from the field, so an open keyboard
 * stays up while the command goes in.
 */
export function CommandPicker({
    composer,
    toggle,
    onPick,
    onClose,
}: {
    /** The input's box: the list may grow up to its top. */
    composer: RefObject<HTMLElement | null>;
    /** The `/` button. Pressing it toggles the list, so it doesn't count as outside. */
    toggle: RefObject<HTMLElement | null>;
    onPick: (command: CommandInfo) => void;
    onClose: () => void;
}) {
    const list = useRef<HTMLUListElement>(null);

    const fit = useCallback(() => {
        const element = list.current;
        const box = composer.current;

        if (!element || !box) {
            return;
        }

        element.style.maxHeight = `${pickerMaxHeight(box.getBoundingClientRect().top, window.visualViewport?.offsetTop ?? 0)}px`;
    }, [composer]);

    // Every render: the input grows as it is typed in, which moves its top.
    useLayoutEffect(fit);

    useEffect(() => {
        const viewport = window.visualViewport;

        viewport?.addEventListener('resize', fit);
        viewport?.addEventListener('scroll', fit);

        return () => {
            viewport?.removeEventListener('resize', fit);
            viewport?.removeEventListener('scroll', fit);
        };
    }, [fit]);

    // Escape, or a press anywhere but the list and the `/` button, closes it.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        const onPointerDown = (event: PointerEvent) => {
            const target = event.target as Node | null;

            if (target && (list.current?.contains(target) || toggle.current?.contains(target))) {
                return;
            }

            onClose();
        };

        document.addEventListener('keydown', onKey);
        document.addEventListener('pointerdown', onPointerDown);

        return () => {
            document.removeEventListener('keydown', onKey);
            document.removeEventListener('pointerdown', onPointerDown);
        };
    }, [onClose, toggle]);

    const keepFocus = (event: SyntheticEvent) => event.preventDefault();

    return (
        <ul ref={list} className="picker" role="listbox" aria-label="Commands">
            {COMMANDS.map((command) => (
                <li key={command.name}>
                    <button type="button" className="picker__item" onPointerDown={keepFocus} onMouseDown={keepFocus} onClick={() => onPick(command)}>
                        <span className="picker__name">{command.text}</span>
                        <span className="picker__description">{command.description}</span>
                    </button>
                </li>
            ))}
        </ul>
    );
}
