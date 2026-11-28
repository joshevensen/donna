import { useState } from 'react';
import { Link, useParams } from 'react-router';
import { api, ApiError } from '../api/client';
import { Gate, GateError } from '../components/Gate';
import { useSession } from '../hooks/useSession';
import { explain } from '../lib/errors';

/**
 * Where "Sign out everywhere" in the new-sign-in email lands. It works
 * without being signed in, from any device, and only when the button is
 * pressed: opening the link changes nothing.
 */
export function RevokePage() {
    const { token = '' } = useParams();
    const { signedOut } = useSession();
    const [state, setState] = useState<'idle' | 'busy' | 'done'>('idle');
    const [error, setError] = useState<string | null>(null);

    const revoke = async () => {
        // The token is the signed link's expiry and signature, joined by a dash.
        const [expires = '', signature = ''] = token.split('-');

        setState('busy');
        setError(null);

        try {
            await api.revoke(expires, signature);
            signedOut();
            setState('done');
        } catch (failure) {
            setError(failure instanceof ApiError && failure.status === 403 ? 'This link has expired or is not valid.' : explain(failure));
            setState('idle');
        }
    };

    if (state === 'done') {
        return (
            <Gate title="Every device is signed out">
                <p className="gate__text">Nobody is signed in to Donna now, and notifications are off everywhere. Sign in again when you&rsquo;re ready.</p>
                <Link className="button button--wide" to="/login">
                    Sign in
                </Link>
            </Gate>
        );
    }

    return (
        <Gate title="Sign out everywhere?">
            <p className="gate__text">This signs every phone and browser out of Donna, including this one.</p>
            <button type="button" className="button button--wide" disabled={state === 'busy'} onClick={revoke}>
                {state === 'busy' ? 'Signing out…' : 'Sign out everywhere'}
            </button>
            {error !== null && <GateError>{error}</GateError>}
        </Gate>
    );
}
