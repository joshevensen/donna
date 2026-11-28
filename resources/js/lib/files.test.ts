import { describe, expect, it } from 'vitest';
import { arrivalLabel, type CardFile, cardLine, describeFile, formatSize, isNumeric, numericColumns, sizeDetail, splitPages, withState } from './files';

function card(overrides: Partial<CardFile> = {}): CardFile {
    return { id: 1, name: 'stripe-payouts-nov.csv', kind: 'csv', size_bytes: 49_152, meta: { rows: 1284 }, kept: false, deleted: false, ...overrides };
}

describe('formatSize', () => {
    it.each([
        [512, '512 B'],
        [1024, '1 KB'],
        [3_100, '3 KB'],
        [49_152, '48 KB'],
        [5_033_165, '4.8 MB'],
        [2 * 1024 * 1024, '2 MB'],
        [10 * 1024 * 1024, '10 MB'],
    ])('%d bytes is %s', (bytes, label) => {
        expect(formatSize(bytes)).toBe(label);
    });
});

describe('describeFile', () => {
    it.each([
        [card(), 'CSV · 1,284 rows · 48 KB'],
        [card({ kind: 'pdf', size_bytes: 1_258_291, meta: { pages: 4, has_text: true } }), 'PDF · 4 pages · 1.2 MB'],
        [card({ kind: 'pdf', size_bytes: 2048, meta: { pages: 1 } }), 'PDF · 1 page · 2 KB'],
        [card({ kind: 'markdown', size_bytes: 3072, meta: { lines: 42 } }), 'Markdown · 3 KB'],
        [card({ kind: 'image', size_bytes: 2_202_009, meta: { width: 4032, height: 3024 } }), 'Image · 2.1 MB'],
    ])('says what the file is and how big', (file, line) => {
        expect(describeFile(file)).toBe(line);
    });
});

describe('cardLine', () => {
    it('describes a file Josh attached or Donna opened', () => {
        expect(cardLine(card({ role: 'attached' }))).toBe('CSV · 1,284 rows · 48 KB');
        expect(cardLine(card({ role: 'opened' }))).toBe('CSV · 1,284 rows · 48 KB');
    });

    it('says what Donna did to one she changed or wrote', () => {
        expect(cardLine(card({ role: 'edited', note: "Replaced 14 matches of 'Fibermade Co'" }))).toBe("Edited · Replaced 14 matches of 'Fibermade Co'");
        expect(cardLine(card({ role: 'edited', note: null }))).toBe('Edited');
        expect(cardLine(card({ role: 'created' }))).toBe('Created');
    });

    it('says a deleted file is deleted, whatever its role', () => {
        expect(cardLine(card({ role: 'edited', note: '3 lines', deleted: true }))).toBe('Deleted');
    });
});

describe('sizeDetail', () => {
    it('adds what code counted to the size', () => {
        expect(sizeDetail(card())).toBe('48 KB, 1,284 rows');
        expect(sizeDetail(card({ kind: 'markdown', size_bytes: 3072, meta: { lines: 42 } }))).toBe('3 KB, 42 lines');
        expect(sizeDetail(card({ kind: 'image', size_bytes: 3072, meta: {} }))).toBe('3 KB');
    });
});

describe('withState', () => {
    // The keep switch, or Donna keeping a file later, changes every card for that file.
    it('lays the latest known name, keep state and deletion over a card', () => {
        const states = new Map([[1, { name: 'payouts.csv', kept: true, deleted: false }]]);

        expect(withState(card(), states)).toMatchObject({ name: 'payouts.csv', kept: true, deleted: false });
        expect(withState(card({ id: 2 }), states)).toMatchObject({ name: 'stripe-payouts-nov.csv', kept: false });
    });
});

describe('arrivalLabel', () => {
    const now = new Date('2026-11-27T21:00:00Z');

    it('gives the time for today and only the day before that, in Josh’s timezone', () => {
        expect(arrivalLabel('2026-11-27T20:14:00Z', 'America/Chicago', now)).toBe('Today, 2:14 PM');
        expect(arrivalLabel('2026-11-24T20:14:00Z', 'America/Chicago', now)).toBe('Tuesday, Nov 24');
        expect(arrivalLabel('2026-11-03T20:14:00Z', 'America/Chicago', now)).toBe('Nov 3');
    });
});

describe('splitPages', () => {
    it('splits a PDF’s text on form feeds without inventing a last empty page', () => {
        expect(splitPages('Page one.\fPage two.\f')).toEqual(['Page one.', 'Page two.']);
        expect(splitPages('Only page.')).toEqual(['Only page.']);
        expect(splitPages('One.\f\fThree.')).toEqual(['One.', '', 'Three.']);
    });
});

describe('numeric cells', () => {
    it.each(['$812.40', '1,094.15', '-12', '(40.00)', '8%', '0', '€ 9'])('reads %s as a number', (cell) => {
        expect(isNumeric(cell)).toBe(true);
    });

    it.each(['Nov 3', 'po_1QxA', '2026-11-03', 'Paid', '', '12 boxes'])('does not read %s as a number', (cell) => {
        expect(isNumeric(cell)).toBe(false);
    });

    it('marks a column numeric only when every filled cell is', () => {
        const table = {
            columns: ['Date', 'Amount', 'Fee', 'Note'],
            rows: [
                ['Nov 3', '$812.40', '', ''],
                ['Nov 4', '$220.00', '1.20', 'n/a'],
            ],
            total_rows: 2,
        };

        expect(numericColumns(table)).toEqual([false, true, true, false]);
    });
});
