# Mail (sign-in codes)

Donna has no passwords. You sign in to the web app with a 6-digit code she emails you, so mail has to work and has to land in an inbox you read. She sends two kinds of email, both only to you: the sign-in code, and a "new sign-in" notice every time a device signs in.

Mail goes out through [Resend](https://resend.com), over its HTTPS API. That matters on DigitalOcean, which blocks the usual mail ports (25, 465, 587) on droplets; the API uses none of them.

**Until this is done, the way in is the server.** `php8.4 artisan donna:login` prints a working code and link ([step 6](#6-the-way-in-without-email)). You can deploy and use Donna before finishing this file.

## Before you start

- [forge-site.md](forge-site.md) is done and the site has deployed.
- You can add DNS records for `joshevensen.com` at your registrar.

## 1. Check the address she sends to

`DONNA_USER_EMAIL` in the site's environment is the only address that can sign in, and the only address she ever emails. Its default, `josh@heydonna.app`, is a placeholder: if it is still that, or blank, set it to an address whose inbox you actually read, then redeploy.

Changing it later is safe. The next deploy moves the one user to the new address; it does not create a second user.

## 2. Create the Resend account and add a sending domain

1. Sign up at [resend.com](https://resend.com). The free plan is enough: Donna sends a handful of emails a month *(unverified: Resend's free plan was 3,000 emails a month and 100 a day when this was written)*.
2. Open **Domains** and add `donna.joshevensen.com` as a sending domain. Using Donna's own subdomain keeps her mail separate from your personal email's reputation and records.
3. Resend shows a short list of DNS records for that domain *(unverified: when this was written they were an MX and a TXT record on a `send` subdomain for SPF, and a TXT record at `resend._domainkey` for DKIM)*. Add each one at your registrar exactly as shown. The values are long; copy them, don't retype them.
4. Back in Resend, press **Verify**. It usually takes a few minutes and can take up to a day.

At most registrars the "host" or "name" field wants only the part before `joshevensen.com`. So a record Resend lists for `send.donna.joshevensen.com` is entered as `send.donna`.

Carry on while it verifies. Mail will not send until the domain shows as verified.

## 3. Create an API key

In Resend, open **API Keys** and create one named `donna`, with **Sending access** only, limited to the `donna.joshevensen.com` domain. It is shown once; copy it.

It is a secret. It goes in the site's environment in Forge and nowhere else.

## 4. Set the environment

In the site's **Environment** tab in Forge, set:

```dotenv
MAIL_MAILER=resend
RESEND_API_KEY=re_...
MAIL_FROM_ADDRESS=donna@donna.joshevensen.com
MAIL_FROM_NAME=Donna
```

`.env.example` starts with `MAIL_MAILER=log`, which writes mail into the log file instead of sending it, so this line must be changed. The address before the `@` can be anything; the part after it must be the domain you verified.

**Until you change it, every sign-in email is written to `storage/logs/laravel.log` on the server, code and link included.** Each works once and for 10 minutes, and the file is only readable from the server, but don't leave it that way: finish this file, or don't press **Email me a code** until you have (use [step 6](#6-the-way-in-without-email) instead).

**Redeploy.** The deploy script caches the configuration, so nothing changes until it runs.

## 5. Check it

Open `https://donna.joshevensen.com`, enter your address, and press **Email me a code**. Within a minute you should have an email titled "123456 is your Donna sign-in code" (with your own code). Type the code in. A second email, "New sign-in to Donna", follows.

If nothing arrives:

- **Look in spam.** If it is there, mark it "not spam"; a new sending domain can take a few emails to earn trust.
- **Look at Resend's Emails page.** It lists every email she tried to send and what happened to it. Nothing listed means the request never reached Resend: check `MAIL_MAILER` and the key, and that you redeployed.
- **Look at the end of `storage/logs/laravel.log`** on the server. A failed send is logged there. The sign-in page says "check your email" whether or not the send worked, on purpose: it never confirms which address is yours.
- **"Too many tries."** She sends at most 3 codes to an address in 10 minutes. Wait, or use the next step.

## 6. The way in without email

On the server:

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan donna:login
```

It prints a 6-digit code and a link. Either signs you in once, within 10 minutes.

- **The code:** on the sign-in page, enter your address and press **I already have a code**, then type the printed code. Do not press **Email me a code**: asking for a new code cancels the printed one.
- **The link:** open it in the browser you want signed in and press **Sign in**. This is no use for the app on the iPhone's Home Screen, which a link can't reach; use the code there.

Running the command also cancels any code that was emailed before it.

Use this before mail is set up, when Resend is down, or if you lose access to the inbox.

## Signing in on the iPhone

The link in the email opens Safari, and Safari does not share its sign-in with the app on your Home Screen. So on the phone, open Donna from the Home Screen, ask for a code, read it in Mail, and come back and type it. The app remembers that it is waiting for a code, so returning from Mail lands on the code field.

On the Mac, the link is the quick way: it opens a page with one **Sign in** button.

Next: [web-push.md](web-push.md).

---

[← All setup instructions](README.md)
