import { Children, isValidElement, memo, type ReactElement, type ReactNode, useEffect, useRef, useState } from 'react';
import ReactMarkdown, { type Components } from 'react-markdown';
import { CopyIcon } from './icons';

/*
 | Donna's replies are markdown. It is rendered to React elements, never to
 | an HTML string, and any raw HTML in a reply is dropped rather than shown.
 */

export function CodeBlock({ language, text }: { language: string; text: string }) {
    const [copied, setCopied] = useState(false);
    const reset = useRef<ReturnType<typeof setTimeout>>(undefined);

    useEffect(() => () => clearTimeout(reset.current), []);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            clearTimeout(reset.current);
            reset.current = setTimeout(() => setCopied(false), 1600);
        } catch {
            // The browser refused the clipboard; the text can still be selected by hand.
        }
    };

    return (
        <div className="code-block">
            <div className="code-block__bar">
                <span className="code-block__language">{language}</span>
                <button type="button" className="code-block__copy" onClick={copy}>
                    <CopyIcon size={15} />
                    <span aria-live="polite">{copied ? 'Copied' : 'Copy'}</span>
                </button>
            </div>
            <pre>{text}</pre>
        </div>
    );
}

const components: Components = {
    a({ href, children }) {
        return (
            <a href={href} target="_blank" rel="noopener noreferrer">
                {children}
            </a>
        );
    },

    // A fenced block arrives as <pre><code class="language-x">: draw it as a code block with a copy button.
    pre({ children }) {
        const code = Children.toArray(children).find(isValidElement) as ReactElement<{ className?: string; children?: ReactNode }> | undefined;
        const language = /language-(\S+)/.exec(code?.props.className ?? '')?.[1] ?? '';
        const text = String(code?.props.children ?? '').replace(/\n$/, '');

        return <CodeBlock language={language} text={text} />;
    },
};

export const Markdown = memo(function Markdown({ text }: { text: string }) {
    return (
        <div className="markdown">
            <ReactMarkdown components={components} disallowedElements={['img']} skipHtml>
                {text}
            </ReactMarkdown>
        </div>
    );
});
