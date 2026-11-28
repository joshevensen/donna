import { useEffect, useRef } from 'react';
import type { Source } from '../api/types';

const KINDS: Record<string, string> = {
    web: 'Web',
    fact: 'Fact',
    message: 'Message',
    person: 'Person',
    topic: 'Topic',
    file: 'File',
};

/**
 * What a reply's context held: the pages she cited, then the memory she was
 * given. "Looked at", not "used": being shown something isn't proof she
 * relied on it.
 */
export function SourcesSheet({ sources, onClose, onOpenFile }: { sources: Source[]; onClose: () => void; onOpenFile: (id: number) => void }) {
    const done = useRef<HTMLButtonElement>(null);

    useEffect(() => {
        done.current?.focus();

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
            <section className="sheet__panel" role="dialog" aria-modal="true" aria-label="What Donna looked at">
                <div className="sheet__grabber" aria-hidden="true" />
                <h2 className="sheet__title">What I looked at</h2>
                <ul className="sheet__list">
                    {sources.map((source, index) => (
                        <li key={index} className="source">
                            <span className="source__kind">{KINDS[source.type] ?? source.type}</span>
                            {source.url !== undefined ? (
                                <a href={source.url} target="_blank" rel="noopener noreferrer">
                                    {source.label}
                                </a>
                            ) : source.file_id !== undefined ? (
                                // A part of a file she was given: tapping it opens the file.
                                <button type="button" className="source__open" onClick={() => onOpenFile(source.file_id!)}>
                                    {source.label}
                                </button>
                            ) : (
                                <span>{source.label}</span>
                            )}
                        </li>
                    ))}
                </ul>
                <button type="button" ref={done} className="button button--wide" onClick={onClose}>
                    Done
                </button>
            </section>
        </div>
    );
}
