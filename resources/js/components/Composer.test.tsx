import { render, screen, within } from '@testing-library/react';
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
        render(<Composer onSend={onSend} costs={null} />);

        expect(screen.getByRole('button', { name: 'Send' })).toHaveProperty('disabled', true);

        await user.type(screen.getByLabelText('Message Donna'), '   {Enter}');

        expect(screen.getByRole('button', { name: 'Send' })).toHaveProperty('disabled', true);
        expect(onSend).not.toHaveBeenCalled();
    });

    it('sends on Enter with a mouse and keyboard, and Shift+Enter is a new line', async () => {
        const user = userEvent.setup();
        const onSend = vi.fn();
        render(<Composer onSend={onSend} costs={null} />);

        await user.type(screen.getByLabelText('Message Donna'), 'First line{Shift>}{Enter}{/Shift}second line{Enter}');

        expect(onSend).toHaveBeenCalledExactlyOnceWith('First line\nsecond line');
        expect(screen.getByLabelText('Message Donna')).toHaveProperty('value', '');
    });

    it('makes Enter a new line on a phone, where the button sends', async () => {
        onAPhone();
        const user = userEvent.setup();
        const onSend = vi.fn();
        render(<Composer onSend={onSend} costs={null} />);

        await user.type(screen.getByLabelText('Message Donna'), 'One{Enter}two');

        expect(onSend).not.toHaveBeenCalled();

        await user.click(screen.getByRole('button', { name: 'Send' }));

        expect(onSend).toHaveBeenCalledExactlyOnceWith('One\ntwo');
    });

    it('keeps the draft across the app being closed, and clears it once sent', async () => {
        const user = userEvent.setup();
        const first = render(<Composer onSend={() => {}} costs={null} />);

        await user.type(screen.getByLabelText('Message Donna'), 'Half a thought');
        first.unmount();

        render(<Composer onSend={() => {}} costs={null} />);

        expect(screen.getByLabelText('Message Donna')).toHaveProperty('value', 'Half a thought');

        await user.type(screen.getByLabelText('Message Donna'), '{Enter}');

        expect(storage.draft()).toBe('');
    });

    it('is a slim bar until it is focused or holds text, without swapping the field for another', async () => {
        const user = userEvent.setup();
        const { container } = render(<Composer onSend={() => {}} costs={null} />);
        const field = screen.getByLabelText('Message Donna');

        expect(container.querySelector('.composer__field')?.getAttribute('data-open')).toBe('false');

        await user.click(field);

        expect(container.querySelector('.composer__field')?.getAttribute('data-open')).toBe('true');
        expect(screen.getByLabelText('Message Donna')).toBe(field);
    });

    it('shows what Donna has cost today and this cycle once the server has said', () => {
        const { rerender } = render(<Composer onSend={vi.fn()} costs={null} />);

        expect(screen.queryByText(/Cycle/)).toBeNull();

        rerender(<Composer onSend={vi.fn()} costs={{ today: '0.42', cycle: '12.30' }} />);

        expect(screen.getByText('Today $0.42 · Cycle $12.30')).toBeTruthy();
    });
});

describe('the command picker', () => {
    const commands = () => screen.queryByRole('list', { name: 'Commands' });

    it('opens from / with every command in alphabetical order, whatever is typed', async () => {
        const user = userEvent.setup();
        render(<Composer onSend={vi.fn()} costs={null} />);

        await user.type(screen.getByLabelText('Message Donna'), '/pe');
        await user.click(screen.getByRole('button', { name: 'Commands' }));

        const list = screen.getByRole('list', { name: 'Commands' });
        expect(within(list).getAllByText(/^\/\w+$/).map((name) => name.textContent)).toEqual(['/cost', '/people', '/projects', '/settings']);
        expect(within(list).getByText('Your projects and where they stand')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Commands' }).getAttribute('aria-expanded')).toBe('true');
    });

    it('keeps the focus in the field when / is tapped, so the keyboard stays up', async () => {
        const user = userEvent.setup();
        render(<Composer onSend={vi.fn()} costs={null} />);
        const field = screen.getByLabelText('Message Donna');

        await user.click(field);
        await user.click(screen.getByRole('button', { name: 'Commands' }));

        expect(commands()).not.toBeNull();
        expect(document.activeElement).toBe(field);
    });

    it('puts the tapped command in the field in place of what was there, closes, and does not send', async () => {
        const user = userEvent.setup();
        const onSend = vi.fn();
        render(<Composer onSend={onSend} costs={null} />);
        const field = screen.getByLabelText('Message Donna');

        await user.type(field, 'half a thought');
        await user.click(screen.getByRole('button', { name: 'Commands' }));
        await user.click(within(screen.getByRole('list', { name: 'Commands' })).getByText('/projects'));

        expect(screen.getByLabelText('Message Donna')).toHaveProperty('value', '/projects');
        expect(commands()).toBeNull();
        expect(document.activeElement).toBe(screen.getByLabelText('Message Donna'));
        expect(onSend).not.toHaveBeenCalled();
    });

    it.each([
        ['Escape', async (user: ReturnType<typeof userEvent.setup>) => user.keyboard('{Escape}')],
        ['tapping / again', async (user: ReturnType<typeof userEvent.setup>) => user.click(screen.getByRole('button', { name: 'Commands' }))],
        ['tapping outside', async (user: ReturnType<typeof userEvent.setup>) => user.click(document.body)],
    ])('closes on %s without changing the draft', async (_case, close) => {
        const user = userEvent.setup();
        const onSend = vi.fn();
        render(<Composer onSend={onSend} costs={null} />);

        await user.type(screen.getByLabelText('Message Donna'), 'keep me');
        await user.click(screen.getByRole('button', { name: 'Commands' }));
        expect(commands()).not.toBeNull();

        await close(user);

        expect(commands()).toBeNull();
        expect(screen.getByRole('button', { name: 'Commands' }).getAttribute('aria-expanded')).toBe('false');
        expect(screen.getByLabelText('Message Donna')).toHaveProperty('value', 'keep me');
        expect(onSend).not.toHaveBeenCalled();
    });
});
