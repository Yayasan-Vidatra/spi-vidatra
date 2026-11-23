import { Form, Head, Link } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

const inputClass = 'mt-1 w-full rounded-md border px-3 py-2 text-sm';

function FieldError({ message }) {
    return message ? <p className="mt-1 text-sm text-red-600">{message}</p> : null;
}

export default function Buat({ units, auditors, today }) {
    return (
        <AppLayout>
            <Head title="Buat penugasan" />
            <div className="mx-auto max-w-xl">
                <Link href="/penugasan" className="text-sm text-gray-600 underline">
                    Kembali ke daftar penugasan
                </Link>
                <h1 className="mt-4 text-2xl font-semibold">Buat penugasan</h1>
                <p className="mt-2 text-sm text-gray-600">
                    Kode dibuat otomatis dengan format tahun-nomor urut-unit-topik, dan tujuh subfolder baku langsung terbentuk.
                </p>

                <Form action="/penugasan" method="post" className="mt-6 space-y-5 rounded-lg border bg-white p-6">
                    {({ errors, processing }) => (
                        <>
                            <div>
                                <label htmlFor="unit_id" className="text-sm font-medium">
                                    Unit yang diaudit
                                </label>
                                <select id="unit_id" name="unit_id" defaultValue="" className={inputClass}>
                                    <option value="" disabled>
                                        Pilih unit
                                    </option>
                                    {units.map((unit) => (
                                        <option key={unit.id} value={unit.id}>
                                            {unit.code} · {unit.name}
                                        </option>
                                    ))}
                                </select>
                                <FieldError message={errors.unit_id} />
                            </div>

                            <div>
                                <label htmlFor="title" className="text-sm font-medium">
                                    Topik singkat
                                </label>
                                <input id="title" name="title" maxLength={100} placeholder="Audit Keuangan Semester 1" className={inputClass} />
                                <FieldError message={errors.title} />
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="start_date" className="text-sm font-medium">
                                        Tanggal mulai
                                    </label>
                                    <input id="start_date" name="start_date" type="date" defaultValue={today} className={inputClass} />
                                    <p className="mt-1 text-xs text-gray-500">Menentukan tahun dan nomor urut.</p>
                                    <FieldError message={errors.start_date} />
                                </div>
                                <div>
                                    <label htmlFor="end_date" className="text-sm font-medium">
                                        Tanggal selesai (opsional)
                                    </label>
                                    <input id="end_date" name="end_date" type="date" className={inputClass} />
                                    <FieldError message={errors.end_date} />
                                </div>
                            </div>

                            <div>
                                <label htmlFor="team_lead_id" className="text-sm font-medium">
                                    Ketua tim (opsional)
                                </label>
                                <select id="team_lead_id" name="team_lead_id" defaultValue="" className={inputClass}>
                                    <option value="">Belum ditentukan</option>
                                    {auditors.map((auditor) => (
                                        <option key={auditor.id} value={auditor.id}>
                                            {auditor.name} ({auditor.email})
                                        </option>
                                    ))}
                                </select>
                                <p className="mt-1 text-xs text-gray-500">Ketua tim otomatis ditunjuk sebagai auditor penugasan.</p>
                                <FieldError message={errors.team_lead_id} />
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                            >
                                Buat penugasan
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
