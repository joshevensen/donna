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

export interface MessagePage {
    data: Message[];
    meta: { next_before?: number | null; next_after?: number | null };
}
