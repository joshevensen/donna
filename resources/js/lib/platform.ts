/*
 | What the app needs to know about the device it is on. Read at call time,
 | so tests can change it.
 */

/** iPhone or iPad. iPadOS reports itself as a Mac, with a touch screen. */
export function isIOS(): boolean {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
}

/** Running as the app installed to the Home Screen, not in a browser tab. */
export function isInstalled(): boolean {
    return (navigator as Navigator & { standalone?: boolean }).standalone === true || window.matchMedia('(display-mode: standalone)').matches;
}

/** A finger is the main pointer, so Enter is a new line and the send button sends. */
export function isTouchFirst(): boolean {
    return window.matchMedia('(pointer: coarse)').matches;
}

export function uuid(): string {
    if (typeof crypto.randomUUID === 'function') {
        return crypto.randomUUID();
    }

    const bytes = crypto.getRandomValues(new Uint8Array(16));

    bytes[6] = (bytes[6]! & 0x0f) | 0x40;
    bytes[8] = (bytes[8]! & 0x3f) | 0x80;

    const hex = [...bytes].map((byte) => byte.toString(16).padStart(2, '0')).join('');

    return `${hex.slice(0, 8)}-${hex.slice(8, 12)}-${hex.slice(12, 16)}-${hex.slice(16, 20)}-${hex.slice(20)}`;
}
