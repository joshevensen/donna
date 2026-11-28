import { useCallback, useEffect, useId, useMemo, useReducer, useRef, useState } from 'react';
import { api, ApiError, fileContentUrl, NetworkError } from '../api/client';
import type { FileDetail, FileTable } from '../api/types';
import { conflictWords, editReducer, isDirty, READING } from '../lib/editing';
import { formatCount, momentLabel, numericColumns, sizeDetail, splitPages } from '../lib/files';
import { isTouchFirst } from '../lib/platform';
import { storage } from '../lib/storage';
import { AlertIcon, ChevronIcon, CloseIcon, ExternalIcon } from './icons';
import { Markdown } from './Markdown';

/** How often the open file is asked for again while Donna is writing a reply that may be changing it. */
const REFRESH_MS = 2_000;

interface Props {
    id: number;
    /** Donna is writing: she may be changing this file right now. */
    busy: boolean;
    timeZone: string;
    onClose: () => void;
    /** The file as the server just gave it, so its cards in the thread agree with what is shown here. */
    onFile: (file: FileDetail) => void;
}

/**
 * One file: a sheet over the chat on a phone, a panel beside it on a wider
 * screen. Reading is rendered on Panel; editing (markdown only) is monospace
 * on Inset, so it is always plain which of the two this is.
 */
