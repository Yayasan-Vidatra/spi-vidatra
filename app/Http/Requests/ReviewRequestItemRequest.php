<?php

namespace App\Http\Requests;

use App\Enums\RequestItemStatus;
use App\Models\RequestItem;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Hasil periksa satu slot (US-17): Diterima atau Perlu Perbaikan dengan catatan wajib. `version_id` adalah
 * versi yang dilihat auditor, agar hasil periksa tidak jatuh ke versi baru yang baru saja diunggah PIC.
 */
class ReviewRequestItemRequest extends FormRequest
{
    public const int MaxNoteLength = 1000;

    public function authorize(): bool
    {
        return $this->user()->can('review', $this->item());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'result' => ['required', Rule::enum(RequestItemStatus::class)->only([RequestItemStatus::Diterima, RequestItemStatus::PerluPerbaikan])],
            'note' => ['required', 'string', 'max:'.self::MaxNoteLength],
            'version_id' => ['required', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'result.required' => 'Pilih hasil periksa: Diterima atau Perlu Perbaikan.',
            'result.enum' => 'Hasil periksa harus Diterima atau Perlu Perbaikan.',
            'note.required' => 'Catatan wajib diisi.',
            'note.string' => 'Catatan harus berupa teks.',
            'note.max' => 'Catatan paling panjang '.self::MaxNoteLength.' karakter.',
            'version_id.required' => 'Versi berkas yang diperiksa tidak diketahui. Muat ulang halaman.',
            'version_id.integer' => 'Versi berkas yang diperiksa tidak diketahui. Muat ulang halaman.',
        ];
    }

    public function item(): RequestItem
    {
        return $this->route('item');
    }

    public function result(): RequestItemStatus
    {
        return RequestItemStatus::from($this->validated('result'));
    }
}
