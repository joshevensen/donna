import { type MouseEvent, type Ref, useCallback, useImperativeHandle, useLayoutEffect, useRef, useState } from 'react';
import type { AppAction, Source } from '../api/types';
import { timeLabel } from '../lib/dates';
import { splitFences } from '../lib/fences';
import { arrivalLabel } from '../lib/files';
import type { PushResult } from '../lib/push';
import { type BubbleRow, bubbleRadius, isCodeOnly, type ListedFile, type Row } from '../lib/timeline';
import { ActionButton } from './ActionButton';
import { FileCard } from './FileCard';
import { AlertIcon, RetryIcon } from './icons';
import { CodeBlock, Markdown } from './Markdown';

/** Closer to the newest message than this, and a new one scrolls into view. */
const NEAR_BOTTOM_PX = 120;

/** Closer to the oldest loaded message than this, and the next page is fetched. */
const NEAR_TOP_PX = 400;

export interface ThreadHandle {
    /** Jump to the newest message, e.g. after Josh sends one. */
    scrollToBottom: () => void;
}

interface Props {
    ref?: Ref<ThreadHandle>;
    rows: Row[];
    timeZone: string;
    hasOlder: boolean;
    loadingOlder: boolean;
    onLoadOlder: () => void;
    onRetrySend: (key: string) => void;
    onDiscardSend: (key: string) => void;
    onRetryReply: (id: number) => void;
    onShowSources: (sources: Source[]) => void;
    onOpenFile: (id: number) => void;
    runAction: (type: AppAction['type']) => Promise<PushResult>;
}

export function Thread({ ref, rows, timeZone, hasOlder, loadingOlder, onLoadOlder, onRetrySend, onDiscardSend, onRetryReply, onShowSources, onOpenFile, runAction }: Props) {
    const scroller = useRef<HTMLDivElement>(null);
    const [openKey, setOpenKey] = useState<string | null>(null);

    // Where the view was before this render. The column is reversed, so scrollTop is 0 at the
    // newest message and goes negative toward older ones.
    const before = useRef({ height: 0, top: 0, lastKey: '' });
    const jump = useRef(false);

    useImperativeHandle(ref, () => ({
        scrollToBottom: () => {
            jump.current = true;

            if (scroller.current) {
                scroller.current.scrollTop = 0;
            }
        },
    }));

    const lastKey = rows[rows.length - 1]?.key ?? '';

    useLayoutEffect(() => {
        const element = scroller.current;

        if (!element) {
            return;
        }

        const previous = before.current;

        if (lastKey !== previous.lastKey) {
            if (jump.current || Math.abs(previous.top) < NEAR_BOTTOM_PX) {
                element.scrollTop = 0;
            } else {
                // Reading further up: hold what's on screen still while the new message lands below.
                element.scrollTop = previous.top - (element.scrollHeight - previous.height);
            }
        }

        jump.current = false;
        before.current = { height: element.scrollHeight, top: element.scrollTop, lastKey };
    });

    const maybeLoadOlder = useCallback(() => {
        const element = scroller.current;

        if (!element || !hasOlder || loadingOlder) {
            return;
        }

        if (element.scrollHeight - element.clientHeight - Math.abs(element.scrollTop) < NEAR_TOP_PX) {
            onLoadOlder();
        }
    }, [hasOlder, loadingOlder, onLoadOlder]);

    // A first page that doesn't fill the screen can't be scrolled, so fetch the next one unasked.
    useLayoutEffect(maybeLoadOlder);

    const onScroll = () => {
        if (scroller.current) {
            before.current.top = scroller.current.scrollTop;
        }

        maybeLoadOlder();
    };

    return (
        <div className="messages" ref={scroller} onScroll={onScroll}>
            <div className="thread">
                {loadingOlder && <div className="thread__older">Loading earlier messages…</div>}
                {rows.map((row) => {
                    switch (row.kind) {
                        case 'date':
                            return (
                                <div key={row.key} className="date-divider">
                                    {row.label}
                                </div>
                            );

                        case 'reply-failed':
                            return (
                                <div key={row.key} className="reply-failed" role="alert">
                                    <AlertIcon />
                                    <span>Donna couldn&rsquo;t reply.</span>
                                    <button type="button" className="reply-failed__retry" onClick={() => onRetryReply(row.replyId)}>
                                        Try again
                                    </button>
                                </div>
                            );

                        case 'typing':
                            return (
                                <div key={row.key} className="row" data-who="donna" data-first={!row.joins} role="status" aria-label="Donna is typing">
                                    <div className="typing" style={{ borderRadius: row.joins ? bubbleRadius('donna', 'last') : bubbleRadius('donna', 'single') }}>
                                        <span className="typing__dot" />
                                        <span className="typing__dot" />
                                        <span className="typing__dot" />
                                    </div>
                                </div>
                            );

                        case 'bubble':
                            return (
                                <Bubble
                                    key={row.key}
                                    row={row}
                                    open={openKey === row.key}
                                    time={timeLabel(row.createdAt, timeZone)}
                                    timeZone={timeZone}
                                    onToggle={() => setOpenKey((current) => (current === row.key ? null : row.key))}
                                    onRetrySend={onRetrySend}
                                    onDiscardSend={onDiscardSend}
                                    onShowSources={onShowSources}
                                    onOpenFile={onOpenFile}
                                    runAction={runAction}
                                />
                            );
                    }
                })}
            </div>
        </div>
    );
}

