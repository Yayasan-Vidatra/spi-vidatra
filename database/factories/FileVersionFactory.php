<?php

namespace Database\Factories;

use App\Enums\ScanStatus;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FileVersion>
 */
class FileVersionFactory extends Factory
{
    /**
     * Define the model's default state: a clean PDF version. The content itself is not stored on disk.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_id' => File::factory(),
            'version_number' => fn (array $attributes): int => ((int) FileVersion::query()->where('file_id', $attributes['file_id'])->max('version_number')) + 1,
            'stored_name' => fn (): string => (string) Str::uuid(),
            'storage_path' => fn (array $attributes): string => 'versi/'.$attributes['stored_name'],
            'size' => 1024,
            'mime_type' => 'application/pdf',
            'sha256' => hash('sha256', fake()->uuid()),
            'is_encrypted' => false,
            'scan_status' => ScanStatus::Bersih,
            'scanned_at' => now(),
            'scan_signature' => null,
            'restored_from_version_id' => null,
            'uploaded_by' => User::factory(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'scan_status' => ScanStatus::Menunggu,
            'scanned_at' => null,
        ]);
    }
}
