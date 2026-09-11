import { headerButtonClasses } from '@/components/buttons';
import { BoardIcon, ListIcon } from '@/components/icons';
import type { TaskView } from '@/lib/task-view';
import { cn } from '@/lib/utils';

/**
 * Switches the tasks between the list and the board.
 *
 * Like the theme toggle beside it, this shows the view it will switch *to*,
 * which is what the label announces, so the icon and the action agree.
 */
export default function ViewToggle({
    view,
    onChange,
}: {
    view: TaskView;
    onChange: (view: TaskView) => void;
}) {
    const isBoard = view === 'board';
    const label = isBoard ? 'Ver em lista' : 'Ver em quadro';

    return (
        <button
            type="button"
            onClick={() => onChange(isBoard ? 'list' : 'board')}
            aria-label={label}
            title={label}
            className={cn(
                headerButtonClasses,
                // 2.1rem is 33.6px: 20% larger than the 28px theme button.
                'size-[2.1rem]',
            )}
        >
            {isBoard ? (
                <ListIcon className="size-5" />
            ) : (
                <BoardIcon className="size-5" />
            )}
        </button>
    );
}
