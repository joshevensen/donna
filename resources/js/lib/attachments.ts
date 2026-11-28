import { ApiError } from '../api/client';
import type { FileItem, FileKind, FileMeta } from '../api/types';
import { formatSize } from './files';

/*
 | The files waiting above the input. Each is a chip: uploading, ready, or
 | refused with a reason. This is the whole of what decides whether send is
 | allowed; the component only draws it.
 */

/** The same limits the server enforces, checked here first so a bad file never starts uploading. */
export const MAX_FILE_BYTES = 10 * 1024 * 1024;
export const MAX_FILES = 5;

export const TOO_LARGE = 'Too large. Max 10 MB';
export const UPLOAD_FAILED = "Couldn't upload. Remove it and try again.";

/** An uploaded file as a message carries it until the server has the message. */
export interface AttachedFile {
    id: number;
    name: string;
    kind: FileKind;
    size_bytes: number;
    meta: FileMeta;
}

interface ChipBase {
    /** Made on the device: the chip exists before the server knows the file. */
    key: string;
    name: string;
    size: number;
}

export type Chip =
    | (ChipBase & { state: 'uploading'; progress: number })
    | (ChipBase & { state: 'ready'; file: AttachedFile })
    | (ChipBase & { state: 'error'; reason: string });

interface Picked {
    name: string;
    size: number;
}

/**
 * Add what was just picked. Five chips at most, whatever their state: the
 * rest of the pick is dropped and `overflow` says so. A file over the limit
 * becomes a refused chip at once and is never uploaded.
 *
 * @returns the new chips, and for each picked file the key of the chip that should upload it (null when it shouldn't).
 */
export function addPicked(chips: Chip[], picked: Picked[], makeKey: () => string): { chips: Chip[]; uploads: Array<string | null>; overflow: boolean } {
    const room = Math.max(0, MAX_FILES - chips.length);
    const added: Chip[] = [];
    const uploads: Array<string | null> = [];

    picked.forEach((file, index) => {
        if (index >= room) {
            uploads.push(null);

            return;
        }

        const key = makeKey();

        if (file.size > MAX_FILE_BYTES) {
            added.push({ key, name: file.name, size: file.size, state: 'error', reason: TOO_LARGE });
            uploads.push(null);
        } else {
            added.push({ key, name: file.name, size: file.size, state: 'uploading', progress: 0 });
            uploads.push(key);
        }
    });

    return { chips: [...chips, ...added], uploads, overflow: picked.length > room };
}

/** How far an upload has got, as a fraction. Only ever moves forward. */
export function progressed(chips: Chip[], key: string, fraction: number): Chip[] {
    return chips.map((chip) =>
        chip.key === key && chip.state === 'uploading' ? { ...chip, progress: Math.max(chip.progress, Math.min(1, fraction)) } : chip,
    );
}

export function uploaded(chips: Chip[], key: string, file: FileItem): Chip[] {
    return chips.map((chip) =>
        chip.key === key
            ? {
                  key,
                  name: file.name,
                  size: file.size_bytes,
                  state: 'ready',
                  file: { id: file.id, name: file.name, kind: file.kind, size_bytes: file.size_bytes, meta: file.meta },
              }
            : chip,
    );
}

export function refused(chips: Chip[], key: string, reason: string): Chip[] {
    return chips.map((chip) => (chip.key === key ? { key, name: chip.name, size: chip.size, state: 'error', reason } : chip));
}

export function removed(chips: Chip[], key: string): Chip[] {
    return chips.filter((chip) => chip.key !== key);
}

/** The files a send would carry. */
export function readyFiles(chips: Chip[]): AttachedFile[] {
    return chips.flatMap((chip) => (chip.state === 'ready' ? [chip.file] : []));
}

/** Ready files as chips again, when the app reopens with a draft. */
export function restored(files: AttachedFile[]): Chip[] {
    return files.slice(0, MAX_FILES).map((file) => ({ key: `file:${file.id}`, name: file.name, size: file.size_bytes, state: 'ready', file }));
}

/** A message needs words or a file, and every chip has to be ready to go with it. */
export function canSend(chips: Chip[], text: string): boolean {
    return chips.every((chip) => chip.state === 'ready') && (text.trim() !== '' || chips.length > 0);
}

/** Why send is waiting on the chips, in words; null when they aren't what's stopping it. */
export function blockedReason(chips: Chip[]): string | null {
    const uploading = chips.filter((chip) => chip.state === 'uploading').length;
    const refusedCount = chips.filter((chip) => chip.state === 'error').length;

    if (uploading === 0 && refusedCount === 0) {
        return null;
    }

    const finishes = uploading === 1 ? 'the upload finishes' : 'the uploads finish';
    const removedText = refusedCount === 1 ? 'the refused file is removed' : 'the refused files are removed';

    if (uploading > 0 && refusedCount > 0) {
        return `Send waits until ${finishes} and ${removedText}.`;
    }

    return uploading > 0 ? `Send waits until ${finishes}.` : `Send waits until ${removedText}.`;
}

/** The second line of a chip. */
export function chipLine(chip: Chip): string {
    switch (chip.state) {
        case 'uploading':
            return `Uploading… ${Math.round(chip.progress * 100)}% of ${formatSize(chip.size)}`;
        case 'ready':
            return formatSize(chip.size);
        case 'error':
            return chip.reason;
    }
}

/** Why an upload didn't work, as the chip says it. The server's own words when it refused the file. */
export function refusalReason(error: unknown): string {
    if (error instanceof ApiError) {
        if (error.status === 413) {
            return TOO_LARGE;
        }

        if (error.status === 422) {
            return error.firstMessage;
        }

        if (error.status === 429) {
            return 'Too many uploads at once. Remove it and try again in a minute.';
        }
    }

    return UPLOAD_FAILED;
}
