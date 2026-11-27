import { daysAgo, expect, test } from './support/test';

test.beforeEach(({ donna }) => {
    donna.said('josh', 'Where did we land on the Fibermade checkout flow?', daysAgo(1));
    donna.said('donna', 'You kept guest checkout and dropped the account-first flow.\n---\nYou never told me whether the discount-code bug got fixed. Did it?', daysAgo(1), {
        sources: [
            { type: 'fact', label: 'Guest checkout kept over account-first · Nov 3' },
            { type: 'message', label: 'You, Nov 3 — "let\'s keep guest checkout"' },
        ],
    });
});

test('send a message and get a reply that arrives as texts, one at a time', async ({ page, donna }) => {
    donna.replies.push({ bubbles: ['Two main projects right now: Fibermade and Donna.', 'Fibermade last came up two days ago. Want a refresher?'] });

    await page.goto('/');

    await expect(page.getByText('Yesterday')).toBeVisible();
    await expect(page.getByText('You kept guest checkout and dropped the account-first flow.')).toBeVisible();

    const send = page.getByRole('button', { name: 'Send' });
    await expect(send).toBeDisabled();

    await page.getByLabel('Message Donna').fill('What am I working on?');
    await send.click();

    // His message is there at once, under today's date, and she starts typing.
    await expect(page.getByText('Today')).toBeVisible();
    await expect(page.getByText('What am I working on?')).toBeVisible();
    await expect(page.getByRole('status', { name: 'Donna is typing' })).toBeVisible();
    await expect(page.getByLabel('Message Donna')).toHaveValue('');

    // The first text lands with the dots still going for the second.
    await expect(page.getByText('Two main projects right now: Fibermade and Donna.')).toBeVisible();
    await expect(page.getByText('Fibermade last came up two days ago.')).toBeHidden();
    await expect(page.getByRole('status', { name: 'Donna is typing' })).toBeVisible();

    await expect(page.getByText('Fibermade last came up two days ago. Want a refresher?')).toBeVisible();
    await expect(page.getByRole('status', { name: 'Donna is typing' })).toBeHidden();

    expect(donna.sends).toHaveLength(1);
});

test('tapping a bubble shows its time, and her sources open what she looked at', async ({ page }) => {
    await page.goto('/');

    await page.getByText('You kept guest checkout and dropped the account-first flow.').click();

    await expect(page.getByText('4:12 PM')).toBeVisible();

    await page.getByRole('button', { name: '2 sources' }).click();

    const sheet = page.getByRole('dialog', { name: 'What Donna looked at' });
    await expect(sheet.getByRole('heading', { name: 'What I looked at' })).toBeVisible();
    await expect(sheet.getByText('Guest checkout kept over account-first · Nov 3')).toBeVisible();

    await sheet.getByRole('button', { name: 'Done' }).click();
    await expect(sheet).toBeHidden();

    // Tapping again puts the time away.
    await page.getByText('You kept guest checkout and dropped the account-first flow.').click();
    await expect(page.getByText('4:12 PM')).toBeHidden();
});

test('a message that does not send stays, survives a reload, and a retry sends it exactly once', async ({ page, donna }) => {
    donna.dropSends = true;

    await page.goto('/');
    await page.getByLabel('Message Donna').fill('Did Kristen send the pattern files?');
    await page.getByRole('button', { name: 'Send' }).click();

    const retry = page.getByRole('button', { name: 'Not delivered. Tap to retry' });
    await expect(retry).toBeVisible();
    await expect(page.getByText('You’re offline. Messages will send when you’re back.')).toBeVisible();

    // iOS closes Home Screen apps freely: the unsent message has to still be there afterwards.
    await page.reload();
    await expect(page.getByText('Did Kristen send the pattern files?')).toBeVisible();
    await expect(retry).toBeVisible();

    donna.dropSends = false;
    await retry.click();

    await expect(page.getByText('Got it.')).toBeVisible();
    await expect(retry).toBeHidden();
    await expect(page.getByText('Did Kristen send the pattern files?')).toHaveCount(1);

    expect(donna.sends).toHaveLength(1);
    expect(donna.sends[0]!.key).toMatch(/^[0-9a-f-]{36}$/);
});

