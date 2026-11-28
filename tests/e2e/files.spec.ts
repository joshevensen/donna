import type { Page } from '@playwright/test';
import { daysAgo, expect, test } from './support/test';

const PLAN = '## Fibermade launch plan\n\nFibermade Co launches to other dyers on January 25.\n\n### Pricing\n\nFlat monthly plan for launch.';

/** Pick a file the way Josh does: the plus, the sheet, then the system's picker. */
async function attach(page: Page, name: string, content: string, which: RegExp = /Choose a file/): Promise<void> {
    await page.getByRole('button', { name: 'Attach a file' }).click();

    const picker = page.waitForEvent('filechooser');

    await page.getByRole('dialog', { name: 'Attach' }).getByRole('button', { name: which }).click();
    await (await picker).setFiles({ name, mimeType: 'application/octet-stream', buffer: Buffer.from(content) });
}

test('attach a file and send it; her reply carries what she did to it', async ({ page, donna }) => {
    await page.goto('/');
    await attach(page, 'launch-plan.md', PLAN);

    // The chip is ready once the upload is done, and the field stays open for the message.
    const chips = page.getByRole('list', { name: 'Attachments' });

    await expect(chips.getByText('launch-plan.md')).toBeVisible();
    await expect(chips.getByText(/^\d+ (B|KB)$/)).toBeVisible();

    const uploaded = donna.files[0]!;

    donna.replies.push({ bubbles: ['Done, 1 of them.'], files: [{ id: uploaded.id, role: 'edited', note: "Replaced 1 match of 'Fibermade Co'" }] });

    await page.getByLabel('Message Donna').fill('Replace Fibermade Co with Fibermade.');
    await page.getByRole('button', { name: 'Send' }).click();

    // His bubble carries the file as a card, above what he said.
    await expect(page.getByRole('button', { name: 'Open launch-plan.md', exact: true })).toBeVisible();
    await expect(page.getByText('Replace Fibermade Co with Fibermade.')).toBeVisible();
    await expect(chips).toBeHidden();

    // Her reply carries the same file, with what changed.
    await expect(page.getByText('Done, 1 of them.')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Open launch-plan.md, edited' })).toContainText("Edited · Replaced 1 match of 'Fibermade Co'");

    expect(donna.sends).toEqual([{ key: expect.any(String), body: 'Replace Fibermade Co with Fibermade.', fileIds: [uploaded.id] }]);
});

test('a photo can be sent with no words at all, and still shows in the thread', async ({ page, donna }) => {
    await page.goto('/');
    await attach(page, 'booth.jpg', 'not really a jpeg', /Choose a photo/);

    await expect(page.getByRole('list', { name: 'Attachments' }).getByText('booth.jpg')).toBeVisible();

    const send = page.getByRole('button', { name: 'Send' });

    await expect(send).toBeEnabled();
    await send.click();

    // A message of only files has no text; it still gets a bubble, holding its card.
    await expect(page.getByRole('button', { name: 'Open booth.jpg' })).toBeVisible();
    await expect(page.getByText('Got it.')).toBeVisible();

    expect(donna.sends).toEqual([{ key: expect.any(String), body: '', fileIds: [donna.files[0]!.id] }]);

    // And it is still there after a reload, drawn from what the API returns.
    await page.reload();
    await expect(page.getByRole('button', { name: 'Open booth.jpg' })).toBeVisible();
});

test('a file Donna cannot read says why on its chip, and nothing sends until it is removed', async ({ page, donna }) => {
    await page.goto('/');
    await page.getByLabel('Message Donna').fill('Here is the price list.');
    await attach(page, 'price-list.xlsx', 'PK');

    const chips = page.getByRole('list', { name: 'Attachments' });

    await expect(chips.getByText('Unsupported. Text, PDF and images only')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Send' })).toBeDisabled();
    await expect(page.getByText('Send waits until the refused file is removed.')).toBeVisible();

    await page.getByRole('button', { name: 'Remove price-list.xlsx' }).click();

    await expect(chips).toBeHidden();
    await page.getByRole('button', { name: 'Send' }).click();
    await expect(page.getByText('Got it.')).toBeVisible();

    expect(donna.sends).toEqual([{ key: expect.any(String), body: 'Here is the price list.', fileIds: [] }]);
});

test('open a file from its card, edit it by hand, and keep it', async ({ page, donna, isMobile }) => {
    const plan = donna.file('launch-plan.md', PLAN);

    donna.said('josh', "Here's the launch plan.", daysAgo(0, '09:41'), { files: [donna.attachment(plan)] });

    await page.goto('/');
    await page.getByRole('button', { name: 'Open launch-plan.md' }).click();

    const viewer = page.getByRole('region', { name: 'launch-plan.md' });

    await expect(viewer.getByRole('heading', { name: 'Fibermade launch plan' })).toBeVisible();

    const chat = (await page.locator('.chat').boundingBox())!;
    const file = (await viewer.boundingBox())!;
    const screen = page.viewportSize()!;

    if (isMobile) {
        // On the phone the file is a sheet over the whole chat.
        expect(file).toMatchObject({ x: 0, y: 0, width: screen.width, height: screen.height });
    } else {
        // On the Mac the chat keeps its column on the left and the file takes the right half.
        expect(Math.abs(file.x - screen.width / 2)).toBeLessThanOrEqual(2);
        expect(Math.abs(file.width - screen.width / 2)).toBeLessThanOrEqual(2);
        expect(chat.width).toBeLessThanOrEqual(600);
        expect(chat.x + chat.width).toBeLessThanOrEqual(file.x);
    }

    // Edit mode looks different from reading, and saves a new version.
    await viewer.getByRole('button', { name: 'Edit' }).click();

    const editing = page.getByRole('region', { name: 'Editing launch-plan.md' });
    const editor = editing.getByLabel('File text');

    await expect(editing.getByText('Based on version 1.')).toBeVisible();
    await expect(editor).toHaveValue(PLAN);

    await editor.fill(`${PLAN}\n\n### Dyers\n\n- Studio one: committed`);
    await editing.getByRole('button', { name: 'Save' }).click();

    await expect(viewer.getByRole('heading', { name: 'Dyers' })).toBeVisible();
    await expect(viewer.getByRole('listitem')).toHaveText('Studio one: committed');
    expect(plan.version).toBe(2);
    expect(plan.last_author).toBe('josh');

    // Keeping it puts the badge on its card in the thread.
    await viewer.getByRole('switch', { name: 'Not kept. Only for this conversation' }).click();
    await expect(viewer.getByRole('switch', { name: 'Kept. Donna finds it when it comes up' })).toBeChecked();
    expect(plan.kept).toBe(true);

    await viewer.getByRole('button', { name: 'Close' }).click();

    await expect(viewer).toBeHidden();
    await expect(page.getByRole('button', { name: 'Open launch-plan.md, kept' })).toBeVisible();

    if (!isMobile) {
        // With the file closed, the chat is the one centered column again.
        const alone = (await page.locator('.chat').boundingBox())!;

        expect(Math.abs(alone.x + alone.width / 2 - screen.width / 2)).toBeLessThanOrEqual(2);
    }
});

test('a save that collides with Donna’s edit is refused, and "Keep mine" saves on top of hers', async ({ page, donna }) => {
    const plan = donna.file('launch-plan.md', PLAN);

    donna.said('josh', "Here's the launch plan.", daysAgo(0, '09:41'), { files: [donna.attachment(plan)] });

    await page.goto('/');
    await page.getByRole('button', { name: 'Open launch-plan.md' }).click();
    await page.getByRole('region', { name: 'launch-plan.md' }).getByRole('button', { name: 'Edit' }).click();

    const editing = page.getByRole('region', { name: 'Editing launch-plan.md' });

    await editing.getByLabel('File text').fill('## Mine\n\nMy version of the plan.');

    // While he types, she rewrites the pricing section.
    donna.donnaEdits(plan, PLAN.replace('Flat monthly plan for launch.', 'One flat plan.'));

    await editing.getByRole('button', { name: 'Save' }).click();

    const banner = editing.getByRole('alert');

    await expect(banner).toContainText("Donna changed this file while you were editing, so it wasn't saved.");
    await expect(editing.getByLabel('File text')).toHaveValue('## Mine\n\nMy version of the plan.');
    await expect(editing.getByText('Based on version 1. Your text is kept here until you choose.')).toBeVisible();
    expect(plan.body).toContain('One flat plan.');

    await banner.getByRole('button', { name: 'Keep mine' }).click();

    await expect(page.getByRole('region', { name: 'launch-plan.md' }).getByRole('heading', { name: 'Mine' })).toBeVisible();
    expect(plan).toMatchObject({ version: 3, last_author: 'josh', body: '## Mine\n\nMy version of the plan.' });
});

test('/files lists kept files first, on the device, and a card opens its file', async ({ page, donna }) => {
    donna.file('stripe-payouts-nov.csv', 'Date,Payout,Amount\nNov 3,po_1QxA,$812.40\nNov 4,po_1QxB,$220.00');
    donna.file('launch-plan.md', PLAN, { kept: true });

    await page.goto('/');
    await page.getByLabel('Message Donna').fill('/files');
    await page.getByRole('button', { name: 'Send' }).click();

    await expect(page.getByRole('region', { name: 'Kept', exact: true }).getByRole('button', { name: 'Open launch-plan.md, kept' })).toBeVisible();

    await page.getByRole('region', { name: 'Recent, not kept' }).getByRole('button', { name: 'Open stripe-payouts-nov.csv' }).click();

    const viewer = page.getByRole('region', { name: 'stripe-payouts-nov.csv' });

    await expect(viewer.getByRole('cell', { name: '$812.40' })).toBeVisible();
    await expect(viewer.getByText('Showing 2 of 2 rows.')).toBeVisible();
    await expect(viewer.getByText('Read-only')).toBeVisible();

    expect(donna.sends).toHaveLength(0);
});
