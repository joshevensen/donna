import { useEffect, useRef } from 'react';
import { FileIcon, PhotoIcon } from './icons';

interface Props {
    onChooseFile: () => void;
    onChoosePhoto: () => void;
    onClose: () => void;
}

/**
 * What the plus opens: a file or a photo. Each button opens its picker
 * inside the tap itself, which is the only time a browser allows it.
 */
export function AttachSheet({ onChooseFile, onChoosePhoto, onClose }: Props) {
    const first = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        first.current?.focus();

        // Heard first and marked handled, so Escape closes this sheet and not a file open behind it.
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                event.preventDefault();
                onClose();
            }
        };

        document.addEventListener('keydown', onKey, true);

        return () => document.removeEventListener('keydown', onKey, true);
    }, [onClose]);

    return (
        <div className="sheet">
            <button type="button" className="sheet__backdrop" aria-label="Close" onClick={onClose} />
            <section className="sheet__panel sheet__panel--options" role="dialog" aria-modal="true" aria-label="Attach">
                <div className="sheet__grabber" aria-hidden="true" />
                <button type="button" ref={first} className="sheet__option" onClick={onChooseFile}>
                    <FileIcon size={22} />
                    <span className="sheet__option-text">
                        <span>Choose a file</span>
                        <span className="sheet__option-note">Text, CSV, PDF or an image. 10 MB each, five at a time</span>
                    </span>
                </button>
                <button type="button" className="sheet__option" onClick={onChoosePhoto}>
                    <PhotoIcon size={22} />
                    <span className="sheet__option-text">
                        <span>Choose a photo</span>
                    </span>
                </button>
                <button type="button" className="sheet__cancel" onClick={onClose}>
                    Cancel
                </button>
            </section>
        </div>
    );
}