interface BubbleProps {
    row: BubbleRow;
    open: boolean;
    time: string;
    timeZone: string;
    onToggle: () => void;
    onRetrySend: (key: string) => void;
    onDiscardSend: (key: string) => void;
    onShowSources: (sources: Source[]) => void;
    onOpenFile: (id: number) => void;
    runAction: (type: AppAction['type']) => Promise<PushResult>;
}

function Bubble({ row, open, time, timeZone, onToggle, onRetrySend, onDiscardSend, onShowSources, onOpenFile, runAction }: BubbleProps) {
    const failed = row.outbox?.state === 'failed';
    const count = row.sources.length;

    // What Josh attached rides at the top of his bubble; what Donna touched sits under her reply.
    const attached = row.who === 'josh' ? row.files : [];
    const touched = row.who === 'donna' ? row.files : [];

    // A tap on the bubble shows its time. Not when the tap was on a link or the copy button,
    // and not when it was the end of selecting some text.
    const onClick = (event: MouseEvent<HTMLDivElement>) => {
        if ((event.target as HTMLElement).closest('a, button, .code-block') || window.getSelection()?.toString()) {
            return;
        }

        onToggle();
    };

    return (
        <div className="row" data-who={row.who} data-first={row.position === 'single' || row.position === 'first'}>
            <div
                className="bubble"
                style={{ borderRadius: bubbleRadius(row.who, row.position) }}
                data-code-only={isCodeOnly(row.text)}
                data-sending={row.outbox?.state === 'sending'}
                data-cards={attached.length > 0 || row.listing !== null}
                onClick={onClick}
            >
                {attached.length > 0 && (
                    <div className="bubble__files">
                        {attached.map((file) => (
                            <FileCard key={file.id} file={file} surface="inset" thumbnail onOpen={onOpenFile} />
                        ))}
                    </div>
                )}
                {row.listing !== null ? (
                    <FileListing files={row.listing} timeZone={timeZone} onOpenFile={onOpenFile} />
                ) : (
                    row.text !== '' && (row.markdown ? <Markdown text={row.text} /> : <Typed text={row.text} />)
                )}
            </div>

            {!failed && (
                <button type="button" className="row__toggle" aria-expanded={open} onClick={onToggle}>
                    {open ? 'Hide the time' : row.who === 'donna' ? 'Show the time and sources' : 'Show the time'}
                </button>
            )}

            {failed && row.outbox && row.outbox.refused === undefined && (
                <button type="button" className="not-delivered" onClick={() => onRetrySend(row.outbox!.key)}>
                    <RetryIcon size={15} />
                    <span>Not delivered. Tap to retry</span>
                </button>
            )}

            {/* The server said no for good: trying again would get the same answer. */}
            {failed && row.outbox && row.outbox.refused !== undefined && (
                <button type="button" className="not-delivered" onClick={() => onDiscardSend(row.outbox!.key)}>
                    <AlertIcon size={15} />
                    <span>Not sent. {row.outbox.refused} Tap to remove it.</span>
                </button>
            )}

            {open && !failed && (
                <div className="row__info">
                    <span>{time}</span>
                    {count > 0 && (
                        <>
                            <span className="row__info-separator" aria-hidden="true">
                                ·
                            </span>
                            <button type="button" className="link-button" onClick={() => onShowSources(row.sources)}>
                                {count} {count === 1 ? 'source' : 'sources'}
                            </button>
                        </>
                    )}
                </div>
            )}

            {touched.length > 0 && (
                <div className="row__files">
                    {touched.map((file) => (
                        <FileCard key={file.id} file={file} surface="raised" onOpen={onOpenFile} />
                    ))}
                </div>
            )}

            {row.actions.map((action) => (
                <ActionButton key={action.type} id={`${row.messageId}:${action.type}`} action={action} run={runAction} />
            ))}
        </div>
    );
}

/**
 * The answer to /files: the kept files, then the ones only used lately, each
 * with when it arrived. Which group a file is in follows the keep switch.
 */
function FileListing({ files, timeZone, onOpenFile }: { files: ListedFile[]; timeZone: string; onOpenFile: (id: number) => void }) {
    const groups = [
        { title: 'Kept', files: files.filter((file) => file.kept) },
        { title: 'Recent, not kept', files: files.filter((file) => !file.kept) },
    ].filter((group) => group.files.length > 0);

    return (
        <div className="file-listing">
            {groups.map((group) => (
                <section key={group.title} className="file-listing__group" aria-label={group.title}>
                    <h3 className="file-listing__title">{group.title}</h3>
                    {group.files.map((file) => (
                        <FileCard key={file.id} file={file} surface="inset" line={arrivalLabel(file.created_at, timeZone)} onOpen={onOpenFile} />
                    ))}
                </section>
            ))}
        </div>
    );
}

/**
 * Josh's words as he typed them, except that a fenced block is drawn as code.
 * Nothing else is read as markdown.
 */
function Typed({ text }: { text: string }) {
    const segments = splitFences(text);

    if (!segments.some((segment) => segment.kind === 'code')) {
        return text;
    }

    return (
        <div className="markdown">
            {segments.map((segment, at) =>
                segment.kind === 'code' ? <CodeBlock key={at} language={segment.language} text={segment.text} /> : <p key={at}>{segment.text}</p>,
            )}
        </div>
    );
}
