import type { FileKind, FileMeta, FileRole, FileTable } from '../api/types';
import { dateLabel, timeLabel } from './dates';

/*
 | How a file is described wherever it shows: on a chip, a card, in the
 | viewer. All of it is formatting of numbers the server counted.
 */

/** Everything a card needs, whichever list the file came from. */
export interface CardFile {
    id: number;
    name: string;
    kind: FileKind;
    size_bytes: number;
    meta: FileMeta;
    kept: boolean;
    deleted: boolean;
    role?: FileRole;
    /** What an edit changed, when the card is for an edit. */
    note?: string | null;
}

/** What is known about a file right now, wherever its cards are: the keep switch moves all of them. */
export interface FileState {
    name: string;
    kept: boolean;
    deleted: boolean;
}

const KINDS: Record<FileKind, string> = {
    markdown: 'Markdown',
    csv: 'CSV',
    text: 'Text',
    pdf: 'PDF',
    image: 'Image',
};

export function kindLabel(kind: FileKind): string {
    return KINDS[kind];
}

/** "1,284": the same grouping on every device. */
export function formatCount(count: number): string {
    return new Intl.NumberFormat('en-US').format(count);
}

/** "512 B", "48 KB", "4.8 MB". Kilobytes are whole; megabytes keep one decimal when it says something. */
export function formatSize(bytes: number): string {
    if (bytes < 1024) {
        return `${bytes} B`;
    }

    if (bytes < 1024 * 1024) {
        return `${Math.max(1, Math.round(bytes / 1024))} KB`;
    }

    const megabytes = Math.round((bytes / (1024 * 1024)) * 10) / 10;

    return `${Number.isInteger(megabytes) ? megabytes.toFixed(0) : megabytes.toFixed(1)} MB`;
}

function plural(count: number, one: string): string {
    return `${formatCount(count)} ${one}${count === 1 ? '' : 's'}`;
}

/** What there is in the file, when code counted it: rows of a CSV, pages of a PDF. */
function contents(kind: FileKind, meta: FileMeta): string | null {
    if (kind === 'csv' && meta.rows !== undefined) {
        return plural(meta.rows, 'row');
    }

    if (kind === 'pdf' && meta.pages !== undefined) {
        return plural(meta.pages, 'page');
    }

    return null;
}

/** "CSV · 1,284 rows · 48 KB" */
export function describeFile(file: Pick<CardFile, 'kind' | 'size_bytes' | 'meta'>): string {
    return [kindLabel(file.kind), contents(file.kind, file.meta), formatSize(file.size_bytes)].filter((part) => part !== null).join(' · ');
}

/** The size as the viewer's details give it: "48 KB, 1,284 rows". */
export function sizeDetail(file: Pick<CardFile, 'kind' | 'size_bytes' | 'meta'>): string {
    const counted = contents(file.kind, file.meta) ?? (file.meta.lines !== undefined && file.kind !== 'image' ? plural(file.meta.lines, 'line') : null);

    return [formatSize(file.size_bytes), counted].filter((part) => part !== null).join(', ');
}

/** The second line of a card: what the file is, or what Donna did to it. */
export function cardLine(file: CardFile): string {
    if (file.deleted) {
        return 'Deleted';
    }

    if (file.role === 'edited') {
        return file.note ? `Edited · ${file.note}` : 'Edited';
    }

    if (file.role === 'created') {
        return 'Created';
    }

    return describeFile(file);
}

/** The latest known name, keep state and deletion laid over a card's own. */
export function withState<T extends CardFile>(file: T, states: ReadonlyMap<number, FileState> | undefined): T {
    const state = states?.get(file.id);

    return state === undefined ? file : { ...file, name: state.name, kept: state.kept, deleted: state.deleted };
}

/** When a file arrived, for a list: "Today, 2:14 PM", then "Yesterday", "Tuesday, Nov 24", "Nov 20". */
export function arrivalLabel(moment: string, timeZone: string, now: Date = new Date()): string {
    const day = dateLabel(moment, timeZone, now);

    return day === 'Today' ? `Today, ${timeLabel(moment, timeZone)}` : day;
}

/** A moment for the viewer's details: the day, and always the time. */
export function momentLabel(moment: string, timeZone: string, now: Date = new Date()): string {
    return `${dateLabel(moment, timeZone, now)}, ${timeLabel(moment, timeZone)}`;
}

/**
 * A PDF's text as its pages. The server joins them with a form feed; a
 * trailing one (every page ends with it) doesn't make an extra page.
 */
export function splitPages(body: string): string[] {
    const pages = body.split('\f');

    if (pages.length > 1 && pages[pages.length - 1]!.trim() === '') {
        pages.pop();
    }

    return pages;
}

/** Money, counts and percentages: "$1,094.15", "-12", "(40.00)", "8%". Dates and IDs are not. */
export function isNumeric(cell: string): boolean {
    return /^\(?[-+]?[$€£]?\s?\d[\d,]*(\.\d+)?\s?%?\)?$/.test(cell.trim());
}

/** Which columns of a table hold numbers, so they can be set flush right like a ledger. */
export function numericColumns(table: FileTable): boolean[] {
    return table.columns.map((_, column) => {
        const cells = table.rows.map((row) => row[column] ?? '').filter((cell) => cell.trim() !== '');

        return cells.length > 0 && cells.every(isNumeric);
    });
}
