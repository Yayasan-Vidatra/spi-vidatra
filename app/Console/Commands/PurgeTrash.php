<?php

namespace App\Console\Commands;

use App\Actions\PurgeFile;
use App\Models\File;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Menghapus permanen berkas yang sudah 30 hari di Tempat Sampah (US-13). Dijalankan scheduler setiap hari.
 */
#[Signature('berkas:kosongkan-sampah')]
#[Description('Menghapus permanen berkas yang sudah melewati masa simpan Tempat Sampah')]
class PurgeTrash extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(PurgeFile $purgeFile): int
    {
        $count = 0;

        File::onlyTrashed()
            ->where('deleted_at', '<=', now()->subDays(config('berkas.trash_retention_days')))
            // lazyById: baris yang dihapus tidak menggeser halaman berikutnya.
            ->lazyById()
            ->each(function (File $file) use ($purgeFile, &$count): void {
                $purgeFile->handle($file, isAutomatic: true);
                $count++;
            });

        $this->info("{$count} berkas dihapus permanen.");

        return self::SUCCESS;
    }
}
