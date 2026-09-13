/** One badge of the catalogue, earned or still to earn. */
export type AchievementBadge = {
    value: string;
    label: string;
    description: string;
    unlocked: boolean;
    unlocked_at_label: string | null;
    is_recent: boolean;
};

/**
 * Everything the progress panel draws.
 *
 * Null with a project selected: the panel does not render there, and unlike a
 * list of tasks there is no empty value a level could take.
 */
export type Progress = {
    level: number;
    xp: number;
    xp_into_level: number;
    xp_for_next_level: number;
    level_percent: number;
    completed: number;
    on_time: number;
    streak: number;
    longest_streak: number;
    achievements: AchievementBadge[];
};
