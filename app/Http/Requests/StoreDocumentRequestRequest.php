<?php

namespace App\Http\Requests;

use App\Models\Assignment;
use App\Models\DocumentRequest;
use App\Models\RequestItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Permintaan dokumen ke satu unit (US-15): unit tujuan, tenggat, dan daftar dokumen dengan format yang
 * diterima. Semua aturan dicek di server, termasuk kelompok format.
 */
class StoreDocumentRequestRequest extends FormRequest
{
    public const int MaxItems = 30;

    public function authorize(): bool
    {
        return $this->user()->can('create', [DocumentRequest::class, $this->assignment()]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = now(config('app.business_timezone'))->toDateString();

        return [
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today],
            'items' => ['required', 'array', 'list', 'min:1', 'max:'.self::MaxItems],
            'items.*' => ['required', 'array:name,description,formats'],
            // Nama dokumen menjadi nama berkas slot, jadi karakter yang tidak boleh ada di nama berkas ditolak.
            'items.*.name' => ['required', 'string', 'max:150', 'distinct:ignore_case', 'not_regex:/[\\/\\\\:*?"<>|\x00-\x1F\x7F]/u'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.formats' => ['required', 'array', 'list', 'min:1'],
            'items.*.formats.*' => ['required', 'string', 'distinct', Rule::in(array_keys(RequestItem::formatGroups()))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'unit_id.required' => 'Pilih unit tujuan.',
            'unit_id.exists' => 'Unit tidak ditemukan.',
            'due_date.required' => 'Tenggat wajib diisi.',
            'due_date.date_format' => 'Tenggat tidak valid.',
            'due_date.after_or_equal' => 'Tenggat tidak boleh sebelum hari ini.',
            'items.required' => 'Isi minimal satu dokumen yang diminta.',
            'items.array' => 'Daftar dokumen tidak valid.',
            'items.list' => 'Daftar dokumen tidak valid.',
            'items.min' => 'Isi minimal satu dokumen yang diminta.',
            'items.max' => 'Satu permintaan paling banyak berisi '.self::MaxItems.' dokumen.',
            'items.*.required' => 'Isian dokumen ke-:position tidak valid.',
            'items.*.array' => 'Isian dokumen ke-:position tidak valid.',
            'items.*.name.required' => 'Nama dokumen ke-:position wajib diisi.',
            'items.*.name.string' => 'Nama dokumen ke-:position tidak valid.',
            'items.*.name.max' => 'Nama dokumen ke-:position maksimal 150 karakter.',
            'items.*.name.distinct' => 'Nama dokumen ke-:position sama dengan dokumen lain di permintaan ini.',
            'items.*.name.not_regex' => 'Nama dokumen ke-:position tidak boleh memuat karakter / \\ : * ? " < > |.',
            'items.*.description.string' => 'Keterangan dokumen ke-:position tidak valid.',
            'items.*.description.max' => 'Keterangan dokumen ke-:position maksimal 1000 karakter.',
            'items.*.formats.required' => 'Pilih minimal satu format untuk dokumen ke-:position.',
            'items.*.formats.array' => 'Format dokumen ke-:position tidak valid.',
            'items.*.formats.list' => 'Format dokumen ke-:position tidak valid.',
            'items.*.formats.min' => 'Pilih minimal satu format untuk dokumen ke-:position.',
            'items.*.formats.*.required' => 'Format dokumen ke-:position tidak valid.',
            'items.*.formats.*.string' => 'Format dokumen ke-:position tidak valid.',
            'items.*.formats.*.distinct' => 'Format yang sama dipilih lebih dari sekali untuk dokumen ke-:position.',
            'items.*.formats.*.in' => 'Format dokumen tidak dikenali.',
        ];
    }

    public function assignment(): Assignment
    {
        return $this->route('assignment');
    }
}
