import { daysAgo, expect, test } from './support/test';

test('sign in with the emailed code and land in the chat', async ({ page, donna }) => {
    donna.signedIn = false;
    donna.said('donna', 'Morning, Josh.', daysAgo(0, '08:00'));

    await page.goto('/');

    await expect(page).toHaveURL(/\/login$/);
    await page.getByLabel('Email address').fill('josh@example.com');
    await page.getByRole('button', { name: 'Email me a code' }).click();

    await expect(page.getByRole('heading', { name: 'Check your email' })).toBeVisible();

    await page.getByLabel('6-digit code').fill('000000');
    await page.getByRole('button', { name: 'Sign in' }).click();
    await expect(page.getByRole('alert')).toHaveText("That code didn't work. Check it, or ask for a new one.");

    await page.getByLabel('6-digit code').fill('123456');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/$/);
    await expect(page.getByText('Morning, Josh.')).toBeVisible();
});

test('coming back from the Mail app lands on the code field, not the start', async ({ page, donna }) => {
    donna.signedIn = false;

    await page.goto('/login');
    await page.getByLabel('Email address').fill('josh@example.com');
    await page.getByRole('button', { name: 'Email me a code' }).click();
    await expect(page.getByLabel('6-digit code')).toBeVisible();

    // iOS may reload the installed app when Josh returns from reading the code.
    await page.reload();

    await expect(page.getByLabel('6-digit code')).toBeVisible();
});

test('the emailed link signs in only when its button is pressed', async ({ page, donna }) => {
    donna.signedIn = false;

    await page.goto('/login/link/' + 'a'.repeat(40));

    await expect(page.getByRole('heading', { name: 'Sign in to Donna' })).toBeVisible();
    expect(donna.signedIn).toBe(false);

    // In Safari on an iPhone the link can't sign in the app on the Home Screen, and says so.
    await expect(page.getByText('This link signs in Safari, not the Donna app on your Home Screen.')).toBeVisible({
        visible: test.info().project.name === 'phone',
    });

    await page.getByRole('button', { name: /^Sign in/ }).click();

    await expect(page).toHaveURL(/\/$/);
    expect(donna.signedIn).toBe(true);
});

test('a session that ends mid-use goes to sign-in and keeps the draft', async ({ page, donna }) => {
    await page.goto('/');
    await page.getByLabel('Message Donna').fill('Half a thought about the checkout');

    // The 30-day timeout or the 90-day cap: the next request is refused.
    donna.signedIn = false;
    await page.evaluate(() => document.dispatchEvent(new Event('visibilitychange')));

    await expect(page.getByRole('heading', { name: 'Sign in to Donna' })).toBeVisible();

    await page.getByLabel('Email address').fill('josh@example.com');
    await page.getByRole('button', { name: 'Email me a code' }).click();
    await page.getByLabel('6-digit code').fill('123456');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page.getByLabel('Message Donna')).toHaveValue('Half a thought about the checkout');
});

test('"sign out everywhere" from the email asks first, then signs everything out', async ({ page, donna }) => {
    await page.goto('/security/revoke/1800000000-abcdef');

    await expect(page.getByRole('heading', { name: 'Sign out everywhere?' })).toBeVisible();
    expect(donna.signedIn).toBe(true);

    await page.getByRole('button', { name: 'Sign out everywhere' }).click();

    await expect(page.getByRole('heading', { name: 'Every device is signed out' })).toBeVisible();
    expect(donna.signedIn).toBe(false);
});

test('a code printed on the server signs in without asking for an email, which would cancel it', async ({ page, donna }) => {
    donna.signedIn = false;

    await page.goto('/login');
    await page.getByLabel('Email address').fill('josh@example.com');
    await page.getByRole('button', { name: 'I already have a code' }).click();

    await page.getByLabel('6-digit code').fill('123456');
    await page.getByRole('button', { name: 'Sign in' }).click();

    await expect(page).toHaveURL(/\/$/);
    expect(donna.codeRequests).toBe(0);
});
