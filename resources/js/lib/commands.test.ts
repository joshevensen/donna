import { describe, expect, it } from 'vitest';
import { parseCommand } from './commands';

describe('parseCommand', () => {
    it.each([
        ['/signout', { name: 'signout', everywhere: false }],
        ['/signout all', { name: 'signout', everywhere: true }],
        ['  /SignOut   ALL  ', { name: 'signout', everywhere: true }],
        ['/notifications', { name: 'notifications' }],
        ['/files', { name: 'files' }],
        ['/help', { name: 'help' }],
    ])('handles %s on the device', (text, command) => {
        expect(parseCommand(text)).toEqual(command);
    });

    // Anything else is a message for Donna, including paths and commands she doesn't have.
    it.each(['/etc/hosts is wrong', '/signout now please', '/good', 'type /help for help', '/files/launch-plan.md', '/'])('sends %s as an ordinary message', (text) => {
        expect(parseCommand(text)).toBeNull();
    });
});
