import { type ChangeEvent, type KeyboardEvent, type SyntheticEvent, useCallback, useEffect, useLayoutEffect, useRef, useState } from 'react';
import { api, type Upload, UploadCancelled } from '../api/client';
import type { Costs } from '../api/types';
import {
    addPicked,
    type AttachedFile,
    blockedReason,
    canSend,
    type Chip,
    chipLine,
    progressed,
    readyFiles,
    refusalReason,
    refused,
    removed,
    restored,
    uploaded,
} from '../lib/attachments';
import { isTouchFirst, uuid } from '../lib/platform';
import { storage } from '../lib/storage';
import { composerHeight } from '../lib/viewport';
import { AttachSheet } from './AttachSheet';
import { AlertIcon, CloseIcon, FileIcon, PlusIcon, SendIcon } from './icons';

/** What iOS will hand over as a photo. Asking for these is what makes it convert HEIC to JPEG. */
const PHOTO_TYPES = 'image/jpeg,image/png,image/webp,image/gif';

/** Long enough for a tap's click to land before the field collapses under it. */
const COLLAPSE_DELAY_MS = 80;

/**
 * The input, pinned to the bottom. Empty and unfocused it is a slim bar;
 * tapped, it opens three lines tall and grows to six, then scrolls. Under the
 * text runs a bar: the plus that attaches a file at the start, what Donna has
 * cost in the middle, the send button at the end. Attached files sit above
 * the text as chips, uploading from the moment they are picked.
 *
 * There is only ever this one textarea. iOS opens the keyboard only when
 * focus lands inside the tap itself, so the bar you tap has to be the real
 * field, restyled, not a stand-in that swaps one in.
 */
