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
 * Ubah nama, pindah, atau ubah label. Hak untuk tiap perubahan dicek di controller lewat FolderPolicy.
 */
class UpdateFolderRequest extends FormRequest
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
            'name' => ['required_without_all:parent_id,classification', 'string', 'max:100'],
            'parent_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('folders', 'id')->where(fn (Builder $query) => $query
                    ->where('assignment_id', $this->assignment()->id)
                    ->whereNull('archived_at')),
            ],
            'classification' => ['sometimes', 'required', Rule::enum(Classification::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required_without_all' => 'Tidak ada perubahan yang dikirim.',
            'name.max' => 'Nama folder maksimal 100 karakter.',
            'parent_id.exists' => 'Folder tujuan tidak ditemukan di penugasan ini.',
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
                if ($validator->errors()->isNotEmpty() || ! ($this->has('name') || $this->has('parent_id'))) {
                    return;
                }

                $folder = $this->folder();
                $parentId = $this->has('parent_id') ? $this->integer('parent_id') : $folder->parent_id;
                $name = $this->has('name') ? $this->string('name')->trim()->toString() : $folder->name;

                $nameIsTaken = Folder::query()
                    ->where('assignment_id', $folder->assignment_id)
                    ->where('parent_id', $parentId)
                    ->where('name', $name)
                    ->whereNull('archived_at')
                    ->whereKeyNot($folder->id)
                    ->exists();

                if ($nameIsTaken) {
                    $validator->errors()->add($this->has('name') ? 'name' : 'parent_id', 'Sudah ada folder dengan nama ini di folder tujuan.');
                }
            },
        ];
    }

    public function assignment(): Assignment
    {
        return $this->route('assignment');
    }

    public function folder(): Folder
    {
        return $this->route('folder');
    }
}
