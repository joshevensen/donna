import { act, render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { api, ApiError, UploadCancelled } from '../api/client';
import type { FileItem } from '../api/types';
import { storage } from '../lib/storage';
import { Composer } from './Composer';

vi.mock('../api/client', async (original) => ({
    ...(await original<typeof import('../api/client')>()),
    api: { uploadFile: vi.fn() },
}));

/** Stand in for the next upload: the test decides how far it gets and how it ends. */
function nextUpload() {
    const upload = {
        progress: (_fraction: number) => {},
        finish: (_file: FileItem) => {},
        fail: (_error: unknown) => {},
        cancel: vi.fn(),
    };

    vi.mocked(api.uploadFile).mockImplementationOnce((_file, onProgress) => {
        const done = new Promise<FileItem>((resolve, reject) => {
            upload.finish = resolve;
            upload.fail = reject;
        });

        upload.progress = onProgress;
        upload.cancel.mockImplementation(() => upload.fail(new UploadCancelled()));

        return { done, cancel: upload.cancel };
    });

    return upload;
}

function uploadedFile(overrides: Partial<FileItem> = {}): FileItem {
    return {
        id: 7,
        name: 'launch-plan.md',
        kind: 'markdown',
        mime: 'text/markdown',
        size_bytes: 3072,
        kept: false,
        deleted: false,
        extraction: 'ready',
        extraction_error: null,
        meta: { lines: 42 },
        version: 1,
        last_author: 'josh',
        editable: true,
        created_at: '2026-11-27T20:14:00Z',
        updated_at: '2026-11-27T20:14:00Z',
        ...overrides,
    };
}

const picker = (container: HTMLElement, which: 'file' | 'photo') => container.querySelector<HTMLInputElement>(`[data-picker="${which}"]`)!;

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

        expect(onSend).toHaveBeenCalledExactlyOnceWith('First line\nsecond line', []);
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

        expect(onSend).toHaveBeenCalledExactlyOnceWith('One\ntwo', []);
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

    it('opens a sheet from the plus to choose a file or a photo, and asks iOS for photos it can read', async () => {
        const user = userEvent.setup();
        const { container } = render(<Composer onSend={vi.fn()} costs={null} />);

        await user.click(screen.getByRole('button', { name: 'Attach a file' }));

        const sheet = screen.getByRole('dialog', { name: 'Attach' });

        expect(within(sheet).getByRole('button', { name: /Choose a file/ })).toBeTruthy();
        expect(within(sheet).getByRole('button', { name: 'Choose a photo' })).toBeTruthy();

        await user.click(within(sheet).getByRole('button', { name: 'Cancel' }));

        expect(screen.queryByRole('dialog', { name: 'Attach' })).toBeNull();

        // Asking for these types is what makes an iPhone hand over a JPEG instead of HEIC. Files are not narrowed at all.
        expect(picker(container, 'photo').accept).toBe('image/jpeg,image/png,image/webp,image/gif');
        expect(picker(container, 'file').accept).toBe('');
    });

    it('uploads a picked file as a chip, holds send until it is ready, and sends it with no text at all', async () => {
        const user = userEvent.setup();
        const onSend = vi.fn();
        const upload = nextUpload();
        const { container } = render(<Composer onSend={onSend} costs={null} />);

        await user.upload(picker(container, 'file'), new File(['# Plan'], 'launch-plan.md', { type: 'text/markdown' }));

        const chips = screen.getByRole('list', { name: 'Attachments' });

        expect(within(chips).getByText('launch-plan.md')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Send' })).toHaveProperty('disabled', true);
        expect(screen.getByRole('status').textContent).toBe('Send waits until the upload finishes.');

        act(() => upload.progress(0.5));

        expect(within(chips).getByText(/Uploading… 50%/)).toBeTruthy();

        await act(async () => upload.finish(uploadedFile()));

        expect(within(chips).getByText('3 KB')).toBeTruthy();
        expect(screen.queryByRole('status')).toBeNull();

        await user.click(screen.getByRole('button', { name: 'Send' }));

        expect(onSend).toHaveBeenCalledExactlyOnceWith('', [{ id: 7, name: 'launch-plan.md', kind: 'markdown', size_bytes: 3072, meta: { lines: 42 } }]);
        expect(screen.queryByRole('list', { name: 'Attachments' })).toBeNull();
    });

    it('says why a file was refused, and will not send until it is removed', async () => {
        const user = userEvent.setup();
        const onSend = vi.fn();
        const upload = nextUpload();
        const { container } = render(<Composer onSend={onSend} costs={null} />);

        await user.type(screen.getByLabelText('Message Donna'), 'Here is the price list');
        await user.upload(picker(container, 'file'), new File(['PK'], 'price-list.xlsx'));
        await act(async () => upload.fail(new ApiError(422, 'Refused', { file: ['Unsupported. Text, PDF and images only'] })));

        expect(screen.getByText('Unsupported. Text, PDF and images only')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Send' })).toHaveProperty('disabled', true);
        expect(screen.getByRole('status').textContent).toBe('Send waits until the refused file is removed.');

        await user.click(screen.getByRole('button', { name: 'Remove price-list.xlsx' }));
        await user.click(screen.getByRole('button', { name: 'Send' }));

        expect(onSend).toHaveBeenCalledExactlyOnceWith('Here is the price list', []);
    });

    it('stops an upload when its chip is removed', async () => {
        const user = userEvent.setup();
        const upload = nextUpload();
        const { container } = render(<Composer onSend={vi.fn()} costs={null} />);

        await user.upload(picker(container, 'file'), new File(['a,b'], 'stripe-payouts-nov.csv'));
        await user.click(screen.getByRole('button', { name: 'Cancel upload of stripe-payouts-nov.csv' }));

        expect(upload.cancel).toHaveBeenCalledOnce();
        expect(screen.queryByRole('list', { name: 'Attachments' })).toBeNull();
    });

    it('takes five files at most and says the rest were left out', async () => {
        const user = userEvent.setup();
        const { container } = render(<Composer onSend={vi.fn()} costs={null} />);

        for (let n = 0; n < 5; n += 1) {
            nextUpload();
        }

        await user.upload(
            picker(container, 'file'),
            ['a', 'b', 'c', 'd', 'e', 'f'].map((name) => new File(['x'], `${name}.txt`)),
        );

        expect(within(screen.getByRole('list', { name: 'Attachments' })).getAllByRole('listitem')).toHaveLength(5);
        expect(api.uploadFile).toHaveBeenCalledTimes(5);
        expect(screen.getByRole('status').textContent).toContain('Five files at most per message.');
    });

    it('keeps files that finished uploading with the draft when the app is closed', async () => {
        const user = userEvent.setup();
        const upload = nextUpload();
        const first = render(<Composer onSend={vi.fn()} costs={null} />);

        await user.upload(picker(first.container, 'file'), new File(['# Plan'], 'launch-plan.md'));
        await act(async () => upload.finish(uploadedFile()));
        first.unmount();

        render(<Composer onSend={vi.fn()} costs={null} />);

        expect(within(screen.getByRole('list', { name: 'Attachments' })).getByText('launch-plan.md')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Send' })).toHaveProperty('disabled', false);
    });
});
