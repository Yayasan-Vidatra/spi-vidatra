<?php

namespace App\Models;

use App\Enums\Classification;
use Database\Factories\FolderTemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Templat subfolder baku penugasan (Rancangan 9.1). Diisi FolderTemplateSeeder.
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
        ];
    }
}
