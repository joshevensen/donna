/*
 | Dates are computed in Josh's timezone (from GET /me), not the device's, so
 | "Yesterday" means the same thing on his phone and his Mac wherever they are.
 */

/** The calendar day a moment falls on in the timezone, as YYYY-MM-DD. */
export function dayKey(moment: string | Date, timeZone: string): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone, year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date(moment));
}

function daysBetween(earlierKey: string, laterKey: string): number {
    return Math.round((Date.parse(`${laterKey}T00:00:00Z`) - Date.parse(`${earlierKey}T00:00:00Z`)) / 86_400_000);
}

/**
 * The divider above a day's messages: "Today", "Yesterday", the weekday and
 * date within the last week, then just the date. Never a time.
 */
export function dateLabel(moment: string | Date, timeZone: string, now: Date = new Date()): string {
    const date = new Date(moment);
    const age = daysBetween(dayKey(date, timeZone), dayKey(now, timeZone));

    if (age <= 0) {
        return 'Today';
    }

    if (age === 1) {
        return 'Yesterday';
    }

    if (age < 7) {
        return new Intl.DateTimeFormat('en-US', { timeZone, weekday: 'long', month: 'short', day: 'numeric' }).format(date);
    }

    const sameYear = dayKey(date, timeZone).slice(0, 4) === dayKey(now, timeZone).slice(0, 4);

    return new Intl.DateTimeFormat('en-US', { timeZone, month: 'short', day: 'numeric', ...(sameYear ? {} : { year: 'numeric' }) }).format(date);
}

/** The time shown when a bubble is tapped, e.g. "4:12 PM". */
export function timeLabel(moment: string | Date, timeZone: string): string {
    return new Intl.DateTimeFormat('en-US', { timeZone, hour: 'numeric', minute: '2-digit' }).format(new Date(moment));
}
