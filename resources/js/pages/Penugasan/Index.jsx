import { Head, Link } from '@inertiajs/react';
import AppLayout from '../../layouts/AppLayout';

function groupByYear(assignments) {
    return assignments.reduce((groups, assignment) => {
        const last = groups[groups.length - 1];

        if (last && last.year === assignment.year) {
            last.items.push(assignment);
        } else {
            groups.push({ year: assignment.year, items: [assignment] });
        }

        return groups;
    }, []);
}

const tabs = [
    { key: 'aktif', label: 'Aktif', href: '/penugasan' },
    { key: 'arsip', label: 'Arsip', href: '/penugasan?tab=arsip' },
];

export default function Index({ tab, assignments, can }) {
    const groups = groupByYear(assignments);

    return (
        <AppLayout>
            <Head title="Penugasan" />
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">Penugasan</h1>
                {can.create && (
                    <Link
                        href="/penugasan/buat"
                        className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700"
                    >
                        Buat penugasan
                    </Link>
                )}
            </div>

            <nav className="mt-6 flex gap-1 border-b" aria-label="Status penugasan">
                {tabs.map((item) => (
                    <Link
                        key={item.key}
                        href={item.href}
                        className={`-mb-px border-b-2 px-4 py-2 text-sm font-medium ${
                            tab === item.key ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-800'
                        }`}
                    >
                        {item.label}
                    </Link>
                ))}
            </nav>

            {groups.length === 0 ? (
                <p className="mt-8 text-sm text-gray-500">
                    {tab === 'arsip' ? 'Belum ada penugasan yang ditutup.' : 'Belum ada penugasan aktif.'}
                </p>
            ) : (
                groups.map((group) => (
                    <section key={group.year} className="mt-8">
                        <h2 className="text-sm font-semibold uppercase tracking-wide text-gray-500">{group.year}</h2>
                        <ul className="mt-3 divide-y rounded-lg border bg-white">
                            {group.items.map((assignment) => (
                                <li key={assignment.id}>
                                    <Link href={`/penugasan/${assignment.id}`} className="block px-4 py-3 hover:bg-gray-50">
                                        <p className="font-medium">{assignment.code}</p>
                                        <p className="mt-1 text-sm text-gray-600">
                                            {assignment.unit}
                                            {assignment.team_lead && ` · Ketua tim: ${assignment.team_lead}`}
                                            {assignment.closed_at && ` · Ditutup ${assignment.closed_at}`}
                                        </p>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </section>
                ))
            )}
        </AppLayout>
    );
}