export function FileViewer({ id, busy, timeZone, onClose, onFile }: Props) {
    const [file, setFile] = useState<FileDetail | null>(null);
    const [problem, setProblem] = useState<'missing' | 'unreachable' | null>(null);
    const [edit, dispatch] = useReducer(editReducer, READING);
    const [keepError, setKeepError] = useState<string | null>(null);
    const [details, setDetails] = useState(false);
    const panel = useRef<HTMLElement>(null);
    const alive = useRef(true);
    const keepLabel = useId();
    const editorLabel = useId();
    const detailsId = useId();

    const reading = edit.mode === 'reading';

    const show = useCallback(
        (fresh: FileDetail) => {
            setFile(fresh);
            setProblem(null);
            onFile(fresh);
        },
        [onFile],
    );

    /** Ask for the file. A quiet load is a refresh: if it fails, what is on screen stays. */
    const load = useCallback(
        async (quiet: boolean) => {
            try {
                const fresh = await api.file(id);

                if (alive.current) {
                    show(fresh);
                }
            } catch (error) {
                if (!alive.current) {
                    return;
                }

                if (error instanceof ApiError && error.status === 404) {
                    setProblem('missing');
                } else if (!quiet) {
                    setProblem('unreachable');
                }
            }
        },
        [id, show],
    );

    useEffect(() => {
        alive.current = true;
        panel.current?.focus({ preventScroll: true });
        void load(false);

        return () => {
            alive.current = false;
        };
    }, [load]);

    // While Donna is writing she may be editing this very file: keep it current, and look once
    // more when she finishes. Never while Josh is editing: his text is not to be touched.
    const wasBusy = useRef(false);

    useEffect(() => {
        if (!reading || problem === 'missing') {
            return;
        }

        if (!busy) {
            if (wasBusy.current) {
                wasBusy.current = false;
                void load(true);
            }

            return;
        }

        wasBusy.current = true;

        const timer = setInterval(() => void load(true), REFRESH_MS);

        return () => clearInterval(timer);
    }, [busy, load, problem, reading]);

    // Escape closes a file being read. One being edited needs Cancel, so nothing is lost by a stray key.
    useEffect(() => {
        const onKey = (event: KeyboardEvent) => {
            if (event.key === 'Escape' && reading && !event.defaultPrevented) {
                onClose();
            }
        };

        document.addEventListener('keydown', onKey);

        return () => document.removeEventListener('keydown', onKey);
    }, [onClose, reading]);

    // Unsaved text is kept on the device as it is typed: iOS closes Home Screen apps without warning.
    useEffect(() => {
        if (edit.mode === 'editing') {
            storage.saveEditDraft(id, isDirty(edit) ? { body: edit.text, baseVersion: edit.baseVersion } : null);
        }
    }, [edit, id]);

    const toggleKeep = () => {
        if (file === null) {
            return;
        }

        const before = file;
        const kept = !file.kept;

        // Flip at once; put it back if the server didn't take it.
        setFile({ ...file, kept });
        setKeepError(null);

        api.updateFile(id, { kept })
            .then((fresh) => alive.current && show(fresh))
            .catch(() => {
                if (alive.current) {
                    setFile((current) => (current === null ? current : { ...current, kept: before.kept }));
                    setKeepError("That didn't save. Try again.");
                }
            });
    };

    const save = async (text: string, baseVersion: number) => {
        dispatch({ type: 'saving' });

        try {
            const saved = await api.saveFile(id, text, baseVersion);

            storage.saveEditDraft(id, null);

            if (alive.current) {
                show(saved);
                dispatch({ type: 'saved' });
            }
        } catch (error) {
            if (!alive.current) {
                return;
            }

            // Someone changed the file since this text was started: the answer carries the file as it now is.
            if (error instanceof ApiError && error.status === 409 && isFile(error.data)) {
                dispatch({ type: 'conflicted', current: error.data });
            } else {
                dispatch({ type: 'failed', message: saveFailure(error) });
            }
        }
    };

    const startEditing = () => {
        if (file !== null) {
            dispatch({ type: 'start', file, draft: storage.editDraft(id) });
        }
    };

    const cancel = () => {
        if (edit.mode !== 'editing') {
            return;
        }

        // The first press with unsaved changes only asks; the second, or one with nothing to lose, leaves.
        if (!isDirty(edit) || edit.confirmingDiscard) {
            storage.saveEditDraft(id, null);
        }

        dispatch({ type: 'cancel' });
    };

    const keepMine = () => {
        if (edit.mode === 'editing' && edit.conflict !== null) {
            const base = edit.conflict.version ?? edit.baseVersion;

            dispatch({ type: 'keep-mine' });
            void save(edit.text, base);
        }
    };

    const takeTheirs = () => {
        if (edit.mode === 'editing' && edit.conflict !== null) {
            storage.saveEditDraft(id, null);
            show(edit.conflict);
            dispatch({ type: 'take-theirs' });
        }
    };

    if (edit.mode === 'editing' && file !== null) {
        const conflict = edit.conflict === null ? null : conflictWords(edit.conflict);

        return (
            <section className="viewer" data-mode="edit" ref={panel} tabIndex={-1} aria-label={`Editing ${file.name}`}>
                <div className="viewer__status-bar" />

                <header className="viewer__header">
                    <div className="viewer__bar viewer__bar--edit">
                        <button type="button" className="viewer__cancel" onClick={cancel}>
                            Cancel
                        </button>
                        <div className="viewer__title">
                            <h2 className="viewer__name">{file.name}</h2>
                            <span className="viewer__mode">Editing</span>
                        </div>
                        <button
                            type="button"
                            className="button viewer__action"
                            disabled={edit.saving || edit.conflict !== null || !isDirty(edit)}
                            onClick={() => void save(edit.text, edit.baseVersion)}
                        >
                            {edit.saving ? 'Saving…' : 'Save'}
                        </button>
                    </div>
                </header>

                {conflict !== null && (
                    <div className="viewer__banner" role="alert">
                        <div className="viewer__banner-text">
                            <AlertIcon size={18} />
                            <span>{conflict.message}</span>
                        </div>
                        <div className="viewer__banner-actions">
                            <button type="button" className="button viewer__banner-button" disabled={edit.saving} onClick={keepMine}>
                                Keep mine
                            </button>
                            <button type="button" className="button button--muted viewer__banner-button" disabled={edit.saving} onClick={takeTheirs}>
                                {conflict.take}
                            </button>
                        </div>
                    </div>
                )}

                {edit.confirmingDiscard && (
                    <div className="viewer__banner" role="alert">
                        <div className="viewer__banner-text">
                            <AlertIcon size={18} />
                            <span>Discard your changes?</span>
                        </div>
                        <div className="viewer__banner-actions">
                            <button type="button" className="button viewer__banner-button" onClick={cancel}>
                                Discard
                            </button>
                            <button type="button" className="button button--muted viewer__banner-button" onClick={() => dispatch({ type: 'keep-editing' })}>
                                Keep editing
                            </button>
                        </div>
                    </div>
                )}

                {edit.error !== null && (
                    <p className="viewer__error" role="alert">
                        <AlertIcon size={18} />
                        <span>{edit.error}</span>
                    </p>
                )}

                <label htmlFor={editorLabel} className="visually-hidden">
                    File text
                </label>
                <textarea
                    id={editorLabel}
                    className="viewer__editor"
                    value={edit.text}
                    autoCapitalize="off"
                    autoCorrect="off"
                    spellCheck
                    // A phone's keyboard would cover half the file before he has chosen where to type.
                    autoFocus={!isTouchFirst()}
                    onChange={(event) => dispatch({ type: 'typed', text: event.target.value })}
                />

                <p className="viewer__based">
                    Based on version {edit.baseVersion}.
                    {edit.conflict !== null ? ' Your text is kept here until you choose.' : edit.restored ? ' Your unsaved text from before is back.' : ''}
                </p>
            </section>
        );
    }

    return (
        <section className="viewer" data-mode="read" ref={panel} tabIndex={-1} aria-label={file?.name ?? 'File'}>
            <div className="viewer__status-bar" />

            <header className="viewer__header">
                <div className="viewer__bar">
                    <button type="button" className="viewer__close" aria-label="Close" onClick={onClose}>
                        <CloseIcon size={20} />
                    </button>
                    <h2 className="viewer__name">{file?.name ?? ''}</h2>
                    {file !== null && problem !== 'missing' && <Action file={file} onEdit={startEditing} />}
                </div>

                {file !== null && problem !== 'missing' && (
                    <div className="viewer__keep">
                        {file.kind === 'image' ? (
                            // An image has nothing to index, so there is nothing to switch.
                            <span className="viewer__keep-label">Images aren&rsquo;t remembered. Donna can look again when you ask.</span>
                        ) : (
                            <>
                                <span className="viewer__keep-label" id={keepLabel}>
                                    {file.kept ? 'Kept. Donna finds it when it comes up' : 'Not kept. Only for this conversation'}
                                </span>
                                <button type="button" role="switch" className="switch" aria-checked={file.kept} aria-labelledby={keepLabel} onClick={toggleKeep}>
                                    <span className="switch__knob" />
                                </button>
                            </>
                        )}
                    </div>
                )}

                {keepError !== null && (
                    <p className="viewer__error" role="alert">
                        <AlertIcon size={18} />
                        <span>{keepError}</span>
                    </p>
                )}
            </header>

            {problem === 'missing' ? (
                <div className="viewer__notice">
                    <p>This file was deleted. Ask Donna to bring it back.</p>
                    <button type="button" className="button" onClick={onClose}>
                        Close
                    </button>
                </div>
            ) : file === null ? (
                problem === 'unreachable' ? (
                    <div className="viewer__notice" role="alert">
                        <p>Couldn&rsquo;t open this file. Check your connection.</p>
                        <button type="button" className="button" onClick={() => void load(false)}>
                            Try again
                        </button>
                    </div>
                ) : (
                    <div className="viewer__body" aria-busy="true" />
                )
            ) : (
                <>
                    <Body file={file} />

                    <footer className="viewer__footer">
                        <button type="button" className="viewer__details-toggle" aria-expanded={details} aria-controls={detailsId} onClick={() => setDetails((open) => !open)}>
                            <span>Details</span>
                            <ChevronIcon direction={details ? 'down' : 'up'} size={16} />
                        </button>
                        {details && <Details id={detailsId} file={file} timeZone={timeZone} />}
                    </footer>
                </>
            )}
        </section>
    );
}

