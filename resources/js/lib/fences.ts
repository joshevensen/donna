export type Segment = { kind: 'text'; text: string } | { kind: 'code'; language: string; text: string };

/**
 * Splits something Josh typed into plain text and fenced code blocks. Only fences are
 * recognised; everything else stays exactly as typed, so a `*` or a `#` is never mistaken
 * for formatting. A fence that is never closed is left as text.
 */
export function splitFences(text: string): Segment[] {
    const lines = text.split('\n');
    const segments: Segment[] = [];
    let prose: string[] = [];
    let index = 0;

    const flush = () => {
        // The newline either side of a block is the block's, not the text's.
        const joined = prose.join('\n');

        if (joined.trim() !== '') {
            segments.push({ kind: 'text', text: joined });
        }

        prose = [];
    };

    while (index < lines.length) {
        const open = /^(`{3,}|~{3,})\s*([^\s`]*)[^`]*$/.exec(lines[index]!);
        const close = open ? lines.findIndex((line, at) => at > index && new RegExp(`^${open[1]![0]}{${open[1]!.length},}\\s*$`).test(line)) : -1;

        if (open && close !== -1) {
            flush();
            segments.push({ kind: 'code', language: open[2] ?? '', text: lines.slice(index + 1, close).join('\n') });
            index = close + 1;
        } else {
            prose.push(lines[index]!);
            index++;
        }
    }

    flush();

    return segments;
}
