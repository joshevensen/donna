import type { AppAction, Message, Source } from '../api/types';
import { dateLabel, dayKey } from './dates';
import type { OutboxItem } from './storage';

/*
 | Turns what the app holds (messages from the server, sends that haven't
 | been confirmed, local notes) into the rows the thread draws: date
 | dividers, bubbles grouped into runs, the "couldn't reply" line, the dots.
 */

/** Something shown on this device only, like a command and its answer. Never sent anywhere. */
export interface Note {
    id: string;
    /** Donna's answer, or the command Josh typed. */
    who: Who;
    text: string;
    createdAt: string;
}

export type TimelineItem =
    | { kind: 'message'; message: Message; visibleBubbles?: number }
    | { kind: 'outbox'; item: OutboxItem }
    | { kind: 'note'; note: Note };

export type Who = 'josh' | 'donna';

/** Where a bubble sits in a run of bubbles from one sender. */
export type Position = 'single' | 'first' | 'middle' | 'last';

export interface BubbleRow {
    kind: 'bubble';
    key: string;
    who: Who;
    text: string;
    /** Donna's words are markdown; Josh's are shown exactly as typed. */
    markdown: boolean;
    position: Position;
    createdAt: string;
    sources: Source[];
    /** Buttons she offered, drawn under the last bubble of the reply. */
    actions: AppAction[];
    /** The reply these belong to, to remember which buttons were used. */
    messageId: number | null;
    /** Set on a message that hasn't been delivered. */
    outbox: OutboxItem | null;
}

export type Row =
    | { kind: 'date'; key: string; label: string }
    | BubbleRow
    | { kind: 'reply-failed'; key: string; replyId: number }
    | { kind: 'typing'; key: string; joins: boolean };

interface Options {
    timeZone: string;
    now: Date;
    typing: boolean;
}

const RADIUS = '20px';

/**
 * A bubble's corners, as CSS (top-left, top-right, bottom-right,
 * bottom-left). Every corner is round except where bubbles from one sender
 * meet: those are square, so a run reads as one bubble cut by thin lines.
 */
export function bubbleRadius(who: Who, position: Position): string {
    if (position === 'single') {
        return RADIUS;
    }

    const joinsAbove = position !== 'first';
    const joinsBelow = position !== 'last';

    return who === 'josh'
        ? `${RADIUS} ${joinsAbove ? '0' : RADIUS} ${joinsBelow ? '0' : RADIUS} ${RADIUS}`
        : `${joinsAbove ? '0' : RADIUS} ${RADIUS} ${RADIUS} ${joinsBelow ? '0' : RADIUS}`;
}

type Draft = Omit<BubbleRow, 'position'>;

function bubblesOf(item: TimelineItem): Array<Draft | Extract<Row, { kind: 'reply-failed' }>> {
    if (item.kind === 'outbox') {
        return [
            {
                kind: 'bubble',
                key: `outbox:${item.item.key}`,
                who: 'josh',
                text: item.item.body,
                markdown: false,
                createdAt: item.item.createdAt,
                sources: [],
                actions: [],
                messageId: null,
                outbox: item.item,
            },
        ];
    }

    if (item.kind === 'note') {
        return [
            {
                kind: 'bubble',
                key: `note:${item.note.id}`,
                who: item.note.who,
                text: item.note.text,
                markdown: item.note.who === 'donna',
                createdAt: item.note.createdAt,
                sources: [],
                actions: [],
                messageId: null,
                outbox: null,
            },
        ];
    }

    const { message } = item;

    if (message.author === 'donna' && message.status === 'failed') {
        return [{ kind: 'reply-failed', key: `failed:${message.id}`, replyId: message.id }];
    }

    // A reply she is still writing has no words yet: the dots stand in for it.
    if (message.status !== 'complete') {
        return [];
    }

    const visible = message.bubbles.slice(0, item.visibleBubbles ?? message.bubbles.length);
    const whole = visible.length === message.bubbles.length;

    return visible.map((text, index) => ({
        kind: 'bubble',
        key: `message:${message.id}:${index}`,
        who: message.author,
        text,
        markdown: message.author === 'donna',
        createdAt: message.created_at,
        sources: message.sources,
        actions: whole && index === visible.length - 1 ? message.actions : [],
        messageId: message.id,
        outbox: null,
    }));
}

function createdAt(item: TimelineItem): string {
    return item.kind === 'message' ? item.message.created_at : item.kind === 'outbox' ? item.item.createdAt : item.note.createdAt;
}

export function buildRows(items: TimelineItem[], options: Options): Row[] {
    const rows: Array<Row | Draft> = [];
    let day: string | null = null;

    for (const item of items) {
        const entries = bubblesOf(item);

        if (entries.length === 0) {
            continue;
        }

        const itemDay = dayKey(createdAt(item), options.timeZone);

        if (itemDay !== day) {
            day = itemDay;
            rows.push({ kind: 'date', key: `date:${itemDay}`, label: dateLabel(createdAt(item), options.timeZone, options.now) });
        }

        rows.push(...entries);
    }

    const last = rows[rows.length - 1];

    if (options.typing) {
        rows.push({ kind: 'typing', key: 'typing', joins: last?.kind === 'bubble' && last.who === 'donna' });
    }

    // The dots are one more of Donna's bubbles as far as the run's corners go.
    const sender = (row: Row | Draft | undefined): Who | null =>
        row?.kind === 'bubble' ? row.who : row?.kind === 'typing' ? 'donna' : null;

    return rows.map((row, index): Row => {
        if (row.kind !== 'bubble') {
            return row;
        }

        const joinsAbove = sender(rows[index - 1]) === row.who;
        const joinsBelow = sender(rows[index + 1]) === row.who;

        return {
            ...row,
            position: joinsAbove && joinsBelow ? 'middle' : joinsAbove ? 'last' : joinsBelow ? 'first' : 'single',
        };
    });
}

/** A bubble that is nothing but a code block gets a snugger frame. */
export function isCodeOnly(text: string): boolean {
    return /^(`{3,}|~{3,})[^\n]*\n[\s\S]*\n\1\s*$/.test(text.trim());
}
