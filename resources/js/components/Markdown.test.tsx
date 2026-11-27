import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';
import { Markdown } from './Markdown';

describe('Markdown', () => {
    it('renders lists, inline code and emphasis', () => {
        render(<Markdown text={'Two reasons:\n\n- Fewer **abandoned** carts\n- Rename `checkout.guest`'} />);

        expect(screen.getAllByRole('listitem')).toHaveLength(2);
        expect(screen.getByText('abandoned').tagName).toBe('STRONG');
        expect(screen.getByText('checkout.guest').tagName).toBe('CODE');
    });

    // A reply is model output. Nothing in it may become live HTML.
    it('never lets raw HTML through', () => {
        const { container } = render(
            <Markdown text={'Hello <script>alert(1)</script> <img src=x onerror="alert(2)"> <b>bold</b>\n\n<iframe src="https://example.com"></iframe>\n\n![pixel](https://example.com/p.png)'} />,
        );

        expect(container.querySelector('script, img, iframe, b')).toBeNull();
        expect(container.innerHTML).not.toContain('onerror');
        expect(container.textContent).toContain('Hello');
    });

    it('opens links in a new tab without handing over the opener, and drops script links', () => {
        render(<Markdown text={'[Docs](https://example.com/docs) and [bad](javascript:alert(1))'} />);

        const link = screen.getByRole('link', { name: 'Docs' });

        expect(link.getAttribute('href')).toBe('https://example.com/docs');
        expect(link.getAttribute('target')).toBe('_blank');
        expect(link.getAttribute('rel')).toBe('noopener noreferrer');
        expect(screen.getByText('bad').getAttribute('href') ?? '').not.toContain('javascript');
    });

    it('draws a fenced block with its language and a button that copies the code', async () => {
        const user = userEvent.setup();
        const writeText = vi.spyOn(navigator.clipboard, 'writeText').mockResolvedValue();

        render(<Markdown text={"```php\nit('works', fn () => true);\n```"} />);

        expect(screen.getByText('php')).toBeTruthy();
        expect(screen.getByText("it('works', fn () => true);").tagName).toBe('PRE');

        await user.click(screen.getByRole('button', { name: 'Copy' }));

        expect(writeText).toHaveBeenCalledWith("it('works', fn () => true);");
        expect(screen.getByRole('button', { name: 'Copied' })).toBeTruthy();
    });
});
