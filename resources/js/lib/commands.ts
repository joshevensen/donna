/*
 | Slash commands are handled here, in the app. They never reach the API and
 | cost nothing. Anything else that starts with "/" is an ordinary message.
 */

export type Command = { name: 'signout'; everywhere: boolean } | { name: 'notifications' } | { name: 'files' } | { name: 'help' };

export const HELP = [
    'Things you can type here:',
    '',
    '- `/files` lists your files',
    '- `/signout` signs this device out',
    '- `/signout all` signs every device out',
    '- `/notifications` turns notifications on or off on this device',
    '- `/help` shows this',
    '',
    'Or just ask me. "Sign me out" works too.',
].join('\n');

export function parseCommand(text: string): Command | null {
    const words = text.trim().toLowerCase().split(/\s+/);

    switch (words.join(' ')) {
        case '/signout':
            return { name: 'signout', everywhere: false };
        case '/signout all':
            return { name: 'signout', everywhere: true };
        case '/notifications':
            return { name: 'notifications' };
        case '/files':
            return { name: 'files' };
        case '/help':
            return { name: 'help' };
        default:
            return null;
    }
}
