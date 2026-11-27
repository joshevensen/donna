import { type RefObject, useEffect } from 'react';
import { viewportFrame } from '../lib/viewport';

/**
 * Pins the element to the visual viewport while the on-screen keyboard is
 * up, so the input sits right above the keys. It writes straight to the
 * element instead of going through React state: this runs on every frame of
 * the keyboard's animation.
 */
export function useKeyboardViewport(ref: RefObject<HTMLElement | null>): void {
    useEffect(() => {
        const viewport = window.visualViewport;
        const element = ref.current;

        if (!viewport || !element) {
            return;
        }

        const apply = () => {
            const frame = viewportFrame(window.innerHeight, viewport.height, viewport.offsetTop);

            element.dataset.keyboard = frame.keyboardOpen ? 'open' : 'closed';
            element.style.top = frame.keyboardOpen ? `${frame.top}px` : '';
            element.style.height = frame.height === null ? '' : `${frame.height}px`;
        };

        viewport.addEventListener('resize', apply);
        viewport.addEventListener('scroll', apply);
        apply();

        return () => {
            viewport.removeEventListener('resize', apply);
            viewport.removeEventListener('scroll', apply);
        };
    }, [ref]);
}
