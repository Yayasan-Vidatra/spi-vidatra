import { Head, Link, usePage } from '@inertiajs/react';
import { formatDateTime } from '../components/fileFormat';
import AppLayout from '../layouts/AppLayout';

const requestNumber = (number) => String(number).padStart(2, '0');

function ListFooter({ total, shown }) {
    return total > shown ? <p className="mt-2 text-xs text-gray-500">Menampilkan {shown} dari {total}.</p> : null;
}

/**
 * Notifikasi terbaru (semua peran), bertaut ke halaman permintaan yang boleh dibuka pengguna.
 */
function Notifications({ notifications, unreadCount }) {
    return (
        <section className="rounded-lg border bg-white p-4">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <h2 className="font-semibold">
                    Notifikasi {unreadCount > 0 && <span className="text-sm font-normal text-gray-600">· {unreadCount} belum dibaca</span>}
                </h2>
                {unreadCount > 0 && (
                    <Link href="/notifikasi/dibaca" method="post" as="button" preserveScroll className="text-sm underline">
                        Tandai semua dibaca
                    </Link>
                )}
            </div>
            {notifications.length === 0 ? (
                <p className="mt-2 text-sm text-gray-600">Belum ada notifikasi.</p>
            ) : (
                <ul className="mt-3 divide-y">
                    {notifications.map((notification) => (
                        <li key={notification.id} className="flex flex-wrap items-start justify-between gap-2 py-2 text-sm">
                            <div className={notification.is_read ? 'text-gray-600' : 'font-medium'}>
                                {notification.url ? (
                                    <Link href={notification.url} className="underline">
                                        {notification.message}
                                    </Link>
                                ) : (
                                    notification.message
                                )}
                                <p className="mt-0.5 text-xs font-normal text-gray-500">{formatDateTime(notification.created_at)}</p>
                            </div>
                            {!notification.is_read && (
                                <Link
                                    href={`/notifikasi/${notification.id}/dibaca`}
                                    method="post"
                                    as="button"
                                    preserveScroll
                                    className="text-xs text-gray-600 underline"
                                >
                                    Tandai dibaca
                                </Link>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

/**
 * Eskalasi tenggat Piagam yang masih Diteruskan (UC-17), untuk Dewan Pengawas sampai slotnya Diterima.
 */
function Escalations({ escalations }) {
    return (
        <section className="rounded-lg border border-red-200 bg-white p-4">
            <h2 className="font-semibold">Eskalasi tenggat Piagam ({escalations.total})</h2>
            <p className="mt-1 text-xs text-gray-600">Slot permintaan yang belum diterima 10 hari kerja setelah tenggat (Piagam Pasal 17 ayat (2)).</p>
            {escalations.items.length === 0 ? (
                <p className="mt-2 text-sm text-gray-600">Tidak ada eskalasi yang terbuka.</p>
            ) : (
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-gray-600">
                            <tr>
                                <th className="py-2 pr-3 font-medium">Dokumen</th>
                                <th className="py-2 pr-3 font-medium">Permintaan</th>
                                <th className="py-2 pr-3 font-medium">Status</th>
                                <th className="py-2 pr-3 font-medium">Tenggat · batas Piagam</th>
                                <th className="py-2 font-medium">Diteruskan</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {escalations.items.map((escalation) => (
                                <tr key={escalation.id}>
                                    <td className="py-2 pr-3">
                                        <Link href={escalation.url} className="font-medium underline">
                                            {escalation.name}
                                        </Link>
                                    </td>
                                    <td className="py-2 pr-3">
                                        {escalation.assignment_code} · Permintaan {requestNumber(escalation.request_number)} · {escalation.unit}
                                    </td>
                                    <td className="py-2 pr-3">
                                        {escalation.status_label}, <span className="text-red-700">{escalation.hold_label}</span>
                                    </td>
                                    <td className="py-2 pr-3 whitespace-nowrap">
                                        {escalation.due_date} · {escalation.charter_deadline}
                                    </td>
                                    <td className="py-2 whitespace-nowrap text-gray-600">{formatDateTime(escalation.escalated_at)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <ListFooter total={escalations.total} shown={escalations.items.length} />
                </div>
            )}
        </section>
    );
}

/**
 * Slot Terlambat (KF-08) di penugasan aktif yang boleh dilihat tim SPI.
 */
function OverdueItems({ overdueItems }) {
    return (
        <section className="rounded-lg border bg-white p-4">
            <h2 className="font-semibold">Slot terlambat ({overdueItems.total})</h2>
            {overdueItems.items.length === 0 ? (
                <p className="mt-2 text-sm text-gray-600">Tidak ada slot yang lewat tenggat.</p>
            ) : (
                <div className="mt-3 overflow-x-auto">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b text-gray-600">
                            <tr>
                                <th className="py-2 pr-3 font-medium">Dokumen</th>
                                <th className="py-2 pr-3 font-medium">Permintaan</th>
                                <th className="py-2 pr-3 font-medium">Status</th>
                                <th className="py-2 font-medium">Tenggat</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {overdueItems.items.map((item) => (
                                <tr key={item.id}>
                                    <td className="py-2 pr-3">
                                        <Link href={item.url} className="font-medium underline">
                                            {item.name}
                                        </Link>
                                    </td>
                                    <td className="py-2 pr-3">
                                        {item.assignment_code} · Permintaan {requestNumber(item.request_number)} · {item.unit}
                                    </td>
                                    <td className="py-2 pr-3">
                                        <span className="text-red-700">Terlambat</span> · {item.status_label}
                                    </td>
                                    <td className="py-2 whitespace-nowrap">
                                        {item.due_date} <span className="text-gray-600">(lewat {item.days_late} hari)</span>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <ListFooter total={overdueItems.total} shown={overdueItems.items.length} />
                </div>
            )}
        </section>
    );
}

/**
 * H-03 Dasbor sementara (US-18): notifikasi, slot terlambat untuk tim SPI, dan eskalasi untuk Dewan Pengawas.
 * Ringkasan lengkap sesuai peran dikerjakan di US-28.
 */
export default function Dasbor({ notifications, overdueItems, escalations }) {
    const { auth } = usePage().props;

    return (
        <AppLayout>
            <Head title="Dasbor" />
            <h1 className="text-2xl font-semibold">Dasbor</h1>
            <p className="mt-2 text-gray-600">
                Selamat datang, {auth.user.name}. Anda masuk sebagai {auth.user.role}.
            </p>

            <div className="mt-6 space-y-6">
                {escalations && <Escalations escalations={escalations} />}
                {overdueItems && <OverdueItems overdueItems={overdueItems} />}
                <Notifications notifications={notifications} unreadCount={auth.user.unread_notification_count} />
            </div>
        </AppLayout>
    );
}
