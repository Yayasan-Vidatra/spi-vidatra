import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '../../layouts/AppLayout';

export default function VerifikasiDuaFaktor() {
    const [isUsingRecoveryCode, setIsUsingRecoveryCode] = useState(false);

    return (
        <AppLayout>
            <Head title="Verifikasi 2FA" />
            <div className="mx-auto max-w-sm rounded-lg border bg-white p-6 shadow-sm">
                <h1 className="text-xl font-semibold">Verifikasi dua faktor</h1>
                <p className="mt-2 text-sm text-gray-600">
                    {isUsingRecoveryCode
                        ? 'Masukkan salah satu kode cadangan Anda. Kode yang dipakai tidak berlaku lagi.'
                        : 'Masukkan kode 6 digit dari aplikasi autentikator Anda.'}
                </p>

                <Form action="/keamanan/dua-faktor/verifikasi" method="post" className="mt-4" resetOnError>
                    {({ errors, processing }) => (
                        <>
                            {isUsingRecoveryCode ? (
                                <>
                                    <label htmlFor="recovery_code" className="text-sm font-medium">
                                        Kode cadangan
                                    </label>
                                    <input
                                        id="recovery_code"
                                        name="recovery_code"
                                        autoComplete="off"
                                        className="mt-1 w-full rounded-md border px-3 py-2 font-mono uppercase"
                                        autoFocus
                                    />
                                    {errors.recovery_code && <p className="mt-2 text-sm text-red-600">{errors.recovery_code}</p>}
                                </>
                            ) : (
                                <>
                                    <label htmlFor="code" className="text-sm font-medium">
                                        Kode autentikasi
                                    </label>
                                    <input
                                        id="code"
                                        name="code"
                                        inputMode="numeric"
                                        autoComplete="one-time-code"
                                        maxLength={6}
                                        className="mt-1 w-full rounded-md border px-3 py-2 font-mono tracking-widest"
                                        autoFocus
                                    />
                                    {errors.code && <p className="mt-2 text-sm text-red-600">{errors.code}</p>}
                                </>
                            )}

                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-4 w-full rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                            >
                                Verifikasi
                            </button>
                        </>
                    )}
                </Form>

                <button
                    type="button"
                    onClick={() => setIsUsingRecoveryCode((value) => !value)}
                    className="mt-4 text-sm text-gray-600 underline"
                >
                    {isUsingRecoveryCode ? 'Pakai kode dari aplikasi' : 'Pakai kode cadangan'}
                </button>
            </div>
        </AppLayout>
    );
}
