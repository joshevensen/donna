import { expect, test } from './support/test';

test('the / button lists the commands, and /cost answers on the device without reaching Donna', async ({ page, donna }) => {
    donna.commandCosts = {
        today: { period: 'today', from: '2026-11-28', until: '2026-11-28', total_usd: '0.01', calls: 1, by_purpose: { reply: { usd: '0.01', calls: 1 } }, web_searches: 0 },
        this_month: { period: 'this_month', from: '2026-11-01', until: '2026-11-30', total_usd: '0.42', calls: 18, by_purpose: { embedding: { usd: '0.01', calls: 3 }, reply: { usd: '0.41', calls: 15 } }, web_searches: 0 },
    };

    await page.goto('/');
    await page.getByRole('button', { name: 'Commands' }).click();

    const list = page.getByRole('list', { name: 'Commands' });

    await list.getByText('/cost', { exact: true }).click();

    await expect(list).toBeHidden();
    await expect(page.getByLabel('Message Donna')).toHaveValue('/cost');

    await page.getByRole('button', { name: 'Send' }).click();

    await expect(page.locator('.row[data-who="josh"]', { hasText: '/cost' })).toBeVisible();
    await expect(page.getByText('$0.42 · 18 calls')).toBeVisible();
    await expect(page.getByText('Reply $0.41 · 15')).toBeVisible();

    expect(donna.sends).toHaveLength(0);
});

test('/settings opens the Settings drawer with notifications and both ways to sign out', async ({ page, donna }) => {
    await page.goto('/');
    await page.getByLabel('Message Donna').fill('/settings');
    await page.getByRole('button', { name: 'Send' }).click();

    const settings = page.getByRole('dialog', { name: 'Settings' });
    await expect(settings).toBeVisible();
    await expect(settings.getByRole('switch', { name: 'Notifications' })).toBeVisible();
    await expect(settings.getByRole('button', { name: 'Sign out', exact: true })).toBeVisible();
    await expect(settings.getByRole('button', { name: 'Sign out everywhere' })).toBeVisible();

    await settings.getByRole('button', { name: 'Done' }).click();
    await expect(settings).toBeHidden();

    expect(donna.sends).toHaveLength(0);
});

test('a command whose answer cannot be fetched says so, and still never reaches Donna', async ({ page, donna }) => {
    donna.failCommands = true;

    await page.goto('/');
    await page.getByLabel('Message Donna').fill('/cost');
    await page.getByRole('button', { name: 'Send' }).click();

    await expect(page.getByText("I couldn't get that. Check your connection and try again.")).toBeVisible();

    expect(donna.sends).toHaveLength(0);
});
