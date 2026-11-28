import { describe, expect, it } from 'vitest';
import { splitFences } from './fences';

describe('splitFences', () => {
    it('pulls a fenced block out of what Josh typed and leaves the rest alone', () => {
        expect(splitFences('Why does this fail?\n```php\necho 1;\n```\n*not* bold')).toEqual([
            { kind: 'text', text: 'Why does this fail?' },
            { kind: 'code', language: 'php', text: 'echo 1;' },
            { kind: 'text', text: '*not* bold' },
        ]);
    });

    it('treats a block with no language, or fenced with tildes, as code', () => {
        expect(splitFences('```\na\n\nb\n```')).toEqual([{ kind: 'code', language: '', text: 'a\n\nb' }]);
        expect(splitFences('~~~sh\nls\n~~~')).toEqual([{ kind: 'code', language: 'sh', text: 'ls' }]);
    });

    it('keeps a shorter fence inside a longer one as code', () => {
        expect(splitFences('````\n```\ninner\n```\n````')).toEqual([{ kind: 'code', language: '', text: '```\ninner\n```' }]);
    });

    it('leaves a fence that is never closed as plain text', () => {
        expect(splitFences('```\nstill typing')).toEqual([{ kind: 'text', text: '```\nstill typing' }]);
    });
});
