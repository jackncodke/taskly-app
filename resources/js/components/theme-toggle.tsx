import { headerButtonClasses, headerButtonSize } from '@/components/buttons';
import { MoonIcon, SunIcon } from '@/components/icons';
import { useTheme } from '@/lib/theme';
import { cn } from '@/lib/utils';

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
            className={cn(headerButtonClasses, headerButtonSize)}
        >
            {isDark ? (
                <SunIcon className="size-4" />
            ) : (
                <MoonIcon className="size-4" />
            )}
        </button>
    );
}
