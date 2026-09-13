export type Project = {
    id: number;
    description: string;
};

/** A project's task counts, keyed by status value, for the overview panel. */
export type ProjectOverview = Project & {
    counts: Record<string, number>;
    total: number;
};
