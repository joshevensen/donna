import { useState } from 'react';
import { Link, Navigate, useNavigate, useParams } from 'react-router';
import { api } from '../api/client';
import { Gate, GateError } from '../components/Gate';
import { useSession } from '../hooks/useSession';
import { explain } from '../lib/errors';
import { isInstalled, isIOS } from '../lib/platform';
import { storage } from '../lib/storage';

/**
 * Where the link in the sign-in email lands. Opening it does nothing: the
 * button signs in. Mail scanners that fetch links ahead of time would
 * otherwise use the link up before Josh got to it.
 */
export function LoginLinkPage() {
    const { token = '' } = useParams();
    const { me, signedIn } = useSession();
    const navigate = useNavigate();
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    // On an iPhone a link from Mail opens in Safari, which doesn't share its sign-in with the
    // app on the Home Screen. Say so, rather than leave him signed in to the wrong place.
    const [strandedInSafari] = useState(() => isIOS() && !isInstalled());

    if (me) {
        return <Navigate to="/" replace />;
    }

    const signIn = async () => {
        setBusy(true);
        setError(null);

        try {
            const who = await api.verifyLink(token);

            storage.saveLogin(null);
            signedIn(who);
            navigate('/', { replace: true });
        } catch (failure) {
            setError(explain(failure));
            setBusy(false);
        }
    };

    return (
        <Gate title="Sign in to Donna">
            {strandedInSafari && (
                <p className="gate__text">
                    This link signs in Safari, not the Donna app on your Home Screen. To sign in there, open Donna and type the 6-digit code from the same
                    email.
                </p>
            )}
            <button type="button" className="button button--wide" disabled={busy} onClick={signIn}>
                {busy ? 'Signing in…' : strandedInSafari ? 'Sign in here in Safari' : 'Sign in'}
            </button>
            {error !== null && (
                <>
                    <GateError>{error}</GateError>
                    <Link className="button button--quiet" to="/login">
                        Get a new code
                    </Link>
                </>
            )}
        </Gate>
    );
}
