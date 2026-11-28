/*
 | The handful of icons the app uses, drawn inline so nothing is fetched and
 | the stroke takes the surrounding text color.
 */

interface IconProps {
    size?: number;
}

function Icon({ size = 16, strokeWidth = 2, children }: IconProps & { strokeWidth?: number; children: React.ReactNode }) {
    return (
        <svg
            width={size}
            height={size}
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={strokeWidth}
            strokeLinecap="round"
            strokeLinejoin="round"
            aria-hidden="true"
        >
            {children}
        </svg>
    );
}

export function AlertIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <circle cx="12" cy="12" r="9" />
            <path d="M12 8v5" />
            <path d="M12 16.5v.01" />
        </Icon>
    );
}

export function RetryIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M3 12a9 9 0 0 1 15.5-6.3L21 8" />
            <path d="M21 3v5h-5" />
            <path d="M21 12a9 9 0 0 1-15.5 6.3L3 16" />
            <path d="M3 21v-5h5" />
        </Icon>
    );
}

export function OfflineIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M2 8.8a15 15 0 0 1 4.2-2.6" />
            <path d="M22 8.8a15 15 0 0 0-9.4-4.7" />
            <path d="M5 12.9a10 10 0 0 1 3.5-2" />
            <path d="M19 12.9a10 10 0 0 0-3.3-1.9" />
            <path d="M8.5 16.4a5 5 0 0 1 7 0" />
            <path d="M12 20h.01" />
            <path d="M3 3l18 18" />
        </Icon>
    );
}

export function CopyIcon(props: IconProps) {
    return (
        <Icon strokeWidth={1.8} {...props}>
            <rect x="9" y="9" width="11" height="11" rx="2" />
            <path d="M5 15V6a2 2 0 0 1 2-2h9" />
        </Icon>
    );
}

export function SendIcon(props: IconProps) {
    return (
        <Icon strokeWidth={2.2} {...props}>
            <path d="M12 19V5" />
            <path d="M5 12l7-7 7 7" />
        </Icon>
    );
}

export function PlusIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M12 5v14" />
            <path d="M5 12h14" />
        </Icon>
    );
}

export function CloseIcon(props: IconProps) {
    return (
        <Icon {...props}>
            <path d="M6 6l12 12" />
            <path d="M18 6L6 18" />
        </Icon>
    );
}

export function FileIcon(props: IconProps) {
    return (
        <Icon strokeWidth={1.7} {...props}>
            <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8z" />
            <path d="M14 3v5h5" />
        </Icon>
    );
}

export function PhotoIcon(props: IconProps) {
    return (
        <Icon strokeWidth={1.7} {...props}>
            <rect x="3" y="4" width="18" height="16" rx="2" />
            <circle cx="9" cy="10" r="1.6" />
            <path d="M21 16l-5-5-8 8" />
        </Icon>
    );
}

export function ChevronIcon({ direction, ...props }: IconProps & { direction: 'right' | 'up' | 'down' }) {
    const path = { right: 'M9 6l6 6-6 6', up: 'M6 15l6-6 6 6', down: 'M6 9l6 6 6-6' }[direction];

    return (
        <Icon {...props}>
            <path d={path} />
        </Icon>
    );
}

export function ExternalIcon(props: IconProps) {
    return (
        <Icon strokeWidth={2.2} {...props}>
            <path d="M7 17L17 7" />
            <path d="M8 7h9v9" />
        </Icon>
    );
}
