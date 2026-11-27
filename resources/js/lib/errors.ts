import { ApiError, NetworkError } from '../api/client';

/** What to tell Josh when a request on a sign-in screen fails. */
export function explain(error: unknown): string {
    if (error instanceof NetworkError) {
        return "Can't reach Donna. Check your connection and try again.";
    }

    if (error instanceof ApiError) {
        if (error.status === 429) {
            return 'Too many tries. Wait a few minutes, then try again.';
        }

        if (error.status === 422) {
            return error.firstMessage;
        }
    }

    return 'Something went wrong. Try again.';
}
