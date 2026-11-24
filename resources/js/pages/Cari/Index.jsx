import { Form, Head, Link } from '@inertiajs/react';
import ClassificationBadge from '../../components/ClassificationBadge';
import Pagination from '../../components/Pagination';
import ScanStatusBadge from '../../components/ScanStatusBadge';
import AppLayout from '../../layouts/AppLayout';

const inputClass = 'rounded-md border px-2 py-1.5 text-sm';

function Filters({ filters, options }) {
    return (
        <Form action="/cari" method="get" className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            {({ errors, processing }) => (
                <>
                    <label className="text-sm lg:col-span-3">
                        <span className="font-medium">Nama berkas</span>
                        <input type="search" name="q" defaultValue={filters.q ?? ''} maxLength={100} className={`${inputClass} mt-1 w-full`} />
                    </label>
                    <label className="text-sm">
                        <span className="font-medium">Label</span>
                        <select name="classification" defaultValue={filters.classification ?? ''} className={`${inputClass} mt-1 w-full`}>
                            <option value="">Semua label</option>
                            {options.classifications.map((classification) => (
                                <option key={classification.value} value={classification.value}>
                                    {classification.label}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="font-medium">Unit</span>
                        <select name="unit_id" defaultValue={filters.unit_id ?? ''} className={`${inputClass} mt-1 w-full`}>
                            <option value="">Semua unit</option>
                            {options.units.map((unit) => (
                                <option key={unit.id} value={unit.id}>
                                    {unit.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm">
                        <span className="font-medium">Tahun</span>
                        <select name="year" defaultValue={filters.year ?? ''} className={`${inputClass} mt-1 w-full`}>
                            <option value="">Semua tahun</option>
                            {options.years.map((year) => (
                                <option key={year} value={year}>
                                    {year}
                                </option>
                            ))}
                        </select>
                    </label>
                    <label className="text-sm sm:col-span-2 lg:col-span-2">
                        <span className="font-medium">Penugasan</span>
                        <select name="assignment_id" defaultValue={filters.assignment_id ?? ''} className={`${inputClass} mt-1 w-full`}>
                            <option value="">Semua penugasan</option>
                            {options.assignments.map((assignment) => (
                                <option key={assignment.id} value={assignment.id}>
                                    {assignment.code}
                                </option>
                            ))}
                        </select>
                    </label>
                    <div className="flex items-end">
                        <button type="submit" disabled={processing} className="rounded-md bg-gray-900 px-4 py-1.5 text-sm font-medium text-white hover:bg-gray-700">
                            Cari
                        </button>
                    </div>
                    {Object.values(errors).length > 0 && <p className="text-sm text-red-600 lg:col-span-3">{Object.values(errors)[0]}</p>}
                </>
            )}
        </Form>
    );
}

export default function Index({ filters, results, options }) {
    return (
        <AppLayout>
            <Head title="Cari berkas" />
            <h1 className="text-2xl font-semibold">Cari berkas</h1>

            <section className="mt-6 rounded-lg border bg-white p-4">
                <Filters key={JSON.stringify(filters)} filters={filters} options={options} />
            </section>

            <section className="mt-6 rounded-lg border bg-white p-4">
                {results === null ? (
                    <p className="text-sm text-gray-500">Isi nama berkas atau pilih filter untuk mulai mencari.</p>
                ) : results.data.length === 0 ? (
                    <p className="text-sm text-gray-500">Tidak ada berkas yang cocok.</p>
                ) : (
                    <>
                        <p className="text-sm text-gray-600">{results.total} berkas ditemukan.</p>
                        <ul className="mt-2 divide-y">
                            {results.data.map((file) => (
                                <li key={file.id} className="py-3 text-sm">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <Link href={`/berkas/${file.id}`} className="break-all font-medium underline">
                                            {file.name}
                                        </Link>
                                        <ClassificationBadge value={file.classification} label={file.classification_label} />
                                        {file.scan_status && <ScanStatusBadge value={file.scan_status} label={file.scan_status_label} />}
                                        {file.is_archived && <span className="text-xs text-gray-500">Folder diarsipkan</span>}
                                    </div>
                                    <p className="mt-1 text-gray-600">
                                        {file.assignment_code ? `${file.assignment_code} · ${file.unit} · ${file.year} · ` : 'Folder umum · '}
                                        {file.folder}
                                        {file.version_number && ` · v${file.version_number}`}
                                    </p>
                                </li>
                            ))}
                        </ul>
                        <Pagination paginator={results} />
                    </>
                )}
            </section>
        </AppLayout>
    );
}
