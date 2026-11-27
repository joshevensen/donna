import '../css/app.css';

import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { BrowserRouter, Navigate, Route, Routes } from 'react-router';
import { Gate, GateError } from './components/Gate';
import { SessionProvider, useSession } from './hooks/useSession';
import { ChatPage } from './pages/ChatPage';
import { LoginLinkPage } from './pages/LoginLinkPage';
import { LoginPage } from './pages/LoginPage';
import { RevokePage } from './pages/RevokePage';

/** The chat, for whoever is signed in; the sign-in page for anyone else. */
function Home() {
    const { me, unreachable } = useSession();

    if (me === undefined) {
        // Nothing is known yet. Say so only if the server can't be reached; otherwise this lasts a moment.
        return unreachable ? (
            <Gate title="Donna">
                <GateError>Can&rsquo;t reach Donna. Trying again…</GateError>
            </Gate>
        ) : (
            <div className="loading" aria-busy="true" />
        );
    }

    // The draft and anything unsent stay on the device, and are there after signing in again.
    return me === null ? <Navigate to="/login" replace /> : <ChatPage me={me} />;
}

export function App() {
    return (
        <SessionProvider>
            <Routes>
                <Route path="/" element={<Home />} />
                <Route path="/login" element={<LoginPage />} />
                <Route path="/login/link/:token" element={<LoginLinkPage />} />
                <Route path="/security/revoke/:token" element={<RevokePage />} />
                <Route path="*" element={<Navigate to="/" replace />} />
            </Routes>
        </SessionProvider>
    );
}

const root = document.getElementById('app');

if (root !== null) {
    createRoot(root).render(
        <StrictMode>
            <BrowserRouter>
                <App />
            </BrowserRouter>
        </StrictMode>,
    );
}
