import { cn } from '@/lib/utils';

/**
 * The colours a tag can take.
 *
 * Every class is written out in full on purpose. Tailwind only emits the
 * classes it can find as literal text in the source, so building a name from
 * parts — `bg-${hue}-100` — would leave these rules out of the stylesheet and
 * the chips would silently render unstyled.
 *
 * Only cool hues are used — greens through blues to purples — so the tags stay
 * quiet next to the warm reds and ambers the interface reserves for state:
 * a cancelled task, a validation error, a destructive button. A tag should not
 * borrow the colour of a warning.
 *
 * Each entry pairs a pale fill with dark text of the same hue, which keeps the
 * text readable against the fill.
 *
 * The border is deliberately several steps darker than the fill. Chips sit on
 * status-tinted cards, and a chip that happens to share the card's hue has
 * almost no contrast against it — measured at 1.02 with a pale border. The
 * darker edge is what keeps such a chip looking like a chip.
 */
const palette = [
    'border-slate-400 bg-slate-100 text-slate-800 dark:border-slate-700 dark:bg-slate-950/60 dark:text-slate-300',
    'border-emerald-400 bg-emerald-100 text-emerald-800 dark:border-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300',
    'border-teal-400 bg-teal-100 text-teal-800 dark:border-teal-700 dark:bg-teal-950/60 dark:text-teal-300',
    'border-cyan-400 bg-cyan-100 text-cyan-800 dark:border-cyan-700 dark:bg-cyan-950/60 dark:text-cyan-300',
    'border-sky-400 bg-sky-100 text-sky-800 dark:border-sky-700 dark:bg-sky-950/60 dark:text-sky-300',
    'border-blue-400 bg-blue-100 text-blue-800 dark:border-blue-700 dark:bg-blue-950/60 dark:text-blue-300',
    'border-indigo-400 bg-indigo-100 text-indigo-800 dark:border-indigo-700 dark:bg-indigo-950/60 dark:text-indigo-300',
    'border-violet-400 bg-violet-100 text-violet-800 dark:border-violet-700 dark:bg-violet-950/60 dark:text-violet-300',
    'border-purple-400 bg-purple-100 text-purple-800 dark:border-purple-700 dark:bg-purple-950/60 dark:text-purple-300',
];

/**
 * Pick a colour from the tag's own text.
 *
 * Deliberately a hash rather than a random draw: the colour has to survive a
 * re-render, a page reload and the same tag appearing on another task. A real
 * random pick would repaint the chips on every keystroke that re-renders the
 * list, which reads as a bug rather than as decoration.
 *
 * Case and surrounding space are ignored so "Bug" and "bug " land on the same
 * colour.
 */
function paletteFor(tag: string): string {
    const normalized = tag.trim().toLowerCase();

    let hash = 0;

    for (let index = 0; index < normalized.length; index += 1) {
        // `| 0` keeps the running value a 32-bit int instead of drifting into
        // the range where doubles lose precision.
        hash = (hash * 31 + normalized.charCodeAt(index)) | 0;
    }

    return palette[Math.abs(hash) % palette.length];
}

export default function TagChip({ tag }: { tag: string }) {
    return (
        <span
            className={cn(
                'inline-block rounded-full border px-2 py-0.5 text-[12px] font-medium',
                paletteFor(tag),
            )}
        >
            {tag}
        </span>
    );
}