test('when Donna could not reply, the thread says so and Try again gets the answer', async ({ page, donna }) => {
    donna.replies.push({ fails: true }, { bubbles: ['Still open: the discount code applies twice at checkout.'] });

    await page.goto('/');
    await page.getByLabel('Message Donna').fill("What's still open on the discount bug?");
    await page.getByRole('button', { name: 'Send' }).click();

    await expect(page.getByText('Donna couldn’t reply.')).toBeVisible();

    await page.getByRole('button', { name: 'Try again' }).click();

    await expect(page.getByRole('status', { name: 'Donna is typing' })).toBeVisible();
    await expect(page.getByText('Still open: the discount code applies twice at checkout.')).toBeVisible();
    await expect(page.getByText('Donna couldn’t reply.')).toBeHidden();

    // Trying again asked for the same reply; it did not send his message a second time.
    expect(donna.sends).toHaveLength(1);
});

test('renders her markdown: a list, inline code, and a code block with a copy button', async ({ page, donna }) => {
    donna.said('donna', 'Two things:\n\n- Rename `checkout.guest`\n- Run the test\n\n```php\nit(\'works\', fn () => expect(true)->toBeTrue());\n```', daysAgo(0, '09:41'));

    await page.goto('/');

    await expect(page.getByRole('listitem').filter({ hasText: 'Run the test' })).toBeVisible();
    await expect(page.locator('code', { hasText: 'checkout.guest' })).toBeVisible();
    await expect(page.locator('.code-block pre')).toHaveText("it('works', fn () => expect(true)->toBeTrue());");
    await expect(page.locator('.code-block').getByText('php')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Copy' })).toBeVisible();
});

test('/help is answered on the device and never sent', async ({ page, donna }) => {
    await page.goto('/');
    await page.getByLabel('Message Donna').fill('/help');
    await page.getByRole('button', { name: 'Send' }).click();

    await expect(page.getByText('signs this device out')).toBeVisible();

    expect(donna.sends).toHaveLength(0);
});

test('a button Donna offers does its work on the tap: signing out', async ({ page, donna }) => {
    donna.said('donna', 'This signs you out on this device.', daysAgo(0, '09:41'), { actions: [{ type: 'sign_out', label: 'Sign out' }] });

    await page.goto('/');
    await page.getByRole('button', { name: 'Sign out' }).click();

    await expect(page.getByRole('heading', { name: 'Sign in to Donna' })).toBeVisible();
    expect(donna.signedIn).toBe(false);
});

test('scrolling up loads earlier messages and holds the place; a new message only pulls the view down from the bottom', async ({ page, donna }) => {
    for (let n = 1; n <= 70; n += 1) {
        donna.said(n % 2 ? 'josh' : 'donna', `Message ${n}`, daysAgo(3));
    }

    const poll = () => page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));
    const scrollTo = (where: 'top' | 'bottom') =>
        page.locator('.messages').evaluate((element, to) => {
            element.scrollTop = to === 'top' ? -element.scrollHeight : 0;
        }, where);

    await page.goto('/');

    // Newest at the bottom, and only the latest page is loaded.
    await expect(page.getByText('Message 70', { exact: true })).toBeInViewport();
    await expect(page.getByText('Message 40', { exact: true })).toHaveCount(0);

    await scrollTo('top');

    await expect(page.getByText('Message 40', { exact: true })).toBeAttached();
    await expect(page.getByText('Message 41', { exact: true })).toBeInViewport();

    // Something arrives from another device while he is reading further up: nothing moves.
    donna.said('donna', 'Sent from the Mac.', new Date().toISOString());
    await poll();

    await expect(page.getByText('Sent from the Mac.')).toBeAttached();
    await expect(page.getByText('Sent from the Mac.')).not.toBeInViewport();
    await expect(page.getByText('Message 41', { exact: true })).toBeInViewport();

    // At the bottom, the next one comes into view by itself.
    await scrollTo('bottom');
    donna.said('donna', 'And another.', new Date().toISOString());
    await poll();

    await expect(page.getByText('And another.')).toBeInViewport();
});
