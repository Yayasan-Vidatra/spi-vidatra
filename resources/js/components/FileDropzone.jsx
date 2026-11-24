import { router } from '@inertiajs/react';
import { useRef, useState } from 'react';

const statusText = {
    antre: 'Menunggu giliran',
    mengunggah: 'Mengunggah',
    selesai: 'Terunggah, menunggu pindai antivirus',
    gagal: 'Gagal',
};

function extensionOf(name) {
    const dot = name.lastIndexOf('.');

    return dot === -1 ? '' : name.slice(dot + 1).toLowerCase();
}

/**
 * Satu permintaan per berkas, berurutan: tiap berkas punya progres dan pesan galatnya sendiri, dan
 * satu berkas yang ditolak tidak menggagalkan yang lain. Server tetap memeriksa ulang jenis dan ukuran.
 */
function uploadOne(url, file, onProgress) {
    return new Promise((resolve) => {
        let result = null;

        router.post(
            url,
            { files: [file] },
            {
                forceFormData: true,
                preserveScroll: true,
                preserveState: true,
                onProgress: (progress) => onProgress(progress?.percentage ?? 0),
                onSuccess: () => {
                    result = { ok: true };
                },
                onError: (errors) => {
                    result = { ok: false, error: Object.values(errors)[0] ?? 'Berkas ditolak.' };
                },
                onFinish: () => resolve(result ?? { ok: false, error: 'Unggahan terputus. Coba lagi.' }),
            },
        );
    });
}

export default function FileDropzone({ uploadUrl, rules }) {
    const [items, setItems] = useState([]);
    const [isDragging, setIsDragging] = useState(false);
    const [isUploading, setIsUploading] = useState(false);
    const inputRef = useRef(null);

    const updateItem = (key, changes) => setItems((current) => current.map((item) => (item.key === key ? { ...item, ...changes } : item)));

    const precheck = (file) => {
        if (!rules.allowed_extensions.includes(extensionOf(file.name))) {
            return `Jenis berkas tidak diizinkan. Gunakan ${rules.allowed_extensions.map((ext) => ext.toUpperCase()).join(', ')}.`;
        }

        if (file.size > rules.max_size_mb * 1024 * 1024) {
            return `Ukuran melebihi batas ${rules.max_size_mb} MB.`;
        }

        return null;
    };

    const addFiles = async (fileList) => {
        const files = Array.from(fileList).slice(0, rules.max_files);

        if (files.length === 0 || isUploading) {
            return;
        }

        const batch = files.map((file, index) => {
            const error = precheck(file);

            return { key: `${Date.now()}-${index}-${file.name}`, file, name: file.name, status: error ? 'gagal' : 'antre', progress: 0, error };
        });

        setItems(batch);
        setIsUploading(true);

        for (const item of batch.filter((entry) => entry.status === 'antre')) {
            updateItem(item.key, { status: 'mengunggah' });

            const result = await uploadOne(uploadUrl, item.file, (progress) => updateItem(item.key, { progress }));

            updateItem(item.key, result.ok ? { status: 'selesai', progress: 100 } : { status: 'gagal', error: result.error });
        }

        setIsUploading(false);

        if (inputRef.current) {
            inputRef.current.value = '';
        }
    };

    return (
        <div>
            <div
                onDragOver={(event) => {
                    event.preventDefault();
                    setIsDragging(true);
                }}
                onDragLeave={() => setIsDragging(false)}
                onDrop={(event) => {
                    event.preventDefault();
                    setIsDragging(false);
                    addFiles(event.dataTransfer.files);
                }}
                className={`rounded-lg border-2 border-dashed px-4 py-6 text-center text-sm ${
                    isDragging ? 'border-gray-900 bg-gray-100' : 'border-gray-300 bg-gray-50'
                }`}
            >
                <p className="text-gray-700">Tarik dan lepas berkas ke sini, atau</p>
                <label className="mt-2 inline-block cursor-pointer rounded-md bg-gray-900 px-3 py-1.5 font-medium text-white hover:bg-gray-700">
                    Pilih berkas
                    <input
                        ref={inputRef}
                        type="file"
                        multiple
                        className="sr-only"
                        disabled={isUploading}
                        accept={rules.allowed_extensions.map((ext) => `.${ext}`).join(',')}
                        onChange={(event) => addFiles(event.target.files)}
                    />
                </label>
                <p className="mt-2 text-xs text-gray-500">
                    {rules.allowed_extensions.map((ext) => ext.toUpperCase()).join(', ')} · maks. {rules.max_size_mb} MB per berkas · maks.{' '}
                    {rules.max_files} berkas sekaligus. Nama yang sama di folder ini menjadi versi baru.
                </p>
            </div>

            {items.length > 0 && (
                <ul className="mt-3 divide-y rounded-md border text-sm">
                    {items.map((item) => (
                        <li key={item.key} className="px-3 py-2">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span className="break-all font-medium">{item.name}</span>
                                <span className={item.status === 'gagal' ? 'text-red-700' : 'text-gray-600'}>
                                    {statusText[item.status]}
                                    {item.status === 'mengunggah' && ` ${item.progress}%`}
                                </span>
                            </div>
                            {item.status === 'mengunggah' && (
                                <div className="mt-1 h-1.5 rounded bg-gray-200">
                                    <div className="h-1.5 rounded bg-gray-900" style={{ width: `${item.progress}%` }} />
                                </div>
                            )}
                            {item.error && <p className="mt-1 text-red-700">{item.error}</p>}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
