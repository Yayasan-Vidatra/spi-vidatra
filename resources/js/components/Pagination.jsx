import { Link } from '@inertiajs/react';

function labelOf(link, index, total) {
    if (index === 0) {
        return '‹ Sebelumnya';
    }

    if (index === total - 1) {
        return 'Berikutnya ›';
    }

    return link.label;
}

/**
 * Navigasi halaman untuk hasil paginate() Laravel.
 */
export default function Pagination({ paginator }) {
    if (!paginator || paginator.last_page <= 1) {
        return null;
    }

    const total = paginator.links.length;

    return (
        <nav className="mt-4 flex flex-wrap items-center gap-1 text-sm" aria-label="Halaman">
            {paginator.links.map((link, index) =>
                link.url ? (
                    <Link
                        key={index}
                        href={link.url}
                        preserveScroll
                        aria-current={link.active ? 'page' : undefined}
                        className={`rounded-md border px-2.5 py-1 ${link.active ? 'bg-gray-900 text-white' : 'bg-white hover:bg-gray-100'}`}
                    >
                        {labelOf(link, index, total)}
                    </Link>
                ) : (
                    <span key={index} className="rounded-md border px-2.5 py-1 text-gray-400">
                        {labelOf(link, index, total)}
                    </span>
                ),
            )}
        </nav>
    );
}
