export type Author = 'josh' | 'donna';

export type MessageStatus = 'pending' | 'complete' | 'failed';

export type AppActionType = 'sign_out' | 'sign_out_everywhere' | 'enable_notifications' | 'disable_notifications';

/** Something a reply's context held: a web page she cited or a piece of memory. */
export interface Source {
    type: string;
    label: string;
    url?: string;
    /** Set on a `file` source: tapping it opens that file. */
    file_id?: number;
}

export type FileKind = 'markdown' | 'csv' | 'text' | 'pdf' | 'image';

/** What code counted in a file: only the fields that apply to its kind are there. */
export interface FileMeta {
    lines?: number;
    rows?: number;
    pages?: number;
    has_text?: boolean;
    width?: number;
    height?: number;
}

/** A file as lists and uploads return it. */
export interface FileItem {
    id: number;
    name: string;
    kind: FileKind;
    mime: string;
    size_bytes: number;
    /** Kept files are the ones Donna finds on her own; the rest are only for the conversation. */
    kept: boolean;
    deleted: boolean;
    extraction: 'ready' | 'failed';
    extraction_error: string | null;
    meta: FileMeta;
    /** The current version's number; null for an image, which has none. */
    version: number | null;
    /** Who made the current version. */
    last_author: 'josh' | 'donna' | null;
    /** Markdown, and small enough to edit in the viewer. */
    editable: boolean;
    created_at: string;
    updated_at: string;
}

/** The first rows of a CSV, parsed by the server. */
export interface FileTable {
    columns: string[];
    rows: string[][];
    total_rows: number;
}

/** One file with what the viewer shows. */
export interface FileDetail extends FileItem {
    /** Markdown, text and PDF text (pages separated by a form feed); null for CSV and images. */
    body: string | null;
    /** The body was cut for viewing: the file is very large. */
    truncated: boolean;
    table: FileTable | null;
    /** The people, projects and topics the file is linked to. */
    subjects?: { type: 'person' | 'project' | 'topic'; name: string }[];
}

export type FileRole = 'attached' | 'created' | 'edited' | 'opened';

/** A file as it rides on a message: what Josh attached, or what Donna created, edited or opened. */
export interface MessageFile {
    id: number;
    name: string;
    kind: FileKind;
    role: FileRole;
    kept: boolean;
    deleted: boolean;
    size_bytes: number;
    meta: FileMeta;
    /** What an edit changed, written by code ("Replaced 14 matches of 'Fibermade Co'"). */
    note: string | null;
}

/** A button Donna offered under a reply. The app does it, on a tap. */
export interface AppAction {
    type: AppActionType;
    label: string;
}

export interface Message {
    id: number;
    author: Author;
    body: string;
    /** Donna's reply as the texts it is shown as; one entry for anything Josh wrote. */
    bubbles: string[];
    status: MessageStatus;
    in_reply_to_id: number | null;
    error: string | null;
    sources: Source[];
    actions: AppAction[];
    /** What Josh attached, and what Donna created, edited or opened while replying. */
    files: MessageFile[];
    /** The key the sending device made for it, so that device can recognise it. */
    client_key: string | null;
    created_at: string;
}

export interface Me {
    name: string;
    /** Josh's timezone. Dates are shown in it on every device, wherever the device is. */
    timezone: string;
    push: {
        configured: boolean;
        public_key: string | null;
    };
}

/** What Donna has cost, in dollars as text ("0.42"), today and this month, by Josh's clock. */
export interface Costs {
    today: string;
    cycle: string;
}

export interface MessagePage {
    data: Message[];
    meta: { next_before?: number | null; next_after?: number | null; failed_jobs?: number; costs?: Costs };
}

export type ProjectTier = 'main' | 'secondary' | 'fun';

export type ProjectStatus = 'active' | 'parked' | 'proposed';

/** A project as /projects lists it. The owner is someone else's name, or null when it's Josh's. */
export interface ProjectSummary {
    id: number;
    name: string;
    tier: ProjectTier | null;
    status: ProjectStatus;
    owner: string | null;
}

/** Someone Donna knows about, as /people lists them. */
export interface PersonSummary {
    id: number;
    name: string;
    relationship: string | null;
}

/** What one period cost, in dollars as text ("0.42"), and what for. */
export interface CostPeriodReport {
    total_usd: string;
    calls: number;
    by_purpose: Record<string, { usd: string; calls: number }>;
}

export interface CostSummary {
    today: CostPeriodReport;
    this_month: CostPeriodReport;
}
