import { Form, Head } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

export default function AktivasiDuaFaktor({ qrCode, secret }) {
    return (
        <AppLayout>
            <Head title="Aktivasi 2FA" />
            <div className="mx-auto max-w-lg rounded-lg border bg-white p-6 shadow-sm">
                <h1 className="text-xl font-semibold">Aktifkan autentikasi dua faktor</h1>
                <p className="mt-2 text-sm text-gray-600">
                    Peran Anda wajib memakai 2FA aplikasi sebelum dapat membuka halaman lain.
                </p>

                <ol className="mt-6 list-decimal space-y-4 pl-5 text-sm text-gray-700">
                    <li>
                        Pindai kode QR ini dengan aplikasi autentikator (Google Authenticator, Microsoft Authenticator, atau sejenisnya).
                        <img src={qrCode} alt="Kode QR 2FA" className="mt-3 h-48 w-48 rounded border bg-white p-2" />
                        <p className="mt-2 text-gray-600">
                            Tidak bisa memindai? Masukkan kunci ini secara manual:
                            <code className="mt-1 block break-all rounded bg-gray-100 px-2 py-1 font-mono text-gray-900">{secret}</code>
                        </p>
                    </li>
                    <li>
                        Masukkan kode 6 digit yang muncul di aplikasi.
                        <Form action="/keamanan/dua-faktor/aktivasi" method="post" className="mt-3" resetOnError>
                            {({ errors, processing }) => (
                                <>
                                    <label htmlFor="code" className="sr-only">
                                        Kode autentikasi
                                    </label>
                                    <input
                                        id="code"
                                        name="code"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        className="w-40 rounded-md border px-3 py-2 font-mono tracking-widest"
                                        autoFocus
                                    />
                                    {errors.code && <p className="mt-2 text-sm text-red-600">{errors.code}</p>}
                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="mt-4 block rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                                    >
                                        Aktifkan 2FA
                                    </button>
                                </>
                            )}
                        </Form>
                    </li>
                </ol>
            </div>
        </AppLayout>
    );
}
