/*
 | When a reply with several bubbles finishes while the app is open, its
 | bubbles arrive one at a time with the typing dots between them, like
 | someone sending a few texts in a row. Replies loaded later show whole.
 */

const BASE_MS = 500;
const PER_CHARACTER_MS = 18;
const SHORTEST_MS = 700;
const LONGEST_MS = 2200;

/** How long the dots show before a bubble: longer texts take longer to "type". */
export function revealDelay(bubble: string): number {
    return Math.min(LONGEST_MS, Math.max(SHORTEST_MS, BASE_MS + bubble.length * PER_CHARACTER_MS));
}

interface Reveal {
    id: number;
    bubbles: string[];
    shown: number;
}

export class RevealQueue {
    private queue: Reveal[] = [];
    private timer: ReturnType<typeof setTimeout> | null = null;

    constructor(private readonly onChange: () => void) {}

    /** Reveal a reply that just finished. Replies take turns, in the order they finished. */
    enqueue(id: number, bubbles: string[]): void {
        if (bubbles.length < 2 || this.queue.some((reveal) => reveal.id === id)) {
            return;
        }

        this.queue.push({ id, bubbles, shown: 0 });

        if (this.queue.length === 1) {
            this.advance();
        }
    }

    /** How many of a reply's bubbles to show right now. */
    visibleCount(id: number, total: number): number {
        const reveal = this.queue.find((candidate) => candidate.id === id);

        return reveal === undefined ? total : Math.min(reveal.shown, total);
    }

    /** True while a bubble is still to come: the dots show. */
    get typing(): boolean {
        return this.queue.length > 0;
    }

    dispose(): void {
        if (this.timer !== null) {
            clearTimeout(this.timer);
        }

        this.timer = null;
        this.queue = [];
    }

    private advance(): void {
        const current = this.queue[0];

        if (current === undefined) {
            return;
        }

        current.shown += 1;

        if (current.shown >= current.bubbles.length) {
            this.queue.shift();
            this.onChange();
            this.advance();

            return;
        }

        this.onChange();

        this.timer = setTimeout(() => this.advance(), revealDelay(current.bubbles[current.shown]!));
    }
}
