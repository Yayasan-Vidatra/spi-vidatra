<?php

namespace App\Http\Requests;

use App\Enums\Classification;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Filter pencarian berkas (US-14). Hak akses dicek di controller dan disaring di query.
 */
class SearchFilesRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:100'],
            'classification' => ['nullable', Rule::enum(Classification::class)],
            'unit_id' => ['nullable', 'integer', 'exists:units,id'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'assignment_id' => ['nullable', 'integer'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'q.max' => 'Kata kunci maksimal 100 karakter.',
            'classification.enum' => 'Label tidak valid.',
            'unit_id.exists' => 'Unit tidak ditemukan.',
            'year.between' => 'Tahun tidak valid.',
        ];
    }

    /**
     * @return array{q: ?string, classification: ?string, unit_id: ?int, year: ?int, assignment_id: ?int}
     */
    public function filters(): array
    {
        $term = trim((string) $this->validated('q'));

        return [
            'q' => $term === '' ? null : $term,
            'classification' => $this->validated('classification'),
            'unit_id' => $this->filled('unit_id') ? (int) $this->validated('unit_id') : null,
            'year' => $this->filled('year') ? (int) $this->validated('year') : null,
            'assignment_id' => $this->filled('assignment_id') ? (int) $this->validated('assignment_id') : null,
        ];
    }
}
