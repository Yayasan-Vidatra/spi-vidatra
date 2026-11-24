<?php

namespace App\Rules;

use App\Services\FileTypeDetector;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use Illuminate\Translation\PotentiallyTranslatedString;

/**
 * Jenis berkas harus diizinkan dan isinya harus cocok dengan ekstensinya (US-09, Rancangan 6.4).
 */
class AllowedFileContent implements ValidationRule
{
    public function __construct(private FileTypeDetector $detector) {}

    /**
     * Run the validation rule.
     *
     * @param  Closure(string, ?string=): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile || ! $value->isValid()) {
            return;
        }

        $extension = strtolower(pathinfo($value->getClientOriginalName(), PATHINFO_EXTENSION));

        if (! $this->detector->isAllowedExtension($extension)) {
            $allowed = implode(', ', array_map(strtoupper(...), config('berkas.allowed_extensions')));

            $fail("Jenis berkas :attribute tidak diizinkan. Jenis yang diizinkan: {$allowed}.");

            return;
        }

        if ($this->detector->detect($value->getRealPath(), $extension) === null) {
            $fail("Isi berkas :attribute tidak sesuai dengan jenis .{$extension}.");
        }
    }
}
