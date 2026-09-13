/**
 * The line of small print that closes every page.
 *
 * It repeats the header's border so the page reads as a framed column, and
 * the year is written out rather than taken from the clock: the notice is a
 * fixed statement, not something that should quietly change at midnight.
 */
export default function SiteFooter() {
    return (
        <footer className="border-t border-[#e3e3e0] px-6 py-4 text-center text-[13px] text-[#706f6c] dark:border-[#3E3E3A] dark:text-[#A1A09A]">
            Taskly - Gerenciador de Projetos Pessoais - Todos os direitos
            reservados 2026
        </footer>
    );
}
