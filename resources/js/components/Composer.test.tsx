import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { storage } from '../lib/storage';
import { Composer } from './Composer';

function onAPhone() {
    window.matchMedia = ((query: string) => ({ matches: query === '(pointer: coarse)', media: query })) as unknown as typeof window.matchMedia;
}

describe('Composer', () => {
    it('cannot send while the field is empty or only spaces', async () => {
        const user = userEvent.setup();
        const onSend = vi.fn();
        render(<Composer onSend={onSend} />);

        expect(screen.getByRole('button', { name: 'Send' })).toHaveProperty('disabled', true);

        await user.type(screen.getByLabelText('Message Donna'), '   {Enter}');

        expect(screen.getByRole('button', { name: 'Send' })).toHaveProperty('disabled', true);
        expect(onSend).not.toHaveBeenCalled();
    });

    it('sends on Enter with a mouse and keyboard, and Shift+Enter is a new line', async () => {
        const user = userEvent.setup();
        const onSend = vi.fn();
        render(<Composer onSend={onSend} />);

        await user.type(screen.getByLabelText('Message Donna'), 'First line{Shift>}{Enter}{/Shift}second line{Enter}');

        expect(onSend).toHaveBeenCalledExactlyOnceWith('First line\nsecond line');
        expect(screen.getByLabelText('Message Donna')).toHaveProperty('value', '');
    });

    it('makes Enter a new line on a phone, where the button sends', async () => {
        onAPhone();
        const user = userEvent.setup();
        const onSend = vi.fn();
        render(<Composer onSend={onSend} />);

        await user.type(screen.getByLabelText('Message Donna'), 'One{Enter}two');

        expect(onSend).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Send' }));

        expect(onSend).toHaveBeenCalledExactlyOnceWith('One\ntwo');
    });

    it('keeps the draft across the app being closed, and clears it once sent', async () => {
        const user = userEvent.setup();
        const first = render(<Composer onSend={() => {}} />);

        await user.type(screen.getByLabelText('Message Donna'), 'Half a thought');
        first.unmount();

        render(<Composer onSend={() => {}} />);

        expect(screen.getByLabelText('Message Donna')).toHaveProperty('value', 'Half a thought');

        await user.type(screen.getByLabelText('Message Donna'), '{Enter}');

        expect(storage.draft()).toBe('');
    });

    it('is a slim bar until it is focused or holds text, without swapping the field for another', async () => {
        const user = userEvent.setup();
        const { container } = render(<Composer onSend={() => {}} />);
        const field = screen.getByLabelText('Message Donna');

        expect(container.querySelector('.composer__field')?.getAttribute('data-open')).toBe('false');

        await user.click(field);

        expect(container.querySelector('.composer__field')?.getAttribute('data-open')).toBe('true');
        expect(screen.getByLabelText('Message Donna')).toBe(field);
    });
});
