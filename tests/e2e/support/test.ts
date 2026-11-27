import { test as base, expect } from '@playwright/test';
import { FakeDonna } from './fakeDonna';

/*
 | Every flow gets a fake Donna already answering the page's requests, and
 | fails if the browser reports a Content-Security-Policy violation: the
 | built app must run under the policy the server sends.
 */

export const test = base.extend<{ donna: FakeDonna }>({
    donna: async ({ page }, use) => {
        const donna = new FakeDonna();
        const violations: string[] = [];

        page.on('console', (message) => {
            if (message.text().includes('Content Security Policy')) {
                violations.push(message.text());
            }
        });

        await donna.install(page);
        await use(donna);

        expect(violations, 'the page broke its Content-Security-Policy').toEqual([]);
    },
});

export { expect };

/** A moment so many days ago, at the given time of day in Chicago, as the API would send it. */
export function daysAgo(days: number, time = '16:12'): string {
    const day = new Date(Date.now() - days * 86_400_000);
    const date = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Chicago' }).format(day);

    // Chicago is UTC-6 in winter and UTC-5 in summer; either lands on the same calendar day there.
    return new Date(`${date}T${time}:00-06:00`).toISOString();
}
