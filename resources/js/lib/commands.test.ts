import { describe, expect, it } from 'vitest';
import { COMMANDS, parseCommand } from './commands';

describe('COMMANDS', () => {
    it('lists every command alphabetically, each with its one-line description', () => {
        expect(COMMANDS).toEqual([
            { name: 'cost', text: '/cost', description: "What I've cost today and this month" },
            { name: 'people', text: '/people', description: 'The people I know about' },
            { name: 'projects', text: '/projects', description: 'Your projects and where they stand' },
            { name: 'settings', text: '/settings', description: 'Notifications and signing out' },
        ]);
    });
});

describe('parseCommand', () => {
    it.each([
        ['/cost', 'cost'],
        ['/people', 'people'],
        ['/projects', 'projects'],
        ['/settings', 'settings'],
        ['  /COST  ', 'cost'],
        ['/Projects\n', 'projects'],
        ['\t/SeTTings ', 'settings'],
    ])('handles %j on the device', (text, command) => {
        expect(parseCommand(text)).toBe(command);
    });

    // Anything else is a message for Donna, including the commands that were removed and paths.
    it.each([
        '/help',
        '/signout',
        '/signout all',
        '/notifications',
        '/',
        '/cost please',
        '/files',
        '/etc/hosts is wrong',
        'what is /cost',
        'cost',
    ])('sends %j as an ordinary message', (text) => {
        expect(parseCommand(text)).toBeNull();
    });
});
