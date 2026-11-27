import { useCallback, useEffect, useState } from 'react';
import { api } from '../api/client';
import type { AppActionType } from '../api/types';
import { currentSubscription, disablePush, enablePush, type PushResult, refreshPush } from '../lib/push';
import { useSession } from './useSession';

interface AppActions {
    /** Do one of the things only the app can do. Always called from a tap. */
    run: (type: AppActionType) => Promise<PushResult>;
    /** Whether this device has notifications on; null until the browser has said. */
    subscribed: boolean | null;
}

/**
 * Signing out and notifications: the things Donna can offer a button for,
 * and the slash commands do directly. All of it happens on this device.
 */
export function useAppActions(): AppActions {
    const { me, signedOut } = useSession();
    const [subscribed, setSubscribed] = useState<boolean | null>(null);

    // Known ahead of time, so /notifications can ask for permission as the first thing its tap does.
    useEffect(() => {
        currentSubscription()
            .then((subscription) => setSubscribed(subscription !== null))
            .catch(() => setSubscribed(false));

        void refreshPush();
    }, []);

    const run = useCallback(
        async (type: AppActionType): Promise<PushResult> => {
            switch (type) {
                case 'enable_notifications': {
                    if (!me) {
                        return { ok: false, message: 'Sign in first.' };
                    }

                    const result = await enablePush(me);

                    setSubscribed((current) => (result.ok ? true : current));

                    return result;
                }

                case 'disable_notifications': {
                    const result = await disablePush();

                    setSubscribed(false);

                    return result;
                }

                case 'sign_out': {
                    // This device stops getting notifications. If that part fails, signing out still happens.
                    await disablePush().catch(() => undefined);
                    await api.logout();
                    signedOut();

                    return { ok: true, message: 'Signed out.' };
                }

                case 'sign_out_everywhere': {
                    await api.logoutEverywhere();
                    await currentSubscription()
                        .then((subscription) => subscription?.unsubscribe())
                        .catch(() => undefined);
                    signedOut();

                    return { ok: true, message: 'Signed out everywhere.' };
                }
            }
        },
        [me, signedOut],
    );

    return { run, subscribed };
}
