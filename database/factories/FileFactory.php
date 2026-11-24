<?php

namespace Database\Factories;

use App\Enums\Classification;
use App\Enums\ScanStatus;
use App\Models\File;
use App\Models\FileVersion;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<File>
 */
class FileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'folder_id' => Folder::factory(),
            'original_name' => ucfirst(fake()->unique()->words(2, true)).'.pdf',
            'classification' => Classification::Internal,
            'current_version_id' => null,
            'created_by' => User::factory(),
        ];
    }

    public function confidential(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification' => Classification::Rahasia,
        ]);
    }

    /**
     * Give the file a first version and make it current.
     *
     * @param  array<string, mixed>  $versionAttributes
     */
    public function withVersion(ScanStatus $scanStatus = ScanStatus::Bersih, array $versionAttributes = []): static
    {
        return $this->afterCreating(function (File $file) use ($scanStatus, $versionAttributes): void {
            $version = FileVersion::factory()->for($file)->create([
                'scan_status' => $scanStatus,
                'uploaded_by' => $file->created_by,
                ...$versionAttributes,
            ]);

            $file->forceFill(['current_version_id' => $version->id])->save();
        });
    }
}
