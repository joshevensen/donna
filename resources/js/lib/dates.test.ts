import { describe, expect, it } from 'vitest';
import { dateLabel, timeLabel } from './dates';

// Josh's timezone decides the day, not the device's and not UTC.
const chicago = 'America/Chicago';
const now = new Date('2026-11-27T18:00:00Z'); // Friday, noon in Chicago

describe('dateLabel', () => {
    it.each([
        ['earlier the same day', '2026-11-27T14:00:00Z', 'Today'],
        ['just after midnight UTC, which is still yesterday evening in Chicago', '2026-11-27T03:00:00Z', 'Yesterday'],
        ['the day before', '2026-11-26T20:00:00Z', 'Yesterday'],
        ['earlier in the week', '2026-11-24T20:00:00Z', 'Tuesday, Nov 24'],
        ['six days back, the last one that gets a weekday', '2026-11-21T20:00:00Z', 'Saturday, Nov 21'],
        ['a week back', '2026-11-20T20:00:00Z', 'Nov 20'],
        ['another year', '2025-12-30T20:00:00Z', 'Dec 30, 2025'],
        ['a clock that runs ahead', '2026-11-28T20:00:00Z', 'Today'],
    ])('labels %s', (_case, moment, label) => {
        expect(dateLabel(moment, chicago, now)).toBe(label);
    });

    it('puts the same moment on different days in different timezones', () => {
        const moment = '2026-11-27T03:00:00Z';

        expect(dateLabel(moment, 'Europe/London', now)).toBe('Today');
        expect(dateLabel(moment, chicago, now)).toBe('Yesterday');
    });
});

describe('timeLabel', () => {
    it('shows the time in the given timezone', () => {
        expect(timeLabel('2026-11-26T22:12:00Z', chicago)).toBe('4:12 PM');
    });
});
