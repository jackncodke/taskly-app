export type TaskAttachment = {
    id: number;
    name: string;
    size: number;
    is_image: boolean;
    url: string;
};

export type StatusOption = {
    value: string;
    label: string;
};

export type Task = {
    id: number;
    title: string;
    short_description: string | null;
    description: string | null;
    status: string;
    status_label: string;
    /** Formatted as `YYYY-MM-DDTHH:mm` to feed a `datetime-local` input. */
    due_at: string | null;
    /** The same moment, formatted for display. */
    due_at_label: string | null;
    tags: string[];
    attachments: TaskAttachment[];
};
