import { Head, Link, usePage } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

export default function KodeCadangan() {
    const { flash } = usePage();
    const recoveryCodes = flash?.recoveryCodes ?? [];

    return (
        <AppLayout>
            <Head title="Kode cadangan" />
            <div className="mx-auto max-w-lg rounded-lg border bg-white p-6 shadow-sm">
                <h1 className="text-xl font-semibold">Kode cadangan 2FA</h1>

                {recoveryCodes.length > 0 ? (
                    <>
                        <p className="mt-2 text-sm text-gray-600">
                            Simpan kode ini di tempat aman. Setiap kode hanya bisa dipakai sekali untuk masuk jika perangkat autentikator
                            Anda hilang. Kode ini <strong>tidak akan ditampilkan lagi</strong>.
                        </p>
                        <ul className="mt-4 grid grid-cols-2 gap-2 rounded-md bg-gray-100 p-4 font-mono text-sm">
                            {recoveryCodes.map((code) => (
                                <li key={code}>{code}</li>
                            ))}
                        </ul>
                    </>
                ) : (
                    <p className="mt-2 text-sm text-gray-600">
                        Kode cadangan hanya ditampilkan sekali, tepat setelah 2FA diaktifkan.
                    </p>
                )}

                <Link
                    href="/dasbor"
                    className="mt-6 inline-block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                >
                    {recoveryCodes.length > 0 ? 'Saya sudah menyimpan kode ini' : 'Lanjut'}
                </Link>
            </div>
        </AppLayout>
    );
}
