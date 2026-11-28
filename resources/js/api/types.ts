export type Author = 'josh' | 'donna';

export type MessageStatus = 'pending' | 'complete' | 'failed';

export type AppActionType = 'sign_out' | 'sign_out_everywhere' | 'enable_notifications' | 'disable_notifications';

/** Something a reply's context held: a web page she cited or a piece of memory. */
export interface Source {
    type: string;
    label: string;
    url?: string;
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
