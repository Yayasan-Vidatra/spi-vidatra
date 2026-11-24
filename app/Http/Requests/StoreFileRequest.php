<?php

namespace App\Http\Requests;

use App\Actions\StoreFileUpload;
use App\Models\File;
use App\Models\Folder;
use App\Rules\AllowedFileContent;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Unggah satu atau beberapa berkas ke folder (US-09). Jenis dicek dari isi berkas, ukuran dari config berkas.
 */
class StoreFileRequest extends FormRequest
{
    private const int MaxNameLength = 255;

    public function authorize(): bool
    {
        return $this->user()->can('uploadFile', $this->folder());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'files' => ['required', 'array', 'max:'.config('berkas.max_files_per_upload')],
            'files.*' => [
                'bail',
                'required',
                'file',
                'max:'.(config('berkas.max_size_mb') * 1024),
                $this->container->make(AllowedFileContent::class),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxSizeMb = config('berkas.max_size_mb');

        return [
            'files.required' => 'Pilih minimal satu berkas.',
            'files.max' => 'Maksimal :max berkas per unggahan.',
            'files.*.required' => 'Berkas :attribute gagal diunggah.',
            'files.*.file' => 'Berkas :attribute gagal diunggah.',
            'files.*.uploaded' => "Berkas :attribute gagal diunggah. Pastikan ukurannya tidak melebihi {$maxSizeMb} MB.",
            'files.*.max' => "Ukuran berkas :attribute melebihi batas {$maxSizeMb} MB.",
        ];
    }

    /**
     * Pesan galat menyebut nama berkas, bukan "files.0".
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return collect($this->uploads())
            ->mapWithKeys(fn (UploadedFile $upload, int|string $index): array => [
                "files.{$index}" => File::normalizeName($upload->getClientOriginalName()),
            ])
            ->all();
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

                foreach ($this->uploads() as $index => $upload) {
                    $name = File::normalizeName($upload->getClientOriginalName());

                    if ($name === '' || mb_strlen($name) > self::MaxNameLength) {
                        $validator->errors()->add("files.{$index}", 'Nama berkas kosong atau lebih dari '.self::MaxNameLength.' karakter.');

                        continue;
                    }

                    $existingFile = File::query()->where('folder_id', $this->folder()->id)->where('original_name', $name)->first();
                    $existingFile?->setRelation('folder', $this->folder());

                    if ($existingFile !== null && $this->user()->cannot('uploadVersion', $existingFile)) {
                        $validator->errors()->add("files.{$index}", StoreFileUpload::BlockedNameMessage);
                    }
                }
            },
        ];
    }

    public function folder(): Folder
    {
        return $this->route('folder');
    }

    /**
     * @return array<int|string, UploadedFile>
     */
    public function uploads(): array
    {
        return array_filter((array) $this->file('files', []), fn (mixed $upload): bool => $upload instanceof UploadedFile);
    }
}
