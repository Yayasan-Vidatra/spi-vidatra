import { Head, Link } from '@inertiajs/react';
import ClassificationBadge from '../../components/ClassificationBadge';
import FolderFiles from '../../components/FolderFiles';
import AppLayout from '../../layouts/AppLayout';

export default function Index({ folders, selectedFolder, uploadRules }) {
    return (
        <AppLayout>
            <Head title="Regulasi dan Templat" />
            <h1 className="text-2xl font-semibold">Regulasi dan Templat</h1>
            <p className="mt-1 text-sm text-gray-600">Peraturan yayasan, SOP, piagam audit, dan format kerja SPI. Diunggah oleh Dewan Pengawas.</p>

            {folders.length === 0 ? (
                <p className="mt-6 text-sm text-gray-500">Belum ada folder yang bisa Anda lihat.</p>
            ) : (
                <>
                    <nav className="mt-6 flex flex-wrap gap-2 border-b" aria-label="Folder umum">
                        {folders.map((folder) => {
                            const isSelected = folder.id === selectedFolder?.id;

                            return (
                                <Link
                                    key={folder.id}
                                    href={`/folder-umum?folder=${folder.id}`}
                                    only={['selectedFolder']}
                                    preserveState
                                    aria-current={isSelected ? 'page' : undefined}
                                    className={`-mb-px inline-flex items-center gap-2 border-b-2 px-3 py-2 text-sm font-medium ${
                                        isSelected ? 'border-gray-900 text-gray-900' : 'border-transparent text-gray-600 hover:text-gray-900'
                                    }`}
                                >
                                    {folder.name}
                                    <ClassificationBadge value={folder.classification} label={folder.classification_label} />
                                </Link>
                            );
                        })}
                    </nav>

                    <section className="mt-4 rounded-lg border bg-white p-4">
                        <FolderFiles folder={selectedFolder} uploadRules={uploadRules} />
                    </section>
                </>
            )}
        </AppLayout>
    );
}
