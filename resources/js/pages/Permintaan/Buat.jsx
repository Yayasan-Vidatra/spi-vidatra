import { Form, Head, Link } from '@inertiajs/react';
import { useRef, useState } from 'react';
import AppLayout from '../../layouts/AppLayout';

const inputClass = 'mt-1 w-full rounded-md border px-3 py-2 text-sm';

function FieldError({ message }) {
    return message ? <p className="mt-1 text-sm text-red-600">{message}</p> : null;
}

/**
 * Error for a list field: the field itself or any of its entries, e.g. items.0.formats.1.
 */
function listError(errors, prefix) {
    return errors[prefix] ?? Object.entries(errors).find(([key]) => key.startsWith(`${prefix}.`))?.[1];
}

function ItemFields({ index, formatGroups, selectedFormats, onToggleFormat, errors, canRemove, onRemove }) {
    const prefix = `items[${index}]`;

    return (
        <fieldset className="space-y-3 rounded-md border p-4">
            <div className="flex items-center justify-between">
                <legend className="text-sm font-medium">Dokumen {index + 1}</legend>
                {canRemove && (
                    <button type="button" onClick={onRemove} className="text-xs font-medium text-red-700 underline">
                        Hapus
                    </button>
                )}
            </div>

            <div>
                <label htmlFor={`item-${index}-name`} className="text-sm">
                    Nama dokumen
                </label>
                <input id={`item-${index}-name`} name={`${prefix}[name]`} maxLength={150} placeholder="Laporan kas Oktober" className={inputClass} />
                <FieldError message={errors[`items.${index}.name`]} />
            </div>

            <div>
                <label htmlFor={`item-${index}-description`} className="text-sm">
                    Keterangan (opsional)
                </label>
                <textarea id={`item-${index}-description`} name={`${prefix}[description]`} maxLength={1000} rows={2} className={inputClass} />
                <FieldError message={errors[`items.${index}.description`]} />
            </div>

            <div>
                <p className="text-sm">Format yang diterima</p>
                <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1">
                    {formatGroups.map((group) => (
                        <label key={group.key} className="flex items-center gap-1.5 text-sm">
                            <input
                                type="checkbox"
                                name={`${prefix}[formats][]`}
                                value={group.key}
                                checked={selectedFormats.includes(group.key)}
                                onChange={(event) => onToggleFormat(group.key, event.target.checked)}
                            />
                            {group.label}
                            <span className="text-xs text-gray-500">({group.extensions.map((extension) => `.${extension}`).join(', ')})</span>
                        </label>
                    ))}
                </div>
                <FieldError message={listError(errors, `items.${index}.formats`)} />
            </div>
        </fieldset>
    );
}

/**
 * H-07 Buat Permintaan (US-15): unit tujuan, tenggat, dan daftar dokumen dengan format yang diterima.
 */
export default function Buat({ assignment, units, defaultUnitId, formatGroups, maxItems, today }) {
    const nextRowId = useRef(1);
    const [rows, setRows] = useState([{ id: 0, formats: [] }]);

    const addRow = () => {
        setRows((current) => [...current, { id: nextRowId.current++, formats: [] }]);
    };

    const removeRow = (rowId) => {
        setRows((current) => current.filter((row) => row.id !== rowId));
    };

    // Each format is kept at most once per row, however often its checkbox fires.
    const toggleFormat = (rowId, formatKey, isChecked) => {
        setRows((current) =>
            current.map((row) => {
                if (row.id !== rowId) {
                    return row;
                }

                const others = row.formats.filter((key) => key !== formatKey);

                return { ...row, formats: isChecked ? [...others, formatKey] : others };
            }),
        );
    };

    return (
        <AppLayout>
            <Head title={`Buat permintaan · ${assignment.code}`} />
            <div className="mx-auto max-w-2xl">
                <Link href={`/penugasan/${assignment.id}/permintaan`} className="text-sm text-gray-600 underline">
                    Kembali ke daftar permintaan
                </Link>
                <h1 className="mt-4 text-2xl font-semibold">Buat permintaan dokumen</h1>
                <p className="mt-2 text-sm text-gray-600">
                    Setiap dokumen menjadi slot unggah di folder 02 Permintaan Dokumen. Semua PIC unit tujuan diberi tahu.
                </p>

                <Form action={`/penugasan/${assignment.id}/permintaan`} method="post" className="mt-6 space-y-5 rounded-lg border bg-white p-6">
                    {({ errors, processing }) => (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <label htmlFor="unit_id" className="text-sm font-medium">
                                        Unit tujuan
                                    </label>
                                    <select id="unit_id" name="unit_id" defaultValue={defaultUnitId} className={inputClass}>
                                        {units.map((unit) => (
                                            <option key={unit.id} value={unit.id}>
                                                {unit.code} · {unit.name}
                                                {unit.pic_count === 0 ? ' (belum ada PIC)' : ''}
                                            </option>
                                        ))}
                                    </select>
                                    <FieldError message={errors.unit_id} />
                                </div>
                                <div>
                                    <label htmlFor="due_date" className="text-sm font-medium">
                                        Tenggat
                                    </label>
                                    <input id="due_date" name="due_date" type="date" min={today} className={inputClass} />
                                    <p className="mt-1 text-xs text-gray-500">Berlaku sampai pukul 23.59 WITA.</p>
                                    <FieldError message={errors.due_date} />
                                </div>
                            </div>

                            <div className="space-y-3">
                                <p className="text-sm font-medium">Dokumen yang diminta</p>
                                {rows.map((row, index) => (
                                    <ItemFields
                                        key={row.id}
                                        index={index}
                                        formatGroups={formatGroups}
                                        selectedFormats={row.formats}
                                        onToggleFormat={(formatKey, isChecked) => toggleFormat(row.id, formatKey, isChecked)}
                                        errors={errors}
                                        canRemove={rows.length > 1}
                                        onRemove={() => removeRow(row.id)}
                                    />
                                ))}
                                <FieldError message={errors.items} />
                                {rows.length < maxItems && (
                                    <button type="button" onClick={addRow} className="rounded-md border px-3 py-1.5 text-sm font-medium hover:bg-gray-100">
                                        Tambah dokumen
                                    </button>
                                )}
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700 disabled:opacity-50"
                            >
                                Buat permintaan
                            </button>
                        </>
                    )}
                </Form>
            </div>
        </AppLayout>
    );
}
