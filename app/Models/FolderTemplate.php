<?php

namespace App\Models;

use App\Enums\Classification;
use App\Enums\FolderPurpose;
use Database\Factories\FolderTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Templat subfolder baku penugasan (Rancangan 9.1). Diisi FolderTemplateSeeder. `purpose` sengaja tidak
 * fillable: menandai subfolder yang dipakai modul permintaan dokumen (FolderPurpose).
 */
#[Fillable(['name', 'description', 'default_classification', 'sort_order'])]
class FolderTemplate extends Model
{
    /** @use HasFactory<FolderTemplateFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'default_classification' => Classification::class,
            'sort_order' => 'integer',
            'purpose' => FolderPurpose::class,
        ];
    }
}