function isFile(data: unknown): data is FileDetail {
    return typeof data === 'object' && data !== null && 'id' in data && 'version' in data;
}

function saveFailure(error: unknown): string {
    if (error instanceof NetworkError) {
        return "Couldn't save. Check your connection and try again. Your text is still here.";
    }

    if (error instanceof ApiError && error.status === 422) {
        return error.firstMessage;
    }

    return "Couldn't save. Try again. Your text is still here.";
}

/** The one thing the header offers for this kind of file. */
function Action({ file, onEdit }: { file: FileDetail; onEdit: () => void }) {
    if (file.kind === 'pdf' || file.kind === 'image') {
        return (
            <a className="button viewer__action" href={fileContentUrl(file.id)} target="_blank" rel="noopener">
                Open original
                <ExternalIcon size={14} />
            </a>
        );
    }

    if (file.kind === 'markdown' && file.editable) {
        return (
            <button type="button" className="button viewer__action" onClick={onEdit}>
                Edit
            </button>
        );
    }

    return <span className="viewer__readonly">Read-only</span>;
}

function Body({ file }: { file: FileDetail }) {
    if (file.extraction === 'failed') {
        return (
            <div className="viewer__body">
                <p className="viewer__error" role="alert">
                    <AlertIcon size={18} />
                    <span>Donna couldn&rsquo;t read this file{file.extraction_error ? `: ${file.extraction_error}` : '.'}</span>
                </p>
            </div>
        );
    }

    const truncated = file.truncated && <p className="viewer__note">Only the first part is shown. The file is too large to show whole.</p>;

    switch (file.kind) {
        case 'markdown':
            return (
                <div className="viewer__body">
                    {truncated}
                    <article className="viewer__article">
                        <Markdown text={file.body ?? ''} />
                    </article>
                </div>
            );

        case 'csv':
            return file.table === null ? <div className="viewer__body" /> : <Table table={file.table} />;

        case 'text':
            return (
                <div className="viewer__body">
                    {truncated}
                    <pre className="viewer__text">{file.body ?? ''}</pre>
                </div>
            );

        case 'pdf':
            return <Pdf file={file} truncated={truncated} />;

        case 'image':
            return (
                <div className="viewer__body viewer__body--image">
                    <img className="viewer__image" src={fileContentUrl(file.id, 'preview')} alt={file.name} />
                </div>
            );
    }
}

