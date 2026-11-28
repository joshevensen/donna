import { describe, expect, it } from 'vitest';
import { COMMANDS, parseCommand } from './commands';

describe('COMMANDS', () => {
    it('is alphabetical, and each command is typed as a slash and its name', () => {
        const texts = COMMANDS.map((command) => command.text);

        expect(texts).toEqual([...texts].sort());
        expect(COMMANDS.every((command) => command.text === `/${command.name}` && command.description !== '')).toBe(true);
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
