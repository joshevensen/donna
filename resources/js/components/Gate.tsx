import type { ReactNode } from 'react';
import { AlertIcon } from './icons';

/**
 * The frame for every screen outside the chat: the icon, centered on
 * Canvas, with a few words and at most a couple of buttons under it.
 */
export function Gate({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="gate__scroll">
            <main className="gate">
                <img className="gate__icon" src="/favicon.svg" alt="" width={96} height={96} />
                <h1 className="gate__title">{title}</h1>
                {children}
            </main>
        </div>
    );
}

/** Errors are Error red with an icon and words, never color alone. */
export function GateError({ children }: { children: ReactNode }) {
    return (
        <p className="gate__error" role="alert">
            <AlertIcon />
            <span>{children}</span>
        </p>
    );
}
