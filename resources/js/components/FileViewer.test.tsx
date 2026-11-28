import { act, render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, describe, expect, it, vi } from 'vitest';
import { api, ApiError } from '../api/client';
import type { FileDetail } from '../api/types';
import { storage } from '../lib/storage';
import { FileViewer } from './FileViewer';

vi.mock('../api/client', async (original) => ({
    ...(await original<typeof import('../api/client')>()),
    api: { file: vi.fn(), saveFile: vi.fn(), updateFile: vi.fn() },
}));

function file(overrides: Partial<FileDetail> = {}): FileDetail {
    return {
        id: 7,
        name: 'launch-plan.md',
        kind: 'markdown',
        mime: 'text/markdown',
        size_bytes: 3072,
        kept: true,
        deleted: false,
        extraction: 'ready',
        extraction_error: null,
        meta: { lines: 4 },
        version: 4,
        last_author: 'donna',
        editable: true,
        created_at: '2026-11-27T20:14:00Z',
        updated_at: '2026-11-27T21:05:00Z',
        body: '## Fibermade launch plan\n\nSix dyers live by the end of the festival.',
        truncated: false,
        table: null,
        ...overrides,
    };
}

function open(shown: FileDetail, props: Partial<Parameters<typeof FileViewer>[0]> = {}) {
    vi.mocked(api.file).mockResolvedValue(shown);

    const onFile = vi.fn();
    const onClose = vi.fn();
    const view = render(<FileViewer id={shown.id} busy={false} timeZone="America/Chicago" onClose={onClose} onFile={onFile} {...props} />);

    return { ...view, onFile, onClose };
}

afterEach(() => {
    vi.useRealTimers();
});

describe('reading a file', () => {
    it('renders a markdown file, offers Edit, and shows that it is kept', async () => {
        const { onFile } = open(file());

        expect(await screen.findByRole('heading', { name: 'Fibermade launch plan' })).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Edit' })).toBeTruthy();
        expect(screen.getByRole('switch', { name: 'Kept. Donna finds it when it comes up' }).getAttribute('aria-checked')).toBe('true');
        expect(onFile).toHaveBeenCalledWith(expect.objectContaining({ id: 7, kept: true }));
    });

    it('lists who and what the file is linked to in its details', async () => {
        const user = userEvent.setup();
        open(file({ subjects: [{ type: 'person', name: 'Anita' }, { type: 'project', name: 'Fibermade' }] }));

        await user.click(await screen.findByRole('button', { name: 'Details' }));

        expect(screen.getByText('Anita, Fibermade')).toBeTruthy();
    });

    it('shows a CSV as a read-only table and says how much of it is shown', async () => {
        open(
            file({
                name: 'stripe-payouts-nov.csv',
                kind: 'csv',
                kept: false,
                editable: false,
                version: 1,
                body: null,
                meta: { rows: 1284 },
                table: { columns: ['Date', 'Payout', 'Amount'], rows: [['Nov 3', 'po_1QxA', '$812.40']], total_rows: 1284 },
            }),
        );

        const table = await screen.findByRole('table');

        expect(within(table).getByRole('columnheader', { name: 'Amount' }).getAttribute('data-numeric')).toBe('true');
        expect(within(table).getByRole('cell', { name: '$812.40' })).toBeTruthy();
        expect(screen.getByText('Showing 1 of 1,284 rows. Scroll sideways for more columns. Ask Donna for totals.')).toBeTruthy();
        expect(screen.getByText('Read-only')).toBeTruthy();
        expect(screen.queryByRole('button', { name: 'Edit' })).toBeNull();
        expect(screen.getByRole('switch', { name: 'Not kept. Only for this conversation' }).getAttribute('aria-checked')).toBe('false');
    });

    it('shows a PDF as the text Donna reads, page by page, with the original one tap away', async () => {
        open(file({ name: 'houston-logistics.pdf', kind: 'pdf', editable: false, version: 1, meta: { pages: 2, has_text: true }, body: 'Load-in: Thursday 7:00 AM.\fTeardown begins Saturday.\f' }));

        expect(await screen.findByText('Load-in: Thursday 7:00 AM.')).toBeTruthy();
        expect(screen.getByRole('region', { name: 'Page 2' }).textContent).toContain('Teardown begins Saturday.');
        expect(screen.queryByRole('region', { name: 'Page 3' })).toBeNull();
        expect(screen.getByText(/Shown as the text Donna reads, 2 pages\./)).toBeTruthy();

        const original = screen.getByRole('link', { name: 'Open original' });

        expect(original.getAttribute('href')).toBe('/api/v1/files/7/content');
        expect(original.getAttribute('target')).toBe('_blank');
    });

    // A scan has no text to show. Saying so beats an empty page that looks like a broken file.
    it('says a scanned PDF has no text instead of showing it as empty', async () => {
        open(file({ name: 'scan.pdf', kind: 'pdf', editable: false, version: 1, meta: { pages: 12, has_text: false }, body: '' }));

        expect(await screen.findByText(/This PDF has no text to show \(it looks like a scan\)/)).toBeTruthy();
    });

    it('shows an image, with no keep switch: images are never remembered', async () => {
        open(file({ name: 'screenshot.png', kind: 'image', kept: false, editable: false, version: null, last_author: null, body: null, meta: { width: 800, height: 600 } }));

        const image = await screen.findByRole('img', { name: 'screenshot.png' });

        expect(image.getAttribute('src')).toBe('/api/v1/files/7/content?variant=preview');
        expect(screen.queryByRole('switch')).toBeNull();
        expect(screen.getByText(/Images aren’t remembered/)).toBeTruthy();
    });

    it('says so when Donna could not read the file', async () => {
        open(file({ name: 'broken.pdf', kind: 'pdf', editable: false, extraction: 'failed', extraction_error: 'the PDF is password-protected', body: null }));

        expect((await screen.findByRole('alert')).textContent).toContain('Donna couldn’t read this file: the PDF is password-protected');
    });

    it('says a deleted file is deleted, and how to get it back', async () => {
        vi.mocked(api.file).mockRejectedValue(new ApiError(404, 'Not found'));

        const onClose = vi.fn();
        render(<FileViewer id={7} busy={false} timeZone="America/Chicago" onClose={onClose} onFile={vi.fn()} />);

        expect(await screen.findByText('This file was deleted. Ask Donna to bring it back.')).toBeTruthy();
        expect(screen.queryByRole('switch')).toBeNull();
    });

    it('lists when it arrived, its size, who edited it last and its version under Details', async () => {
        const user = userEvent.setup();
        open(file({ version: 1, last_author: 'josh', created_at: new Date().toISOString() }));

        await user.click(await screen.findByRole('button', { name: 'Details' }));

        const details = screen.getByRole('button', { name: 'Details' }).parentElement!;

        expect(within(details).getByText('3 KB, 4 lines')).toBeTruthy();
        expect(within(details).getByText('You')).toBeTruthy();
        expect(within(details).getByText('1 (original)')).toBeTruthy();
        expect(within(details).getByRole('link', { name: 'Download' }).getAttribute('href')).toBe('/api/v1/files/7/content');
    });
});

