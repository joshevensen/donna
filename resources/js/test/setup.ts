import { cleanup } from '@testing-library/react';
import { afterEach, beforeEach } from 'vitest';

beforeEach(() => {
    // jsdom has no media queries. Tests start as a Mac with a mouse; a test that wants a
    // phone replaces this.
    window.matchMedia = ((query: string) => ({ matches: false, media: query, addEventListener() {}, removeEventListener() {} })) as unknown as typeof window.matchMedia;
});

afterEach(() => {
    cleanup();
    localStorage.clear();
});
