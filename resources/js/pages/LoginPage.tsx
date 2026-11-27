import { type FormEvent, useState } from 'react';
import { Navigate, useNavigate } from 'react-router';
import { api } from '../api/client';
import { Gate, GateError } from '../components/Gate';
import { useSession } from '../hooks/useSession';
import { explain } from '../lib/errors';
import { storage } from '../lib/storage';

/** A code works for ten minutes; after that the code step is no use. */
const CODE_LIFETIME_MS = 10 * 60 * 1000;

/**
 * Sign-in: an email address, then the 6-digit code Donna sends to it. There
 * are no passwords. The step is remembered, because on an iPhone getting
 * the code means leaving for Mail, and iOS may reload the app on return.
 */
export function LoginPage() {
    const { me, signedIn, unreachable } = useSession();
    const navigate = useNavigate();

    const [pending] = useState(() => {
        const saved = storage.login();

        return saved !== null && Date.now() - saved.requestedAt < CODE_LIFETIME_MS ? saved : null;
    });

    const [email, setEmail] = useState(pending?.email ?? '');
    const [step, setStep] = useState<'email' | 'code'>(pending ? 'code' : 'email');
    const [code, setCode] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [notice, setNotice] = useState<string | null>(null);

    if (me) {
        return <Navigate to="/" replace />;
    }

    const requestCode = async () => {
        setBusy(true);
        setError(null);
        setNotice(null);

        try {
            await api.requestLoginCode(email.trim());
            storage.saveLogin({ email: email.trim(), requestedAt: Date.now() });
            setCode('');
            setStep('code');

            return true;
        } catch (failure) {
            setError(explain(failure));

            return false;
        } finally {
            setBusy(false);
        }
    };

    const onEmail = (event: FormEvent) => {
        event.preventDefault();
        void requestCode();
    };

    // For a code that didn't come by email: one printed on the server by `donna:login`.
    // Asking for an emailed code here would cancel it, so this goes straight to the field.
    const haveCode = () => {
        setError(null);
        setNotice(null);
        setCode('');
        setStep('code');
    };

    const onCode = async (event: FormEvent) => {
        event.preventDefault();
        setBusy(true);
        setError(null);
        setNotice(null);

        try {
            const who = await api.verifyCode(email.trim(), code);

            storage.saveLogin(null);
            signedIn(who);
            navigate('/', { replace: true });
        } catch (failure) {
            setError(explain(failure));
            setBusy(false);
        }
    };

    const startOver = () => {
        storage.saveLogin(null);
        setStep('email');
        setCode('');
        setError(null);
        setNotice(null);
    };

    if (step === 'email') {
        return (
            <Gate title="Sign in to Donna">
                <form className="gate__form" onSubmit={onEmail} noValidate>
                    <label htmlFor="email" className="visually-hidden">
                        Email address
                    </label>
                    <input
                        id="email"
                        className="gate__input"
                        type="email"
                        inputMode="email"
                        autoComplete="email"
                        autoCapitalize="none"
                        spellCheck={false}
                        placeholder="Email address"
                        value={email}
                        aria-invalid={error !== null}
                        onChange={(event) => setEmail(event.target.value)}
                        required
                    />
                    <button type="submit" className="button button--wide" disabled={busy || email.trim() === ''}>
                        {busy ? 'Sending…' : 'Email me a code'}
                    </button>
                </form>
                {error !== null && <GateError>{error}</GateError>}
                {error === null && unreachable && <GateError>Can&rsquo;t reach Donna right now.</GateError>}
                <button type="button" className="button button--quiet" disabled={busy || email.trim() === ''} onClick={haveCode}>
                    I already have a code
                </button>
            </Gate>
        );
    }

    const emailed = storage.login() !== null;

    return (
        <Gate title={emailed ? 'Check your email' : 'Enter your code'}>
            <p className="gate__text">
                {emailed
                    ? `If ${email.trim()} is the address Donna knows, a 6-digit code is on its way. It works for 10 minutes.`
                    : 'Type the 6-digit code you were given. It works once, for 10 minutes.'}
            </p>
            <form className="gate__form" onSubmit={onCode} noValidate>
                <label htmlFor="code" className="visually-hidden">
                    6-digit code
                </label>
                <input
                    id="code"
                    className="gate__input gate__input--code"
                    type="text"
                    inputMode="numeric"
                    autoComplete="one-time-code"
                    pattern="[0-9 ]*"
                    maxLength={7}
                    placeholder="000000"
                    value={code}
                    aria-invalid={error !== null}
                    onChange={(event) => setCode(event.target.value.replace(/[^\d ]/g, ''))}
                    autoFocus
                    required
                />
                <button type="submit" className="button button--wide" disabled={busy || code.replace(/\D/g, '').length !== 6}>
                    {busy ? 'Signing in…' : 'Sign in'}
                </button>
            </form>
            {error !== null && <GateError>{error}</GateError>}
            {notice !== null && (
                <p className="gate__text" role="status">
                    {notice}
                </p>
            )}
            <div className="gate__secondary">
                <button
                    type="button"
                    className="button button--quiet"
                    disabled={busy}
                    onClick={async () => (await requestCode()) && setNotice('A new code is on its way. The old one no longer works.')}
                >
                    Send a new code
                </button>
                <button type="button" className="button button--quiet" disabled={busy} onClick={startOver}>
                    Use a different email
                </button>
            </div>
        </Gate>
    );
}
