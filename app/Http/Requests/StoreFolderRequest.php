<?php

namespace App\Http\Requests;

use App\Enums\Classification;
use App\Models\Assignment;
use App\Models\Folder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Hak atas folder induk dan label dicek di controller lewat FolderPolicy setelah induknya diketahui.
 */
class StoreFolderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('manageFolders', $this->assignment());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parent_id' => [
                'required',
                'integer',
                Rule::exists('folders', 'id')->where(fn (Builder $query) => $query
                    ->where('assignment_id', $this->assignment()->id)
                    ->whereNull('archived_at')),
            ],
            'name' => ['required', 'string', 'max:100'],
            'classification' => ['nullable', Rule::enum(Classification::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.required' => 'Pilih folder induk.',
            'parent_id.exists' => 'Folder induk tidak ditemukan di penugasan ini.',
            'name.required' => 'Nama folder wajib diisi.',
            'name.max' => 'Nama folder maksimal 100 karakter.',
            'classification.enum' => 'Label tidak valid.',
        ];
    }

    /**
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $nameIsTaken = Folder::query()
                    ->where('parent_id', $this->integer('parent_id'))
                    ->where('name', $this->string('name')->trim()->toString())
                    ->whereNull('archived_at')
                    ->exists();

                if ($nameIsTaken) {
                    $validator->errors()->add('name', 'Sudah ada folder dengan nama ini di folder induk yang sama.');
                }
            },
        ];
    }

    public function assignment(): Assignment
    {
        return $this->route('assignment');
    }
}
