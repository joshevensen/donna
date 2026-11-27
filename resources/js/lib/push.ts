import { api } from '../api/client';
import type { Me } from '../api/types';
import { isInstalled, isIOS } from './platform';

/*
 | Web Push on this device. Subscriptions are per device, so whether this one
 | is subscribed is the browser's to say (PushManager), not the server's.
 */

export type PushResult =
    | { ok: true; message: string }
    | { ok: false; message: string };

const NEEDS_INSTALL =
    'Notifications only work from the app on your Home Screen. In Safari, tap Share, then "Add to Home Screen", open Donna from there, and ask me again.';

function supported(): boolean {
    return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
}

/** The VAPID public key as the bytes PushManager wants. */
function applicationServerKey(publicKey: string): Uint8Array<ArrayBuffer> {
    const base64 = (publicKey + '='.repeat((4 - (publicKey.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
    const raw = atob(base64);
    const bytes = new Uint8Array(new ArrayBuffer(raw.length));

    for (let index = 0; index < raw.length; index += 1) {
        bytes[index] = raw.charCodeAt(index);
    }

    return bytes;
}

async function registration(): Promise<ServiceWorkerRegistration> {
    return (await navigator.serviceWorker.getRegistration('/')) ?? (await navigator.serviceWorker.register('/sw.js', { scope: '/' }));
}

export async function currentSubscription(): Promise<PushSubscription | null> {
    if (!supported()) {
        return null;
    }

    const existing = await navigator.serviceWorker.getRegistration('/');

    return (await existing?.pushManager.getSubscription()) ?? null;
}

/**
 * Turn notifications on for this device. The permission prompt is only
 * allowed from a tap, so this has to be the first thing a tap does.
 */
export async function enablePush(me: Me): Promise<PushResult> {
    if (isIOS() && !isInstalled()) {
        return { ok: false, message: NEEDS_INSTALL };
    }

    if (!supported()) {
        return { ok: false, message: "This browser can't show notifications." };
    }

    if (!me.push.configured || me.push.public_key === null) {
        return { ok: false, message: "Notifications aren't set up on the server yet." };
    }

    const permission = await Notification.requestPermission();

    if (permission !== 'granted') {
        return { ok: false, message: "Notifications are blocked for Donna. Allow them in this device's settings, then ask me again." };
    }

    const worker = await registration();

    await navigator.serviceWorker.ready;

    const subscription =
        (await worker.pushManager.getSubscription()) ??
        (await worker.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: applicationServerKey(me.push.public_key) }));

    await api.savePushSubscription(subscription.toJSON());

    return { ok: true, message: 'Notifications are on for this device.' };
}

/** Turn notifications off for this device, here and on the server. */
export async function disablePush(): Promise<PushResult> {
    const subscription = await currentSubscription();

    if (subscription === null) {
        return { ok: true, message: 'Notifications were already off on this device.' };
    }

    await api.deletePushSubscription(subscription.endpoint);
    await subscription.unsubscribe();

    return { ok: true, message: 'Notifications are off for this device.' };
}

/**
 * Tell the server this device is still subscribed, so a subscription the
 * browser renewed or the server lost is put back. Quiet: a failure here is
 * retried on the next open.
 */
export async function refreshPush(): Promise<void> {
    try {
        const subscription = await currentSubscription();

        if (subscription !== null && Notification.permission === 'granted') {
            await api.savePushSubscription(subscription.toJSON());
        }
    } catch {
        // Not worth interrupting anything for.
    }
}
