<?php

namespace App\Http\Requests;

use App\Models\DocumentRequest;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Ubah tenggat permintaan (US-18): tanggal baru tidak sebelum hari ini dan berbeda dari tenggat sekarang, dengan
 * alasan wajib yang dicatat di log dan dikirim ke PIC. Tenggat di akhir pekan atau hari libur hanya diberi
 * peringatan di formulir, tidak ditolak.
 */
class UpdateDocumentRequestDueDateRequest extends FormRequest
{
    public const int MaxReasonLength = 500;

    public function authorize(): bool
    {
        return $this->user()->can('updateDueDate', $this->documentRequest());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $today = now(config('app.business_timezone'))->toDateString();

        return [
            'due_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$today, 'not_in:'.$this->documentRequest()->due_date->toDateString()],
            'reason' => ['required', 'string', 'max:'.self::MaxReasonLength],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'due_date.required' => 'Tenggat baru wajib diisi.',
            'due_date.date_format' => 'Tenggat tidak valid.',
            'due_date.after_or_equal' => 'Tenggat tidak boleh sebelum hari ini.',
            'due_date.not_in' => 'Tenggat baru sama dengan tenggat sekarang.',
            'reason.required' => 'Alasan perubahan tenggat wajib diisi.',
            'reason.string' => 'Alasan harus berupa teks.',
            'reason.max' => 'Alasan paling panjang '.self::MaxReasonLength.' karakter.',
        ];
    }

    public function documentRequest(): DocumentRequest
    {
        return $this->route('documentRequest');
    }
}
