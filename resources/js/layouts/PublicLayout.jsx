import { Link } from '@inertiajs/react';

export default function PublicLayout({ children }) {
    return (
        <div className="min-h-screen bg-gray-50 text-gray-900">
            <header className="border-b bg-white">
                <div className="mx-auto max-w-4xl px-4 py-4">
                    <Link href="/" className="text-lg font-semibold">Folder Kerja SPI</Link>
                </div>
            </header>
            <main className="mx-auto max-w-4xl px-4 py-8">{children}</main>
            <footer className="mx-auto max-w-4xl px-4 py-6 text-sm text-gray-500">
                Yayasan Vidatra
            </footer>
        </div>
    );
}