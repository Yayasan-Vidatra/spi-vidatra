import { Form, Head } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

export default function PernyataanKerahasiaan({ statement }) {
    return (
        <AppLayout>
            <Head title="Pernyataan kerahasiaan" />
            <div className="mx-auto max-w-2xl rounded-lg border bg-white p-6 shadow-sm">
                <h1 className="text-xl font-semibold">Pernyataan kerahasiaan</h1>
                <p className="mt-2 text-sm text-gray-600">Baca dan setujui pernyataan berikut sebelum memakai sistem.</p>

                <div className="mt-4 max-h-96 overflow-y-auto whitespace-pre-line rounded-md border bg-gray-50 p-4 text-sm leading-relaxed text-gray-800">
                    {statement.body}
                </div>

                <Form action="/keamanan/pernyataan-kerahasiaan" method="post" className="mt-6">
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="statement_id" value={statement.id} />
                            <label className="flex items-start gap-2 text-sm">
                                <input type="checkbox" name="agreed" value="1" className="mt-0.5" />
                                <span>Saya telah membaca, memahami, dan menyetujui pernyataan kerahasiaan di atas.</span>
                            </label>
                            {errors.agreed && <p className="mt-2 text-sm text-red-600">{errors.agreed}</p>}
                            {errors.statement_id && <p className="mt-2 text-sm text-red-600">{errors.statement_id}</p>}
                            <button
                                type="submit"
                                disabled={processing}
                                className="mt-4 rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                            >
                                Setuju dan lanjutkan
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
