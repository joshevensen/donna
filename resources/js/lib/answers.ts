/*
 | The answers to /projects, /people and /cost, written as markdown for a
 | local note. They come straight from the API: no model call, no cost.
 */

import type { CostPeriodReport, CostSummary, PersonSummary, ProjectStatus, ProjectSummary } from '../api/types';

export const ANSWER_FAILED = "I couldn't get that. Check your connection and try again.";

/**
 * A name as plain text. Names and relationships can come from extraction, so
 * one holding markdown must not turn into a link or formatting in her bubble.
 */
function plain(text: string): string {
    return text.replace(/[\\`*_[\]()<>#!|~]/g, '\\$&');
}

const STATUS_GROUPS: { status: ProjectStatus; title: string }[] = [
    { status: 'active', title: 'Active' },
    { status: 'parked', title: 'Parked' },
    { status: 'proposed', title: 'Proposed' },
];

/** Projects grouped Active, Parked, Proposed, each group in the order given. */
export function formatProjects(projects: ProjectSummary[]): string {
    if (projects.length === 0) {
        return 'No projects yet.';
    }

    return STATUS_GROUPS.flatMap(({ status, title }) => {
        const lines = projects.filter((project) => project.status === status).map(projectLine);

        return lines.length === 0 ? [] : [`**${title}**\n\n${lines.join('\n')}`];
    }).join('\n\n');
}

function projectLine(project: ProjectSummary): string {
    let line = `- ${plain(project.name)}`;

    if (project.tier !== null) {
        line += ` — ${project.tier}`;
    }

    if (project.owner !== null) {
        line += ` · ${plain(project.owner)}'s`;
    }

    return line;
}

export function formatPeople(people: PersonSummary[]): string {
    if (people.length === 0) {
        return "I don't know anyone yet.";
    }

    return people.map((person) => (person.relationship !== null ? `- ${plain(person.name)} — ${plain(person.relationship)}` : `- ${plain(person.name)}`)).join('\n');
}

export function formatCosts(costs: CostSummary): string {
    return [costSection('Today', costs.today), costSection('This month', costs.this_month)].join('\n\n');
}

function costSection(title: string, report: CostPeriodReport): string {
    if (report.calls === 0) {
        return `**${title}** — Nothing yet.`;
    }

    const heading = `**${title}** — $${report.total_usd} · ${report.calls} ${report.calls === 1 ? 'call' : 'calls'}`;
    const purposes = Object.entries(report.by_purpose).map(
        ([purpose, { usd, calls }]) => `- ${purpose.charAt(0).toUpperCase()}${purpose.slice(1)} $${usd} · ${calls}`,
    );

    return `${heading}\n\n${purposes.join('\n')}`;
}
