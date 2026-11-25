import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import FolderFiles from '../../components/FolderFiles';
import FolderTree from '../../components/FolderTree';
import AppLayout from '../../layouts/AppLayout';

const primaryButton = 'rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50';

function ClosureControls({ assignment, can }) {
    const [isConfirming, setIsConfirming] = useState(false);

    if (can.reopen) {
        return (
            <Form action={`/penugasan/${assignment.id}/penutupan`} method="delete">
                {({ processing }) => (
                    <button type="submit" disabled={processing} className="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-100">
                        Buka kembali
                    </button>
                )}
            </Form>
        );
    }

    if (!can.close) {
        return null;
    }

    if (!isConfirming) {
        return (
            <button type="button" onClick={() => setIsConfirming(true)} className="rounded-md border border-red-300 px-3 py-1.5 text-sm font-medium text-red-700 hover:bg-red-50">
                Tutup penugasan
            </button>
        );
    }

    return (
        <Form action={`/penugasan/${assignment.id}/penutupan`} method="post" className="flex flex-wrap items-center gap-2 rounded-md border border-red-200 bg-red-50 p-3">
            {({ processing }) => (
                <>
                    <span className="text-sm text-red-800">
                        Pastikan laporan final sudah ada di folder Laporan. Semua folder akan menjadi baca saja.
                    </span>
                    <button type="submit" disabled={processing} className="rounded-md bg-red-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-red-800">
                        Ya, tutup
                    </button>
                    <button type="button" onClick={() => setIsConfirming(false)} className="text-sm text-gray-700 underline">
                        Batal
                    </button>
                </>
            )}
        </Form>
    );
}

function MemberPanel({ assignment, members, availableAuditors, canManage }) {
    const memberUrl = (member) => `/penugasan/${assignment.id}/auditor/${member.id}`;

    return (
        <section className="rounded-lg border bg-white p-4">
            <h2 className="font-semibold">Auditor</h2>
            {members.length === 0 ? (
                <p className="mt-2 text-sm text-gray-500">Belum ada auditor yang ditunjuk.</p>
            ) : (
                <ul className="mt-3 divide-y">
                    {members.map((member) => (
                        <li key={member.id} className="py-2 text-sm">
                            <p className="font-medium">
                                {member.name}
                                {member.is_team_lead && <span className="ml-2 text-xs font-normal text-gray-500">Ketua tim</span>}
                            </p>
                            <p className="text-gray-500">{member.email}</p>
                            <p className="mt-1 text-xs text-gray-600">
                                {member.can_access_confidential ? 'Boleh melihat folder Rahasia' : 'Tanpa akses Rahasia'}
                            </p>
                            {canManage && (
                                <div className="mt-1 flex gap-3">
                                    <Form action={memberUrl(member)} method="patch">
                                        {({ processing }) => (
                                            <>
                                                <input type="hidden" name="can_access_confidential" value={member.can_access_confidential ? '0' : '1'} />
                                                <button type="submit" disabled={processing} className="text-xs font-medium text-gray-600 underline">
                                                    {member.can_access_confidential ? 'Cabut akses Rahasia' : 'Beri akses Rahasia'}
                                                </button>
                                            </>
                                        )}
                                    </Form>
                                    <Form action={memberUrl(member)} method="delete">
                                        {({ processing }) => (
                                            <button type="submit" disabled={processing} className="text-xs font-medium text-red-700 underline">
                                                Cabut dari penugasan
                                            </button>
                                        )}
                                    </Form>
                                </div>
                            )}
                        </li>
                    ))}
                </ul>
            )}

            {canManage && availableAuditors.length > 0 && (
                <Form action={`/penugasan/${assignment.id}/auditor`} method="post" className="mt-4 space-y-2 border-t pt-4" resetOnSuccess>
                    {({ errors, processing }) => (
                        <>
                            <label htmlFor="user_id" className="text-sm font-medium">
                                Tunjuk auditor
                            </label>
                            <select id="user_id" name="user_id" defaultValue="" className="w-full rounded-md border px-2 py-1.5 text-sm">
                                <option value="" disabled>
                                    Pilih Auditor SPI
                                </option>
                                {availableAuditors.map((auditor) => (
                                    <option key={auditor.id} value={auditor.id}>
                                        {auditor.name}
                                    </option>
                                ))}
                            </select>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" name="can_access_confidential" value="1" />
                                Boleh melihat folder Rahasia
                            </label>
                            {errors.user_id && <p className="text-sm text-red-600">{errors.user_id}</p>}
                            <button type="submit" disabled={processing} className={primaryButton}>
                                Tunjuk
                            </button>
                        </>
                    )}
                </Form>
            )}
        </section>
    );
}

export default function Show({ assignment, folders, selectedFolder, uploadRules, archivedFolders, members, availableAuditors, can }) {
    const isClosed = assignment.status === 'ditutup';

    return (
        <AppLayout>
            <Head title={assignment.code} />
            <Link href={isClosed ? '/penugasan?tab=arsip' : '/penugasan'} className="text-sm text-gray-600 underline">
                Kembali ke daftar penugasan
            </Link>

            <div className="mt-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h1 className="text-2xl font-semibold">{assignment.code}</h1>
                        <span
                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                isClosed ? 'bg-gray-200 text-gray-700' : 'bg-green-100 text-green-800'
                            }`}
                        >
                            {assignment.status_label}
                        </span>
                    </div>
                    <p className="mt-1 text-sm text-gray-600">
                        {assignment.unit} · Mulai {assignment.start_date}
                        {assignment.end_date && ` · Selesai ${assignment.end_date}`}
                        {assignment.team_lead && ` · Ketua tim: ${assignment.team_lead}`}
                    </p>
                    {isClosed && (
                        <p className="mt-2 text-sm text-gray-600">
                            Ditutup {assignment.closed_at}. Semua folder baca saja; hanya Dewan Pengawas yang bisa membuka kembali.
                        </p>
                    )}
                </div>
                <div className="flex flex-wrap items-start gap-2">
                    <Link
                        href={`/penugasan/${assignment.id}/permintaan`}
                        className="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-100"
                    >
                        Permintaan dokumen
                    </Link>
                    {/* key: pasang ulang saat status berubah agar konfirmasi tutup tidak tersisa setelah tutup atau buka kembali. */}
                    <ClosureControls key={assignment.status} assignment={assignment} can={can} />
                </div>
            </div>

            <div className="mt-8 grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="rounded-lg border bg-white p-4">
                        <h2 className="font-semibold">Folder</h2>
                        <div className="mt-3">
                            <FolderTree folders={folders} assignmentId={assignment.id} selectedFolderId={selectedFolder?.id} />
                        </div>
                        {archivedFolders.length > 0 && (
                            <details className="mt-4 text-sm text-gray-600">
                                <summary className="cursor-pointer">Folder diarsipkan ({archivedFolders.length})</summary>
                                <ul className="mt-2 list-disc pl-5">
                                    {archivedFolders.map((folder) => (
                                        <li key={folder.id}>
                                            {folder.name} · diarsipkan {folder.archived_at}
                                        </li>
                                    ))}
                                </ul>
                            </details>
                        )}
                    </section>

                    <section className="rounded-lg border bg-white p-4">
                        <h2 className="font-semibold">Berkas</h2>
                        <div className="mt-3">
                            <FolderFiles folder={selectedFolder} uploadRules={uploadRules} />
                        </div>
                    </section>
                </div>

                <MemberPanel
                    assignment={assignment}
                    members={members}
                    availableAuditors={availableAuditors}
                    canManage={can.manageMembers}
                />
            </div>
        </AppLayout>
    );
}