/** A PDF is shown as the text Donna reads, page by page. The original is one tap away. */
function Pdf({ file, truncated }: { file: FileDetail; truncated: React.ReactNode }) {
    const pages = useMemo(() => splitPages(file.body ?? ''), [file.body]);
    const count = file.meta.pages ?? pages.length;

    if (file.meta.has_text === false) {
        return (
            <div className="viewer__body">
                <p className="viewer__note">This PDF has no text to show (it looks like a scan). Donna looks at its pages as pictures. Open the original to see it.</p>
            </div>
        );
    }

    return (
        <div className="viewer__body">
            <p className="viewer__note">
                Shown as the text Donna reads, {formatCount(count)} {count === 1 ? 'page' : 'pages'}. The layout isn&rsquo;t shown. Open the original to see it.
            </p>
            {truncated}
            <article className="viewer__pages">
                {pages.map((page, index) => (
                    <section key={index} aria-label={`Page ${index + 1}`}>
                        <h3 className="viewer__page-label">Page {index + 1}</h3>
                        <pre className="viewer__page">{page.replace(/^(\s*\n)+/, '').trimEnd()}</pre>
                    </section>
                ))}
            </article>
        </div>
    );
}

/** A CSV as a table: the first rows, as the server parsed them. Totals are Donna's job, done in code. */
function Table({ table }: { table: FileTable }) {
    const numeric = useMemo(() => numericColumns(table), [table]);

    return (
        <>
            <div className="viewer__body viewer__body--table">
                <table className="file-table">
                    <thead>
                        <tr>
                            {table.columns.map((column, index) => (
                                <th key={index} scope="col" data-numeric={numeric[index]}>
                                    {column}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {table.rows.map((row, at) => (
                            <tr key={at}>
                                {table.columns.map((_, index) => (
                                    <td key={index} data-numeric={numeric[index]}>
                                        {row[index] ?? ''}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
            <p className="viewer__count">
                Showing {formatCount(table.rows.length)} of {formatCount(table.total_rows)} rows. Scroll sideways for more columns. Ask Donna for totals.
            </p>
        </>
    );
}

function Details({ id, file, timeZone }: { id: string; file: FileDetail; timeZone: string }) {
    const downloadable = file.kind === 'markdown' || file.kind === 'csv' || file.kind === 'text';

    return (
        <dl className="viewer__details" id={id}>
            <dt>Added</dt>
            <dd>{momentLabel(file.created_at, timeZone)}</dd>
            <dt>Modified</dt>
            <dd>{momentLabel(file.updated_at, timeZone)}</dd>
            <dt>Size</dt>
            <dd>{sizeDetail(file)}</dd>
            {file.last_author !== null && (
                <>
                    <dt>Last edited by</dt>
                    <dd>{file.last_author === 'donna' ? 'Donna' : 'You'}</dd>
                </>
            )}
            {file.version !== null && (
                <>
                    <dt>Version</dt>
                    <dd>{file.version === 1 ? '1 (original)' : file.version}</dd>
                </>
            )}
            {downloadable && (
                <>
                    <dt>File</dt>
                    <dd>
                        <a className="viewer__download" href={fileContentUrl(file.id)} download={file.name}>
                            Download
                        </a>
                    </dd>
                </>
            )}
        </dl>
    );
}
