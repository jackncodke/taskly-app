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

/**
 * An overdue task, as the alerts panel receives it. It carries its project so
 * the edit form can be opened from a screen where no project is selected.
 */
export type AlertTask = Task & {
    project_id: number;
};

/** One day mark on the timeline's axis. */
export type TimelineDay = {
    label: string;
    date_label: string;
    /** Where the day starts across the window, from 0 to 100. */
    offset: number;
};

/** A task plotted on the timeline: only what the chart draws. */
export type TimelineTask = {
    id: number;
    short_description: string | null;
    status: string;
    status_label: string;
    due_at_label: string | null;
    /** Where the deadline falls across the window, from 0 to 100. */
    offset: number;
};

export type Timeline = {
    days: TimelineDay[];
    tasks: TimelineTask[];
};
