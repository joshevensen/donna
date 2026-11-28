import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import type { AppActionType } from '../api/types';
import type { PushResult } from '../lib/push';
import { SettingsSheet } from './SettingsSheet';

function sheet(subscribed: boolean | null, run: (type: AppActionType) => Promise<PushResult>) {
    const onClose = vi.fn();

    render(<SettingsSheet run={run} subscribed={subscribed} onClose={onClose} />);

    return { onClose };
}

describe('SettingsSheet', () => {
    it.each([
        [false, 'enable_notifications', { ok: true, message: 'Notifications are on for this device.' }],
        [true, 'disable_notifications', { ok: true, message: 'Notifications are off for this device.' }],
        [false, 'enable_notifications', { ok: false, message: 'Notifications are blocked for Donna.' }],
    ] as const)('the switch, with notifications %s, runs %s and shows what happened', async (subscribed, action, result) => {
        const user = userEvent.setup();
        const run = vi.fn().mockResolvedValue(result);
        sheet(subscribed, run);

        await user.click(screen.getByRole('switch', { name: 'Notifications' }));

        expect(run).toHaveBeenCalledWith(action);
        expect(screen.getByRole('status')).toHaveProperty('textContent', result.message);
        expect(screen.getByRole('status').dataset.error).toBe(String(!result.ok));
    });

    it.each([
        ['Sign out', 'sign_out'],
        ['Sign out everywhere', 'sign_out_everywhere'],
    ])('%s says so when it fails', async (label, action) => {
        const user = userEvent.setup();
        const run = vi.fn().mockRejectedValue(new Error('offline'));
        sheet(true, run);

        await user.click(screen.getByRole('button', { name: label }));

        expect(run).toHaveBeenCalledWith(action);
        expect(await screen.findByText("I couldn't sign you out. Check your connection and try again.")).toBeTruthy();
    });

    it('closes on Done and on Escape', async () => {
        const user = userEvent.setup();
        const { onClose } = sheet(true, vi.fn());

        await user.click(screen.getByRole('button', { name: 'Done' }));
        await user.keyboard('{Escape}');

        expect(onClose).toHaveBeenCalledTimes(2);
    });
});