describe('the keep switch', () => {
    it('stops keeping a file at once and tells the thread, so its cards lose the badge', async () => {
        const user = userEvent.setup();
        const { onFile } = open(file());

        vi.mocked(api.updateFile).mockResolvedValue(file({ kept: false }));

        await user.click(await screen.findByRole('switch'));

        expect(api.updateFile).toHaveBeenCalledWith(7, { kept: false });
        expect(screen.getByRole('switch', { name: 'Not kept. Only for this conversation' }).getAttribute('aria-checked')).toBe('false');
        await waitFor(() => expect(onFile).toHaveBeenLastCalledWith(expect.objectContaining({ kept: false })));
    });

    it('goes back, with a reason, when the change did not save', async () => {
        const user = userEvent.setup();
        open(file());

        vi.mocked(api.updateFile).mockRejectedValue(new ApiError(500, 'Oops'));

        await user.click(await screen.findByRole('switch'));

        expect((await screen.findByRole('alert')).textContent).toContain("That didn't save. Try again.");
        expect(screen.getByRole('switch').getAttribute('aria-checked')).toBe('true');
    });
});

describe('editing a markdown file', () => {
    it('saves what Josh typed against the version he started from, and goes back to reading', async () => {
        const user = userEvent.setup();
        open(file());

        vi.mocked(api.saveFile).mockResolvedValue(file({ version: 5, last_author: 'josh', body: '## Fibermade launch plan\n\nEight dyers.' }));

        await user.click(await screen.findByRole('button', { name: 'Edit' }));

        const editor = screen.getByLabelText('File text');

        expect(screen.getByText('Editing')).toBeTruthy();
        expect(screen.getByText('Based on version 4.')).toBeTruthy();
        expect(screen.getByRole('button', { name: 'Save' })).toHaveProperty('disabled', true);

        await user.clear(editor);
        await user.type(editor, '## Fibermade launch plan{Enter}{Enter}Eight dyers.');
        await user.click(screen.getByRole('button', { name: 'Save' }));

        expect(api.saveFile).toHaveBeenCalledExactlyOnceWith(7, '## Fibermade launch plan\n\nEight dyers.', 4);
        expect(await screen.findByText('Eight dyers.')).toBeTruthy();
        expect(screen.queryByLabelText('File text')).toBeNull();
        expect(storage.editDraft(7)).toBeNull();
    });

    it('shows the conflict banner when Donna changed the file meanwhile, and "Keep mine" saves on top of hers', async () => {
        const user = userEvent.setup();
        open(file());

        const hers = file({ version: 5, last_author: 'donna', body: '## Fibermade launch plan\n\nHers.' });

        vi.mocked(api.saveFile)
            .mockRejectedValueOnce(new ApiError(409, 'The file changed.', {}, hers))
            .mockResolvedValueOnce(file({ version: 6, last_author: 'josh', body: 'Mine.' }));

        await user.click(await screen.findByRole('button', { name: 'Edit' }));
        await user.clear(screen.getByLabelText('File text'));
        await user.type(screen.getByLabelText('File text'), 'Mine.');
        await user.click(screen.getByRole('button', { name: 'Save' }));

        const banner = await screen.findByRole('alert');

        expect(banner.textContent).toContain("Donna changed this file while you were editing, so it wasn't saved.");
        expect(screen.getByLabelText('File text')).toHaveProperty('value', 'Mine.');
        expect(screen.getByText('Based on version 4. Your text is kept here until you choose.')).toBeTruthy();

        await user.click(within(banner).getByRole('button', { name: 'Keep mine' }));

        expect(api.saveFile).toHaveBeenLastCalledWith(7, 'Mine.', 5);
        expect(await screen.findByText('Mine.')).toBeTruthy();
        expect(screen.queryByRole('alert')).toBeNull();
    });

    it('"Take Donna’s" drops his text and shows hers', async () => {
        const user = userEvent.setup();
        open(file());

        vi.mocked(api.saveFile).mockRejectedValueOnce(new ApiError(409, 'The file changed.', {}, file({ version: 5, last_author: 'donna', body: 'Hers, tightened.' })));

        await user.click(await screen.findByRole('button', { name: 'Edit' }));
        await user.type(screen.getByLabelText('File text'), ' Mine.');
        await user.click(screen.getByRole('button', { name: 'Save' }));
        await user.click(await screen.findByRole('button', { name: "Take Donna's" }));

        expect(screen.getByText('Hers, tightened.')).toBeTruthy();
        expect(screen.queryByLabelText('File text')).toBeNull();
        expect(api.saveFile).toHaveBeenCalledOnce();
        expect(storage.editDraft(7)).toBeNull();
    });

    it('keeps his text and says why when a save fails for another reason', async () => {
        const user = userEvent.setup();
        open(file());

        vi.mocked(api.saveFile).mockRejectedValueOnce(new ApiError(500, 'Oops'));

        await user.click(await screen.findByRole('button', { name: 'Edit' }));
        await user.type(screen.getByLabelText('File text'), ' More.');
        await user.click(screen.getByRole('button', { name: 'Save' }));

        expect((await screen.findByRole('alert')).textContent).toContain("Couldn't save. Try again. Your text is still here.");
        expect((screen.getByLabelText('File text') as HTMLTextAreaElement).value).toContain('More.');
    });

    // iOS closes Home Screen apps without warning; half an edit must not go with it.
    it('brings unsaved text back when edit mode is opened again, and asks before discarding it', async () => {
        const user = userEvent.setup();
        const first = open(file());

        await user.click(await screen.findByRole('button', { name: 'Edit' }));
        await user.type(screen.getByLabelText('File text'), ' Unsaved thought.');
        first.unmount();

        open(file());
        await user.click(await screen.findByRole('button', { name: 'Edit' }));

        expect((screen.getByLabelText('File text') as HTMLTextAreaElement).value).toContain('Unsaved thought.');
        expect(screen.getByText('Based on version 4. Your unsaved text from before is back.')).toBeTruthy();

        await user.click(screen.getByRole('button', { name: 'Cancel' }));

        expect(screen.getByRole('alert').textContent).toContain('Discard your changes?');

        await user.click(screen.getByRole('button', { name: 'Discard' }));

        expect(screen.queryByLabelText('File text')).toBeNull();
        expect(storage.editDraft(7)).toBeNull();
    });
});

describe('while Donna is writing', () => {
    it('keeps the open file current, but never while Josh is editing it', async () => {
        vi.useFakeTimers({ shouldAdvanceTime: true });

        const user = userEvent.setup({ advanceTimers: vi.advanceTimersByTime });
        const { rerender, onFile, onClose } = open(file(), { busy: true });

        await screen.findByRole('button', { name: 'Edit' });

        vi.mocked(api.file).mockResolvedValue(file({ version: 5, body: 'Donna rewrote this.' }));
        await act(() => vi.advanceTimersByTimeAsync(2_000));

        expect(screen.getByText('Donna rewrote this.')).toBeTruthy();

        await user.click(screen.getByRole('button', { name: 'Edit' }));

        const calls = vi.mocked(api.file).mock.calls.length;

        await act(() => vi.advanceTimersByTimeAsync(6_000));

        expect(api.file).toHaveBeenCalledTimes(calls);

        // She finishes while he is still editing: nothing is fetched until he is reading again.
        rerender(<FileViewer id={7} busy={false} timeZone="America/Chicago" onClose={onClose} onFile={onFile} />);
        await act(() => vi.advanceTimersByTimeAsync(2_000));

        expect(api.file).toHaveBeenCalledTimes(calls);
    });
});
