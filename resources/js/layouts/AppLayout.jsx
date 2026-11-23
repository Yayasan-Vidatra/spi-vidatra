import { Link, usePage } from '@inertiajs/react';

export default function AppLayout({ children }) {
    const { props, flash } = usePage();
    const { auth } = props;

    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <header className="border-b bg-white">
                <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-3 px-4 py-4">
                    <nav className="flex items-center gap-5">
                        <Link href="/dasbor" className="text-lg font-semibold">
                            Folder Kerja SPI
                        </Link>
                        {auth.can?.viewAssignments && (
                            <Link href="/penugasan" className="text-sm font-medium text-gray-700 hover:text-gray-900">
                                Penugasan
                            </Link>
                        )}
                    </nav>
                    {auth.user && (
                        <div className="flex items-center gap-4 text-sm">
                            <span className="text-gray-600">
                                {auth.user.name}
                                {auth.user.role && <span className="ml-1 text-gray-400">· {auth.user.role}</span>}
                            </span>
                            <Link
                                href="/logout"
                                method="post"
                                as="button"
                                className="rounded-md border px-3 py-1.5 font-medium hover:bg-gray-100"
                            >
                                Keluar
                            </Link>
                        </div>
                    )}
                </div>
            </header>
            <main className="mx-auto max-w-5xl px-4 py-8">
                {flash?.success && (
                    <div role="status" className="mb-6 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                        {flash.success}
                    </div>
                )}
                {flash?.warning && (
                    <div role="status" className="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                        {flash.warning}
                    </div>
                )}
                {children}
            </main>
            <footer className="mx-auto max-w-5xl px-4 py-6 text-sm text-gray-500">Yayasan Vidatra</footer>
        </div>
    );
}
