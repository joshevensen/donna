import { describe, expect, it } from 'vitest';
import { composerHeight, pickerMaxHeight, viewportFrame } from './viewport';

describe('viewportFrame', () => {
    it('pins the chat to the visual viewport while the keyboard is up', () => {
        expect(viewportFrame(844, 508, 0)).toEqual({ keyboardOpen: true, top: 0, height: 508 });
    });

    it('follows the viewport when iOS has scrolled it to show the field', () => {
        expect(viewportFrame(844, 508.4, 59.6)).toEqual({ keyboardOpen: true, top: 60, height: 508 });
    });

    it.each([
        ['no keyboard', 844, 844, 0],
        ['a toolbar taking a little height', 844, 790, 0],
        ['iOS 26 leaving a stale offset after the keyboard closes', 844, 844, 48],
    ])('lets the chat fill the screen with %s', (_case, windowHeight, visualHeight, offsetTop) => {
        expect(viewportFrame(windowHeight, visualHeight, offsetTop)).toEqual({ keyboardOpen: false, top: 0, height: null });
    });
});

describe('composerHeight', () => {
    const metrics = { lineHeight: 22, padding: 18 };

    it.each([
        ['one line of text opens at three lines', 40, 84, false],
        ['four lines grow the field', 106, 106, false],
        ['six lines is as tall as it gets', 150, 150, false],
        ['more than six scrolls', 216, 150, true],
    ])('%s', (_case, contentHeight, height, scrolls) => {
        expect(composerHeight(contentHeight, metrics)).toEqual({ height, scrolls });
    });
});

describe('pickerMaxHeight', () => {
    it.each([
        ['fills the space above the input, less a margin', 600, 0, undefined, 588],
        ['stops at the top of the visible viewport when iOS has scrolled it for the keyboard', 420, 60, undefined, 348],
        ['takes a margin of its own', 600, 0, 20, 580],
        ['rounds to whole pixels', 600.6, 59.2, undefined, 529],
        ['is never negative when the input is near the top', 50, 60, undefined, 0],
    ])('%s', (_case, composerTop, visibleTop, margin, height) => {
        expect(pickerMaxHeight(composerTop, visibleTop, margin)).toBe(height);
    });
});
