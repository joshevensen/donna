import { createContext, type ReactNode, useCallback, useContext, useEffect, useMemo, useState } from 'react';
import { api, NetworkError, setUnauthorizedHandler } from '../api/client';
import type { Me } from '../api/types';
import { storage } from '../lib/storage';

interface Session {
    /** Who is signed in; null when nobody is; undefined until that is known. */
    me: Me | null | undefined;
    /** The server couldn't be reached to find out. */
    unreachable: boolean;
    signedIn: (me: Me) => void;
    signedOut: () => void;
}

const SessionContext = createContext<Session | null>(null);

const RETRY_MS = 3_000;

export function SessionProvider({ children }: { children: ReactNode }) {
    // Open on what was true last time. If the session has since ended, the first request says so.
    const [me, setMe] = useState<Me | null | undefined>(() => storage.me<Me>() ?? undefined);
    const [unreachable, setUnreachable] = useState(false);

    const signedIn = useCallback((who: Me) => {
        storage.saveMe(who);
        setMe(who);
    }, []);

    const signedOut = useCallback(() => {
        storage.saveMe(null);
        setMe(null);
    }, []);

    useEffect(() => {
        let cancelled = false;
        let retry: ReturnType<typeof setTimeout> | undefined;

        const ask = () =>
            api
                .me()
                .then((who) => {
                    if (!cancelled) {
                        setUnreachable(false);
                        signedIn(who);
                    }
                })
                .catch((error: unknown) => {
                    if (cancelled) {
                        return;
                    }

                    if (error instanceof NetworkError) {
                        setUnreachable(true);
                        retry = setTimeout(ask, RETRY_MS);
                    } else {
                        signedOut();
                    }
                });

        void ask();

        // Any later 401 means the session ran out: 30 days unused, or the 90-day cap.
        setUnauthorizedHandler(signedOut);

        return () => {
            cancelled = true;
            clearTimeout(retry);
            setUnauthorizedHandler(null);
        };
    }, [signedIn, signedOut]);

    const value = useMemo(() => ({ me, unreachable, signedIn, signedOut }), [me, unreachable, signedIn, signedOut]);

    return <SessionContext.Provider value={value}>{children}</SessionContext.Provider>;
}

export function useSession(): Session {
    const session = useContext(SessionContext);

    if (session === null) {
        throw new Error('useSession needs a SessionProvider above it.');
    }

    return session;
}
