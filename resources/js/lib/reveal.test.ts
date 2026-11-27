import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { revealDelay, RevealQueue } from './reveal';

describe('RevealQueue', () => {
    beforeEach(() => {
        vi.useFakeTimers();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('shows a reply one bubble at a time, with the dots between', () => {
        const onChange = vi.fn();
        const queue = new RevealQueue(onChange);
        const bubbles = ['Two projects.', 'Fibermade came up two days ago.', 'Want a refresher?'];

        queue.enqueue(7, bubbles);

        expect(queue.visibleCount(7, 3)).toBe(1);
        expect(queue.typing).toBe(true);

        vi.advanceTimersByTime(revealDelay(bubbles[1]!) - 1);
        expect(queue.visibleCount(7, 3)).toBe(1);

        vi.advanceTimersByTime(1);
        expect(queue.visibleCount(7, 3)).toBe(2);
        expect(queue.typing).toBe(true);

        vi.advanceTimersByTime(revealDelay(bubbles[2]!));
        expect(queue.visibleCount(7, 3)).toBe(3);
        expect(queue.typing).toBe(false);
        expect(onChange).toHaveBeenCalled();
    });

    it('shows a reply it was never given whole: history is not replayed', () => {
        const queue = new RevealQueue(() => {});

        expect(queue.visibleCount(3, 4)).toBe(4);
        expect(queue.typing).toBe(false);
    });

    it('shows a one-bubble reply at once', () => {
        const queue = new RevealQueue(() => {});

        queue.enqueue(7, ['Got it.']);

        expect(queue.visibleCount(7, 1)).toBe(1);
        expect(queue.typing).toBe(false);
    });

    it('makes a second reply wait its turn', () => {
        const queue = new RevealQueue(() => {});

        queue.enqueue(7, ['One.', 'Two.']);
        queue.enqueue(9, ['Three.', 'Four.']);

        expect(queue.visibleCount(9, 2)).toBe(0);

        vi.advanceTimersByTime(revealDelay('Two.'));
        expect(queue.visibleCount(7, 2)).toBe(2);
        expect(queue.visibleCount(9, 2)).toBe(1);

        vi.advanceTimersByTime(revealDelay('Four.'));
        expect(queue.visibleCount(9, 2)).toBe(2);
        expect(queue.typing).toBe(false);
    });

    it('paces a bubble by its length, within limits', () => {
        expect(revealDelay('Ok.')).toBe(700);
        expect(revealDelay('x'.repeat(40))).toBeGreaterThan(revealDelay('x'.repeat(20)));
        expect(revealDelay('x'.repeat(5000))).toBe(2200);
    });
});
