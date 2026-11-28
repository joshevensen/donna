# Notifications (Web Push) and the Home Screen app

When Donna answers and the app isn't open, your phone shows a notification, like a text. This file sets up the keys that make that possible, installs Donna to the iPhone's Home Screen, and turns notifications on.

Push is optional. Without the keys everything else works; she just can't notify you.

## Before you start

- [forge-site.md](forge-site.md) and [queue-and-scheduler.md](queue-and-scheduler.md) are done. Notifications are sent by the queue worker.
- You can sign in ([mail.md](mail.md)).
- The site has its certificate. Browsers only allow push, and installing to the Home Screen, over HTTPS.
- The iPhone is on iOS 16.4 or newer.

## 1. Make the keys

Web Push identifies the server to Apple's, Google's and Mozilla's push services with a key pair (called VAPID keys). On the server:

```bash
cd /home/forge/donna.joshevensen.com/current && php8.4 artisan webpush:vapid --show
```

It prints two lines, `VAPID_PUBLIC_KEY=...` and `VAPID_PRIVATE_KEY=...`. With `--show` it only prints them; it does not write to any file.

## 2. Set the environment

In the site's **Environment** tab in Forge, add the two lines it printed, and a contact address:

```dotenv
VAPID_PUBLIC_KEY=...
VAPID_PRIVATE_KEY=...
VAPID_SUBJECT=mailto:you@example.com
```

The private key is a secret. The subject is how a push service would reach you if your server misbehaved; your email address is fine.

**Redeploy.**

Make the keys once and keep them. **If either key ever changes, every device stops receiving notifications** and has to turn them on again (step 4).

## 3. Install Donna on the iPhone

On an iPhone, notifications only work from the installed app, not from a Safari tab.

1. Open `https://donna.joshevensen.com` in **Safari** (not another browser's app).
2. Tap the **Share** button, then **Add to Home Screen**, then **Add**.
3. Open Donna from the new icon. It opens full screen, with no Safari bars.
4. Sign in. The installed app has its own sign-in, separate from Safari's, so use the code from the email, not the link ([mail.md](mail.md#signing-in-on-the-iphone)).

## 4. Turn notifications on

In the installed app, tell her "turn on notifications". She replies with a **Turn on notifications** button. Tap it, then **Allow** when iOS asks. The button changes to **Done**.

Typing `/settings` and turning on the **Notifications** switch does the same without involving her; turning the switch off turns them off.

Do this once on each device you want notified.

**On the Mac**, open Donna in the browser you use and do the same. macOS also has to allow that browser to show notifications: **System Settings → Notifications**, then the browser *(unverified; the exact screen differs by macOS version)*.

## 5. Check it

1. In the installed app, send her a message.
2. Lock the phone, or switch to another app, right away.
3. When she answers, a notification titled "Donna" appears with the start of her reply. Tapping it opens the app.

Nothing arrives while the app is open and on screen: you are already looking at the reply.

**An open tab on the Mac holds back the phone's notification.** "Open" is tracked for you, not per device, so while a Donna tab is visible on any device, no device is notified. Close or hide the tab to test the phone.

## If it doesn't work

| What you see | Cause | Fix |
|---|---|---|
| The button says notifications aren't set up on the server | The keys aren't in the environment, or you didn't redeploy after adding them | Steps 1 and 2. |
| The button says notifications only work from the Home Screen | You are in a Safari tab on the iPhone | Step 3, then ask again from the installed app. |
| The button says notifications are blocked | You tapped **Don't Allow**, or they were turned off later | iPhone: **Settings → Notifications → Donna → Allow Notifications**. Then ask again. |
| The button says **Done** but nothing ever arrives | The queue worker isn't running, or a Donna tab is open and visible somewhere | [queue-and-scheduler.md](queue-and-scheduler.md); close other tabs. Then `php8.4 artisan queue:failed` on the server. |
| They stopped after working | The keys changed, the app was removed and added again, or you signed out | Turn them on again (step 4). Signing out turns them off for that device on purpose. |

## Turning them off, and a lost phone

- One device: tell her "turn off notifications", or type `/settings` and turn off the **Notifications** switch.
- Signing a device out turns its notifications off.
- A lost phone: sign out everywhere ([operating.md](operating.md#a-lost-or-stolen-device)). That removes every device's notifications along with every sign-in.

Next: [verify-setup.md](verify-setup.md).

---

[← All setup instructions](README.md)
