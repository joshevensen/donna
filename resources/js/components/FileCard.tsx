import { fileContentUrl } from '../api/client';
import { type CardFile, cardLine } from '../lib/files';
import { ChevronIcon, FileIcon, PhotoIcon } from './icons';

interface Props {
    file: CardFile;
    /**
     * Where the card sits: inside one of Josh's bubbles or the answer to
     * /files (`inset`), or by itself under one of Donna's replies (`raised`).
     */
    surface: 'inset' | 'raised';
    /** The second line, when it isn't the usual one (a list shows when the file arrived). */
    line?: string;
    /** Show the picture itself above the name: an image Josh sent. */
    thumbnail?: boolean;
    onOpen: (id: number) => void;
}

/**
 * A file, wherever it is mentioned: icon, name, one line about it, and a
 * Kept badge when Donna is keeping it. Tapping it opens the viewer. A
 * deleted file's card stays, says so, and opens nothing.
 */
export function FileCard({ file, surface, line, thumbnail = false, onOpen }: Props) {
    const second = file.deleted ? cardLine(file) : (line ?? cardLine(file));

    // Holding the picture's shape before it loads keeps the thread from jumping when it does.
    const shape = file.meta.width && file.meta.height ? { aspectRatio: `${file.meta.width} / ${file.meta.height}` } : undefined;

    const content = (
        <>
            {thumbnail && file.kind === 'image' && !file.deleted && (
                <img className="file-card__thumbnail" src={fileContentUrl(file.id, 'preview')} alt="" loading="lazy" decoding="async" style={shape} />
            )}
            <span className="file-card__row">
                <span className="file-card__icon">{file.kind === 'image' ? <PhotoIcon size={22} /> : <FileIcon size={22} />}</span>
                <span className="file-card__text">
                    <span className="file-card__name">{file.name}</span>
                    <span className="file-card__line">{second}</span>
                </span>
                {file.kept && !file.deleted && <span className="file-card__kept">Kept</span>}
                {surface === 'raised' && !file.deleted && (
                    <span className="file-card__chevron">
                        <ChevronIcon direction="right" size={16} />
                    </span>
                )}
            </span>
        </>
    );

    if (file.deleted) {
        return (
            <div className="file-card" data-surface={surface} data-deleted="true">
                {content}
            </div>
        );
    }

    return (
        <button
            type="button"
            className="file-card"
            data-surface={surface}
            aria-label={`Open ${file.name}${file.kept ? ', kept' : ''}${file.role === 'edited' ? ', edited' : ''}`}
            onClick={() => onOpen(file.id)}
        >
            {content}
        </button>
    );
}
