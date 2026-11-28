/*
 | iOS doesn't shrink the page when the keyboard opens: it shrinks the visual
 | viewport and may scroll it. The chat is pinned to that viewport so the
 | input rides above the keyboard and the page itself never scrolls.
 */

/**
 * A visual viewport this close to the window's height means no keyboard.
 * The slack covers toolbars, and iOS 26 leaving a stale offset behind after
 * the keyboard closes.
 */
export const KEYBOARD_THRESHOLD = 80;

export interface ViewportFrame {
    keyboardOpen: boolean;
    /** Pixels from the top of the page to pin the chat at; 0 when the keyboard is closed. */
    top: number;
    /** The height to give the chat, or null to let it fill the screen. */
    height: number | null;
}

export function viewportFrame(windowHeight: number, visualHeight: number, visualOffsetTop: number): ViewportFrame {
    if (visualHeight >= windowHeight - KEYBOARD_THRESHOLD) {
        return { keyboardOpen: false, top: 0, height: null };
    }

    return { keyboardOpen: true, top: Math.max(0, Math.round(visualOffsetTop)), height: Math.round(visualHeight) };
}

export interface ComposerMetrics {
    /** One line of text, in pixels. */
    lineHeight: number;
    /** The textarea's top and bottom padding together, in pixels. */
    padding: number;
}

export const MIN_LINES = 3;
export const MAX_LINES = 6;

/**
 * The open input's height for text that measures `contentHeight`: at least
 * three lines, at most six, after which it scrolls.
 */
export function composerHeight(contentHeight: number, metrics: ComposerMetrics): { height: number; scrolls: boolean } {
    const min = metrics.lineHeight * MIN_LINES + metrics.padding;
    const max = metrics.lineHeight * MAX_LINES + metrics.padding;

    return { height: Math.min(max, Math.max(min, Math.ceil(contentHeight))), scrolls: contentHeight > max + 1 };
}

/**
 * How tall the command list may be: the space between the top of what's
 * visible and the input, less a margin. Never negative.
 */
export function pickerMaxHeight(composerTop: number, visibleTop: number, margin = 12): number {
    return Math.max(0, Math.round(composerTop - visibleTop - margin));
}