export function Composer({ onSend, costs }: { onSend: (text: string, files: AttachedFile[]) => void; costs: Costs | null }) {
    const [text, setText] = useState(() => storage.draft());
    const [focused, setFocused] = useState(false);
    const [width, setWidth] = useState(0);
    const [choosing, setChoosing] = useState(false);
    const [overflow, setOverflow] = useState(false);
    const input = useRef<HTMLTextAreaElement>(null);
    const filePicker = useRef<HTMLInputElement>(null);
    const photoPicker = useRef<HTMLInputElement>(null);

    // The chips. The ref is the truth, so an upload that reports in between two renders
    // never works from a stale list; the state is what gets drawn.
    const [chips, drawChips] = useState<Chip[]>(() => restored(storage.attachments()));
    const held = useRef(chips);
    const uploads = useRef(new Map<string, Upload>());
    const mounted = useRef(true);

    const apply = useCallback((next: Chip[]) => {
        held.current = next;

        // Files that finished uploading are kept with the draft: iOS closes Home Screen apps without warning.
        storage.saveAttachments(readyFiles(next));

        if (mounted.current) {
            drawChips(next);
        }
    }, []);

    useEffect(() => {
        mounted.current = true;

        const inFlight = uploads.current;

        return () => {
            mounted.current = false;
            inFlight.forEach((upload) => upload.cancel());
            inFlight.clear();
        };
    }, []);

    const open = focused || text.length > 0 || chips.length > 0;
    const sendable = canSend(chips, text);
    const waiting = blockedReason(chips);

    // The draft is saved as it is typed: iOS closes Home Screen apps without warning.
    useEffect(() => storage.saveDraft(text), [text]);

    /** Take what a picker handed over: a chip for each, uploading at once. */
    const pick = (event: ChangeEvent<HTMLInputElement>) => {
        const files = Array.from(event.target.files ?? []);

        // So choosing the same file again still counts as a change.
        event.target.value = '';

        if (files.length === 0) {
            return;
        }

        const picked = addPicked(held.current, files, uuid);

        apply(picked.chips);
        setOverflow(picked.overflow);

        picked.uploads.forEach((key, index) => {
            if (key === null) {
                return;
            }

            const upload = api.uploadFile(files[index]!, (fraction) => apply(progressed(held.current, key, fraction)));

            uploads.current.set(key, upload);

            upload.done
                .then((file) => apply(uploaded(held.current, key, file)))
                .catch((error) => {
                    // Removing a chip cancels its upload; there is nothing left to mark.
                    if (!(error instanceof UploadCancelled)) {
                        apply(refused(held.current, key, refusalReason(error)));
                    }
                })
                .finally(() => uploads.current.delete(key));
        });
    };

    const remove = (key: string) => {
        uploads.current.get(key)?.cancel();
        uploads.current.delete(key);
        apply(removed(held.current, key));
        setOverflow(false);
    };

    const attach = useRef<HTMLButtonElement>(null);

    // Closing the sheet without choosing puts focus back on the button that opened it.
    const closeSheet = useCallback(() => {
        setChoosing(false);
        attach.current?.focus();
    }, []);

    // The picker has to be opened inside the tap, so this runs before the sheet goes away.
    const choose = (picker: HTMLInputElement | null) => {
        picker?.click();
        setChoosing(false);
    };

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
        if (!canSend(held.current, text)) {
            return;
        }

        onSend(text.trim(), readyFiles(held.current));
        setText('');
        apply([]);
        setOverflow(false);

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

    // Pressing send must not take focus from the field, or the keyboard would drop.
    const keepFocus = (event: SyntheticEvent) => event.preventDefault();

    return (
        <div className="composer">
            <div className="composer__field" data-open={open}>
                {chips.length > 0 && (
                    <ul className="chips" aria-label="Attachments">
                        {chips.map((chip) => (
                            <li key={chip.key} className="chip" data-state={chip.state}>
                                <span className="chip__icon">{chip.state === 'error' ? <AlertIcon size={18} /> : <FileIcon size={18} />}</span>
                                <span className="chip__text">
                                    <span className="chip__name">{chip.name}</span>
                                    <span className="chip__line">{chipLine(chip)}</span>
                                </span>
                                <button
                                    type="button"
                                    className="chip__remove"
                                    aria-label={chip.state === 'uploading' ? `Cancel upload of ${chip.name}` : `Remove ${chip.name}`}
                                    onPointerDown={keepFocus}
                                    onMouseDown={keepFocus}
                                    onClick={() => remove(chip.key)}
                                >
                                    <CloseIcon size={16} />
                                </button>
                                {chip.state === 'uploading' && <span className="chip__progress" aria-hidden="true" style={{ width: `${Math.round(chip.progress * 100)}%` }} />}
                            </li>
                        ))}
                    </ul>
                )}
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
                        ref={attach}
                        type="button"
                        className="composer__attach"
                        aria-label="Attach a file"
                        aria-haspopup="dialog"
                        onPointerDown={keepFocus}
                        onMouseDown={keepFocus}
                        onClick={() => setChoosing(true)}
                    >
                        <PlusIcon size={16} />
                    </button>
                    {costs && (
                        <p className="composer__costs">
                            Today ${costs.today} · Cycle ${costs.cycle}
                        </p>
                    )}
                    <button type="button" className="composer__send" aria-label="Send" disabled={!sendable} onPointerDown={keepFocus} onMouseDown={keepFocus} onClick={submit}>
                        <SendIcon size={16} />
                    </button>
                </div>
            </div>

            {(overflow || waiting !== null) && (
                <p className="composer__note" role="status">
                    {[overflow ? 'Five files at most per message.' : null, waiting].filter((line) => line !== null).join(' ')}
                </p>
            )}

            {/* Always in the page: a picker can only be opened from inside a tap, on an input that already exists.
                The file picker takes anything, and the server decides what it can read. */}
            <input ref={filePicker} type="file" multiple className="visually-hidden" tabIndex={-1} aria-hidden="true" data-picker="file" onChange={pick} />
            <input ref={photoPicker} type="file" multiple accept={PHOTO_TYPES} className="visually-hidden" tabIndex={-1} aria-hidden="true" data-picker="photo" onChange={pick} />

            {choosing && <AttachSheet onChooseFile={() => choose(filePicker.current)} onChoosePhoto={() => choose(photoPicker.current)} onClose={closeSheet} />}
        </div>
    );
}
