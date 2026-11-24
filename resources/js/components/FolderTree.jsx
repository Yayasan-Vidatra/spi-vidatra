import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import ClassificationBadge from './ClassificationBadge';

const labelNames = { umum: 'Umum', internal: 'Internal', rahasia: 'Rahasia' };
const inputClass = 'rounded-md border px-2 py-1.5 text-sm';
const primaryButton = 'rounded-md bg-gray-900 px-3 py-1.5 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50';
const linkButton = 'text-xs font-medium text-gray-600 hover:text-gray-900 underline';

function descendantIds(folderId, childrenByParent) {
    const ids = new Set([folderId]);
    const stack = [folderId];

    while (stack.length > 0) {
        for (const child of childrenByParent.get(stack.pop()) ?? []) {
            ids.add(child.id);
            stack.push(child.id);
        }
    }

    return ids;
}

function ErrorText({ errors }) {
    const messages = Object.values(errors);

    return messages.length > 0 ? <p className="mt-1 text-sm text-red-600">{messages[0]}</p> : null;
}

function FolderActions({ folder, assignmentId, folders, childrenByParent, onDone }) {
    const [panel, setPanel] = useState(null);
    const [confirmArchive, setConfirmArchive] = useState(false);
    const folderUrl = `/penugasan/${assignmentId}/folder/${folder.id}`;
    const close = () => {
        setPanel(null);
        onDone?.();
    };
    const toggle = (name) => setPanel((current) => (current === name ? null : name));

    const excluded = descendantIds(folder.id, childrenByParent);
    const moveTargets = folders.filter((target) => target.can.createSubfolder && !excluded.has(target.id) && target.id !== folder.parent_id);

    const hasAnyAction = folder.can.createSubfolder || folder.can.rename || folder.can.move || folder.can.changeLabel || folder.can.archive;

    if (!hasAnyAction) {
        return null;
    }

    return (
        <div className="mt-1">
            <div className="flex flex-wrap gap-3">
                {folder.can.createSubfolder && (
                    <button type="button" className={linkButton} onClick={() => toggle('subfolder')}>
                        Subfolder baru
                    </button>
                )}
                {folder.can.rename && (
                    <button type="button" className={linkButton} onClick={() => toggle('rename')}>
                        Ubah nama
                    </button>
                )}
                {folder.can.move && moveTargets.length > 0 && (
                    <button type="button" className={linkButton} onClick={() => toggle('move')}>
                        Pindahkan
                    </button>
                )}
                {folder.can.changeLabel && folder.allowed_labels.length > 1 && (
                    <button type="button" className={linkButton} onClick={() => toggle('label')}>
                        Ubah label
                    </button>
                )}
                {folder.can.archive &&
                    (confirmArchive ? (
                        <Form action={`${folderUrl}/arsip`} method="post" className="inline-flex items-center gap-2" onSuccess={close}>
                            {({ processing }) => (
                                <>
                                    <span className="text-xs text-gray-700">Arsipkan folder ini beserta isinya?</span>
                                    <button type="submit" disabled={processing} className="text-xs font-medium text-red-700 underline">
                                        Ya, arsipkan
                                    </button>
                                    <button type="button" className={linkButton} onClick={() => setConfirmArchive(false)}>
                                        Batal
                                    </button>
                                </>
                            )}
                        </Form>
                    ) : (
                        <button type="button" className={linkButton} onClick={() => setConfirmArchive(true)}>
                            Arsipkan
                        </button>
                    ))}
            </div>

            {panel === 'subfolder' && (
                <Form action={`/penugasan/${assignmentId}/folder`} method="post" className="mt-2 flex flex-wrap items-start gap-2" onSuccess={close}>
                    {({ errors, processing }) => (
                        <>
                            <input type="hidden" name="parent_id" value={folder.id} />
                            <input name="name" placeholder="Nama subfolder" maxLength={100} className={inputClass} autoFocus />
                            <select name="classification" defaultValue={folder.classification} className={inputClass} aria-label="Label">
                                {folder.allowed_child_labels.map((label) => (
                                    <option key={label} value={label}>
                                        {labelNames[label]}
                                    </option>
                                ))}
                            </select>
                            <button type="submit" disabled={processing} className={primaryButton}>
                                Buat
                            </button>
                            <div className="w-full">
                                <ErrorText errors={errors} />
                            </div>
                        </>
                    )}
                </Form>
            )}

            {panel === 'rename' && (
                <Form action={folderUrl} method="patch" className="mt-2 flex flex-wrap items-start gap-2" onSuccess={close}>
                    {({ errors, processing }) => (
                        <>
                            <input name="name" defaultValue={folder.name} maxLength={100} className={inputClass} autoFocus />
                            <button type="submit" disabled={processing} className={primaryButton}>
                                Simpan
                            </button>
                            <div className="w-full">
                                <ErrorText errors={errors} />
                            </div>
                        </>
                    )}
                </Form>
            )}

            {panel === 'move' && (
                <Form action={folderUrl} method="patch" className="mt-2 flex flex-wrap items-start gap-2" onSuccess={close}>
                    {({ errors, processing }) => (
                        <>
                            <select name="parent_id" defaultValue="" className={inputClass} aria-label="Folder tujuan">
                                <option value="" disabled>
                                    Pilih folder tujuan
                                </option>
                                {moveTargets.map((target) => (
                                    <option key={target.id} value={target.id}>
                                        {target.path}
                                    </option>
                                ))}
                            </select>
                            <button type="submit" disabled={processing} className={primaryButton}>
                                Pindahkan
                            </button>
                            <div className="w-full">
                                <ErrorText errors={errors} />
                            </div>
                        </>
                    )}
                </Form>
            )}

            {panel === 'label' && (
                <Form action={folderUrl} method="patch" className="mt-2 flex flex-wrap items-start gap-2" onSuccess={close}>
                    {({ errors, processing }) => (
                        <>
                            <select name="classification" defaultValue={folder.classification} className={inputClass} aria-label="Label">
                                {folder.allowed_labels.map((label) => (
                                    <option key={label} value={label}>
                                        {labelNames[label]}
                                    </option>
                                ))}
                            </select>
                            <button type="submit" disabled={processing} className={primaryButton}>
                                Simpan label
                            </button>
                            <div className="w-full">
                                <ErrorText errors={errors} />
                            </div>
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

function FolderNode({ folder, assignmentId, folders, childrenByParent, selectedFolderId }) {
    const children = childrenByParent.get(folder.id) ?? [];
    const isSelected = folder.id === selectedFolderId;

    return (
        <li>
            <div className={`rounded-md px-2 py-2 ${isSelected ? 'bg-gray-100' : 'hover:bg-gray-50'}`}>
                <div className="flex flex-wrap items-center gap-2">
                    <Link
                        href={`/penugasan/${assignmentId}?folder=${folder.id}`}
                        only={['selectedFolder']}
                        preserveScroll
                        preserveState
                        aria-current={isSelected ? 'true' : undefined}
                        className={`font-medium hover:underline ${isSelected ? 'underline' : ''}`}
                    >
                        {folder.name}
                    </Link>
                    <ClassificationBadge value={folder.classification} label={folder.classification_label} />
                    {folder.is_locked && <span className="text-xs text-gray-500">Terkunci</span>}
                </div>
                <FolderActions folder={folder} assignmentId={assignmentId} folders={folders} childrenByParent={childrenByParent} />
            </div>
            {children.length > 0 && (
                <ul className="ml-4 border-l pl-3">
                    {children.map((child) => (
                        <FolderNode
                            key={child.id}
                            folder={child}
                            assignmentId={assignmentId}
                            folders={folders}
                            childrenByParent={childrenByParent}
                            selectedFolderId={selectedFolderId}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

export default function FolderTree({ folders, assignmentId, selectedFolderId }) {
    const byId = new Map(folders.map((folder) => [folder.id, folder]));
    const childrenByParent = new Map();

    for (const folder of folders) {
        // Folder yang induknya tidak terlihat oleh pengguna tidak dikirim server, jadi tidak ada yatim.
        const parentKey = folder.parent_id !== null && byId.has(folder.parent_id) ? folder.parent_id : null;
        childrenByParent.set(parentKey, [...(childrenByParent.get(parentKey) ?? []), folder]);
    }

    const pathOf = (folder) => {
        const names = [];

        for (let current = folder; current; current = byId.get(current.parent_id)) {
            names.unshift(current.name);
        }

        return names.join(' / ');
    };
    const foldersWithPath = folders.map((folder) => ({ ...folder, path: pathOf(folder) }));

    return (
        <ul className="space-y-1">
            {(childrenByParent.get(null) ?? []).map((folder) => (
                <FolderNode
                    key={folder.id}
                    folder={folder}
                    assignmentId={assignmentId}
                    folders={foldersWithPath}
                    childrenByParent={childrenByParent}
                    selectedFolderId={selectedFolderId}
                />
            ))}
        </ul>
    );
}
