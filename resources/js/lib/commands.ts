/*
 | Slash commands are handled here, in the app. They never reach the API and
 | cost nothing. Anything else that starts with "/" is an ordinary message.
 */

export type CommandName = 'cost' | 'files' | 'people' | 'projects' | 'settings';

export interface CommandInfo {
    name: CommandName;
    /** What's typed: "/cost". */
    text: string;
    description: string;
}

/** Every command, alphabetical, as the command list shows them. */
export const COMMANDS: CommandInfo[] = [
    { name: 'cost', text: '/cost', description: "What I've cost today and this month" },
    { name: 'files', text: '/files', description: 'Your files, kept ones first' },
    { name: 'people', text: '/people', description: 'The people I know about' },
    { name: 'projects', text: '/projects', description: 'Your projects and where they stand' },
    { name: 'settings', text: '/settings', description: 'Notifications and signing out' },
];

/** The command the text is exactly, ignoring case and extra spaces, or null. */
export function parseCommand(text: string): CommandName | null {
    const typed = text.trim().toLowerCase().split(/\s+/).join(' ');

    return COMMANDS.find((command) => command.text === typed)?.name ?? null;
}
