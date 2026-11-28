import { describe, expect, it } from 'vitest';
import type { CostSummary, PersonSummary, ProjectSummary } from '../api/types';
import { formatCosts, formatPeople, formatProjects } from './answers';

describe('formatProjects', () => {
    it('says so when there are no projects', () => {
        expect(formatProjects([])).toBe('No projects yet.');
    });

    it('groups them Active, Parked, Proposed, with the tier and anyone else who owns them', () => {
        const projects: ProjectSummary[] = [
            { id: 1, name: 'Fibermade', tier: 'main', status: 'active', owner: null },
            { id: 2, name: 'Garage gym', tier: null, status: 'proposed', owner: 'Kristen' },
            { id: 3, name: 'Kitchen remodel', tier: 'secondary', status: 'active', owner: 'Kristen' },
            { id: 4, name: 'Side thing', tier: null, status: 'parked', owner: null },
            { id: 5, name: 'Storyrift', tier: 'fun', status: 'parked', owner: null },
        ];

        expect(formatProjects(projects)).toBe(
            [
                '**Active**\n\n- Fibermade — main\n- Kitchen remodel — secondary · Kristen\'s',
                '**Parked**\n\n- Side thing\n- Storyrift — fun',
                '**Proposed**\n\n- Garage gym · Kristen\'s',
            ].join('\n\n'),
        );
    });

    it('leaves out a group with nothing in it', () => {
        const projects: ProjectSummary[] = [
            { id: 4, name: 'Storyrift', tier: 'fun', status: 'parked', owner: null },
            { id: 2, name: 'Garage gym', tier: null, status: 'proposed', owner: null },
        ];

        expect(formatProjects(projects)).toBe('**Parked**\n\n- Storyrift — fun\n\n**Proposed**\n\n- Garage gym');
    });
});

describe('formatPeople', () => {
    it('says so when Donna knows no one', () => {
        expect(formatPeople([])).toBe("I don't know anyone yet.");
    });

    it('lists each person with their relationship, or just the name when it is unknown', () => {
        const people: PersonSummary[] = [
            { id: 3, name: 'Kristen', relationship: 'wife' },
            { id: 4, name: 'Sam', relationship: null },
            { id: 5, name: 'Tom', relationship: 'brother' },
        ];

        expect(formatPeople(people)).toBe('- Kristen — wife\n- Sam\n- Tom — brother');
    });
});

describe('formatCosts', () => {
    const nothing = { total_usd: '0.00', calls: 0, by_purpose: {} };

    it('says "Nothing yet." for a period with no calls', () => {
        const costs: CostSummary = {
            today: nothing,
            this_month: { total_usd: '0.42', calls: 18, by_purpose: { embedding: { usd: '0.01', calls: 3 }, reply: { usd: '0.41', calls: 15 } } },
        };

        expect(formatCosts(costs)).toBe('**Today** — Nothing yet.\n\n**This month** — $0.42 · 18 calls\n\n- Embedding $0.01 · 3\n- Reply $0.41 · 15');
    });

    it('says "1 call", not "1 calls"', () => {
        const once = { total_usd: '0.01', calls: 1, by_purpose: { tagging: { usd: '0.01', calls: 1 } } };

        expect(formatCosts({ today: once, this_month: once })).toBe(
            '**Today** — $0.01 · 1 call\n\n- Tagging $0.01 · 1\n\n**This month** — $0.01 · 1 call\n\n- Tagging $0.01 · 1',
        );
    });
});
