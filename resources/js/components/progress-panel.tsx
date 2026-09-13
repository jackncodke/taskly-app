import Panel from '@/components/panel';
import { FlameIcon, TrophyIcon } from '@/components/icons';
import { cn } from '@/lib/utils';
import type { AchievementBadge, Progress } from '@/types';

const mutedClasses = 'text-[#706f6c] dark:text-[#A1A09A]';
const borderClasses = 'border-[#e3e3e0] dark:border-[#3E3E3A]';

/**
 * How a badge looks, by whether it has been earned.
 *
 * The locked one is an outline: muted grey and a dashed border, so it reads as
 * a shape still to be filled in rather than as something broken. Earning it
 * fills it, which is the whole of the reward, and the badge earned since
 * yesterday is filled in colour — the one warm thing on the panel, and so the
 * first thing the eye lands on after winning it.
 */
function badgeClasses(badge: AchievementBadge): string {
    if (!badge.unlocked) {
        return cn('border-dashed', borderClasses, mutedClasses);
    }

    if (badge.is_recent) {
        return 'border-[#c2a86b] bg-[#f7f1e2] text-[#6b5a2b] dark:border-[#6b5a2b] dark:bg-[#2a2519] dark:text-[#d9c48a]';
    }

    return 'border-[#d4d4d0] bg-[#f2f2f0] text-[#1b1b18] dark:border-[#4a4a45] dark:bg-[#22221f] dark:text-[#EDEDEC]';
}

/**
 * The level, the streak and the badge catalogue.
 *
 * Every number here is counted from the tasks themselves rather than stored, so
 * what the panel shows is always what the board holds. The bar is filled from
 * the percentage the server worked out, the same way the timeline places its
 * dots, rather than from arithmetic repeated here.
 */
export default function ProgressPanel({ progress }: { progress: Progress }) {
    const { level, xp, xp_into_level, xp_for_next_level, streak } = progress;

    return (
        <Panel title="Progresso">
            <div className="flex flex-col gap-5 sm:flex-row sm:items-start sm:gap-8">
                <div className="flex min-w-0 flex-1 flex-col gap-2">
                    <div className="flex items-baseline justify-between gap-3">
                        <span className="text-[15px] font-medium">
                            Nível {level}
                        </span>

                        <span
                            className={cn(
                                'text-[13px] tabular-nums',
                                mutedClasses,
                            )}
                        >
                            {xp} XP
                        </span>
                    </div>

                    <div
                        className={cn(
                            'h-2 overflow-hidden rounded-full border bg-[#f5f5f3] dark:bg-[#1b1b18]',
                            borderClasses,
                        )}
                        role="progressbar"
                        aria-valuenow={xp_into_level}
                        aria-valuemin={0}
                        aria-valuemax={xp_for_next_level}
                        aria-label={`Progresso para o nível ${level + 1}`}
                    >
                        <div
                            className="h-full rounded-full bg-[#1b1b18] dark:bg-[#EDEDEC]"
                            style={{ width: `${progress.level_percent}%` }}
                        />
                    </div>

                    <p className={cn('text-[13px] tabular-nums', mutedClasses)}>
                        {xp_into_level} / {xp_for_next_level} XP para o nível{' '}
                        {level + 1}
                    </p>
                </div>

                <div className="flex shrink-0 gap-6 sm:gap-8">
                    <div className="flex flex-col gap-1">
                        <span className="flex items-center gap-1.5 text-[15px] font-medium">
                            <FlameIcon className="size-4" />
                            {streak} {streak === 1 ? 'dia' : 'dias'}
                        </span>

                        <span className={cn('text-[13px]', mutedClasses)}>
                            {streak === 0
                                ? 'Conclua algo hoje'
                                : 'seguidos concluindo'}
                        </span>

                        <span className={cn('text-[12px]', mutedClasses)}>
                            Recorde: {progress.longest_streak}
                        </span>
                    </div>

                    <div className="flex flex-col gap-1">
                        <span className="text-[15px] font-medium tabular-nums">
                            {progress.completed}
                        </span>

                        <span className={cn('text-[13px]', mutedClasses)}>
                            {progress.completed === 1
                                ? 'tarefa concluída'
                                : 'tarefas concluídas'}
                        </span>
                    </div>
                </div>
            </div>

            <div
                className={cn(
                    'flex flex-wrap gap-2 border-t pt-4',
                    borderClasses,
                )}
            >
                {progress.achievements.map((badge) => (
                    <span
                        key={badge.value}
                        className={cn(
                            'flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[12px]',
                            badgeClasses(badge),
                        )}
                        title={
                            badge.unlocked_at_label
                                ? `${badge.description} — conquistado em ${badge.unlocked_at_label}`
                                : badge.description
                        }
                    >
                        <TrophyIcon className="size-3.5 shrink-0" />
                        {badge.label}
                    </span>
                ))}
            </div>
        </Panel>
    );
}
