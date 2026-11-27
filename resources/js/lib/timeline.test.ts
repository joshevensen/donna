import { describe, expect, it } from 'vitest';
import type { Message } from '../api/types';
import { type BubbleRow, bubbleRadius, buildRows, isCodeOnly, type Row, type TimelineItem } from './timeline';

const options = { timeZone: 'America/Chicago', now: new Date('2026-11-27T18:00:00Z'), typing: false };

let nextId = 1;

function message(author: 'josh' | 'donna', bubbles: string[], overrides: Partial<Message> = {}): TimelineItem {
    return {
        kind: 'message',
        message: {
            id: nextId++,
            author,
            body: bubbles.join('\n---\n'),
            bubbles,
            status: 'complete',
            in_reply_to_id: null,
            error: null,
            sources: [],
            actions: [],
            client_key: null,
            created_at: '2026-11-27T15:00:00Z',
            ...overrides,
        },
    };
}

const bubbles = (rows: Row[]) => rows.filter((row): row is BubbleRow => row.kind === 'bubble');
const shape = (rows: Row[]) => rows.map((row) => (row.kind === 'bubble' ? `${row.who}:${row.position}` : row.kind));

describe('buildRows', () => {
    it('groups consecutive bubbles from one sender into a run', () => {
        const rows = buildRows([message('josh', ['Where did we land?']), message('donna', ['One.', 'Two.', 'Three.']), message('josh', ['Thanks.']), message('josh', ['One more.'])], options);

        expect(shape(rows)).toEqual(['date', 'josh:single', 'donna:first', 'donna:middle', 'donna:last', 'josh:first', 'josh:last']);
    });

    it('starts a new run on a new day', () => {
        const rows = buildRows(
            [message('donna', ['Night.'], { created_at: '2026-11-26T04:00:00Z' }), message('donna', ['Morning.'], { created_at: '2026-11-27T15:00:00Z' })],
            options,
        );

        expect(rows.map((row) => (row.kind === 'date' ? row.label : row.kind === 'bubble' ? row.position : row.kind))).toEqual([
            'Wednesday, Nov 25',
            'single',
            'Today',
            'single',
        ]);
    });

    it('draws a failed reply as a line with a retry, and a reply she is still writing as nothing', () => {
        const failed = message('donna', [], { status: 'failed' });
        const pending = message('donna', [], { status: 'pending' });

        const rows = buildRows([message('josh', ['Hello?']), failed, message('josh', ['Anyone?']), pending], options);

        expect(shape(rows)).toEqual(['date', 'josh:single', 'reply-failed', 'josh:single']);
        expect(rows[2]).toMatchObject({ replyId: failed.kind === 'message' ? failed.message.id : 0 });
    });

    it('joins the typing dots onto her last bubble, and stands them alone after his', () => {
        const hers = buildRows([message('donna', ['One.'])], { ...options, typing: true });
        const his = buildRows([message('josh', ['Hi.'])], { ...options, typing: true });

        expect(shape(hers)).toEqual(['date', 'donna:first', 'typing']);
        expect(hers[2]).toMatchObject({ joins: true });
        expect(shape(his)).toEqual(['date', 'josh:single', 'typing']);
        expect(his[2]).toMatchObject({ joins: false });
    });

    it('shows only the bubbles revealed so far, and holds her button back until the last one', () => {
        const reply = message('donna', ['One.', 'Two.'], { actions: [{ type: 'sign_out', label: 'Sign out' }] });

        const partial = buildRows([{ ...reply, visibleBubbles: 1 } as TimelineItem], options);
        const whole = buildRows([reply], options);

        expect(bubbles(partial).map((row) => [row.text, row.actions.length])).toEqual([['One.', 0]]);
        expect(bubbles(whole).map((row) => [row.text, row.actions.length])).toEqual([
            ['One.', 0],
            ['Two.', 1],
        ]);
    });

    it('shows an unsent message as his bubble, carrying what a retry needs', () => {
        const item = { key: 'abc', body: 'Did it send?', createdAt: '2026-11-27T16:00:00Z', state: 'failed' as const };

        const [, bubble] = buildRows([{ kind: 'outbox', item }], options);

        expect(bubble).toMatchObject({ who: 'josh', text: 'Did it send?', markdown: false, outbox: item });
    });

    it('renders her words as markdown and his exactly as typed', () => {
        const rows = bubbles(buildRows([message('josh', ['**not bold**']), message('donna', ['**bold**'])], options));

        expect(rows.map((row) => row.markdown)).toEqual([false, true]);
    });
});

describe('bubbleRadius', () => {
    // top-left, top-right, bottom-right, bottom-left: square where bubbles of one sender meet.
    it.each([
        ['josh', 'single', '20px'],
        ['josh', 'first', '20px 20px 0 20px'],
        ['josh', 'middle', '20px 0 0 20px'],
        ['josh', 'last', '20px 0 20px 20px'],
        ['donna', 'single', '20px'],
        ['donna', 'first', '20px 20px 20px 0'],
        ['donna', 'middle', '0 20px 20px 0'],
        ['donna', 'last', '0 20px 20px 20px'],
    ] as const)('%s %s', (who, position, radius) => {
        expect(bubbleRadius(who, position)).toBe(radius);
    });
});

describe('isCodeOnly', () => {
    it('is true for a bubble that is one fenced block and nothing else', () => {
        expect(isCodeOnly('```php\necho 1;\n```')).toBe(true);
        expect(isCodeOnly('Here:\n```php\necho 1;\n```')).toBe(false);
        expect(isCodeOnly('`inline`')).toBe(false);
    });
});
