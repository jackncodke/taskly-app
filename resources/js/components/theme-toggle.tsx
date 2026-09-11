import { MoonIcon, SunIcon } from '@/components/icons';
import { useTheme } from '@/lib/theme';

/**
 * Icon-only switch between the light and dark themes.
 *
 * Shows the theme it will switch *to*, which is what the label announces, so
 * the icon and the action never disagree.
 */
export default function ThemeToggle() {
    const [theme, setTheme] = useTheme();

    const isDark = theme === 'dark';
    const label = isDark ? 'Ativar tema claro' : 'Ativar tema escuro';

    return (
        <button
            type="button"
            onClick={() => setTheme(isDark ? 'light' : 'dark')}
            aria-label={label}
            title={label}
            className="rounded-md border border-[#e3e3e0] p-1.5 transition-colors hover:bg-[#f4f4f2] dark:border-[#3E3E3A] dark:hover:bg-[#161615]"
        >
            {isDark ? (
                <SunIcon className="size-4" />
            ) : (
                <MoonIcon className="size-4" />
            )}
        </button>
    );
}
