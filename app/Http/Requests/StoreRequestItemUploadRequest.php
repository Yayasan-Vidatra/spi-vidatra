<?php

namespace App\Http\Requests;

use App\Actions\StoreRequestItemUpload;
use App\Models\File;
use App\Models\RequestItem;
use App\Rules\AllowedFileContent;
use App\Services\Encryption\ConfidentialFileEncryption;
use App\Services\Encryption\FileCipher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Validator;

/**
 * Unggah satu berkas ke slot permintaan (US-16). Aturan unggah umum M3 (jenis dicek dari isi, ukuran dari
 * config) berlaku, ditambah format yang diterima slot.
 */
class StoreRequestItemUploadRequest extends FormRequest
{
    private const int MaxNameLength = 255;

    public function authorize(): bool
    {
        return $this->user()->can('upload', $this->item());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => [
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
            'file.required' => 'Pilih berkas yang akan diunggah.',
            'file.file' => 'Berkas gagal diunggah.',
            'file.uploaded' => "Berkas gagal diunggah. Pastikan ukurannya tidak melebihi {$maxSizeMb} MB.",
            'file.max' => "Ukuran berkas melebihi batas {$maxSizeMb} MB.",
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $upload = $this->file('file');

        return $upload instanceof UploadedFile ? ['file' => File::normalizeName($upload->getClientOriginalName())] : [];
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

                $item = $this->item();
                $extension = strtolower(pathinfo($this->file('file')->getClientOriginalName(), PATHINFO_EXTENSION));

                if (! in_array($extension, $item->accepted_extensions, true)) {
                    $validator->errors()->add('file', sprintf(
                        'Format berkas untuk dokumen ini harus %s (%s).',
                        implode(' atau ', $item->acceptedFormatLabels()),
                        implode(', ', array_map(fn (string $accepted): string => '.'.$accepted, $item->accepted_extensions)),
                    ));

                    return;
                }

                if (mb_strlen($item->slotFileName($extension)) > self::MaxNameLength) {
                    $validator->errors()->add('file', 'Nama dokumen terlalu panjang untuk dijadikan nama berkas.');

                    return;
                }

                $keyIsMissing = ! $this->container->make(FileCipher::class)->isAvailable();

                if ($keyIsMissing && $this->container->make(StoreRequestItemUpload::class)->willBeEncrypted($item)) {
                    $validator->errors()->add('file', ConfidentialFileEncryption::KeyMissingMessage);
                }
            },
        ];
    }

    public function item(): RequestItem
    {
        return $this->route('item');
    }
}
