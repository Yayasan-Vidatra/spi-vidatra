<?php

namespace App\Http\Requests;

use App\Enums\Classification;
use App\Models\File;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Ubah label berkas (US-07). Hak atas label tujuan dicek di controller lewat FilePolicy.
 */
class UpdateFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('changeLabel', $this->targetFile());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'classification' => ['required', Rule::enum(Classification::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'classification.required' => 'Pilih label.',
            'classification.enum' => 'Label tidak valid.',
        ];
    }

    public function targetFile(): File
    {
        return $this->route('file');
    }
}
