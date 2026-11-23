<?php

namespace App\Http\Requests;

use App\Enums\RoleSlug;
use App\Models\Assignment;
use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssignmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Assignment::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')],
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'team_lead_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(fn (Builder $query) => $query
                    ->where('is_active', true)
                    ->where('role_id', Role::query()->where('slug', RoleSlug::AuditorSpi)->value('id'))),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => 'Topik penugasan wajib diisi.',
            'title.max' => 'Topik penugasan maksimal 100 karakter.',
            'unit_id.required' => 'Pilih unit yang diaudit.',
            'unit_id.exists' => 'Unit tidak ditemukan.',
            'start_date.required' => 'Tanggal mulai wajib diisi.',
            'start_date.date_format' => 'Tanggal mulai tidak valid.',
            'end_date.date_format' => 'Tanggal selesai tidak valid.',
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
            'team_lead_id.exists' => 'Ketua tim harus Auditor SPI yang aktif.',
        ];
    }
}
