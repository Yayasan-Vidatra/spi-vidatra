import { Head, Link, usePage } from '@inertiajs/react';
import PublicLayout from '../../layouts/PublicLayout';

export default function BelumDiberiAkses() {
    const { flash } = usePage();

    return (
        <PublicLayout>
            <Head title="Belum diberi akses" />
            <div className="mx-auto max-w-md rounded-lg border bg-white p-6 shadow-sm">
                <h1 className="text-xl font-semibold">Akun Anda belum diberi akses</h1>
                <p className="mt-2 text-sm text-gray-600">
                    {flash?.email ? (
                        <>
                            Akun <span className="font-medium text-gray-900">{flash.email}</span> sudah dikenali, tetapi belum memiliki peran
                            di Folder Kerja SPI.
                        </>
                    ) : (
                        'Akun Anda sudah dikenali, tetapi belum memiliki peran di Folder Kerja SPI.'
                    )}
                </p>
                <p className="mt-2 text-sm text-gray-600">Hubungi Admin IT Yayasan untuk meminta akses.</p>
                <Link href="/login" className="mt-6 inline-block text-sm font-medium text-gray-900 underline">
                    Kembali ke halaman masuk
                </Link>
            </div>
        </PublicLayout>
    );
}
