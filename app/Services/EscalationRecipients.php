<?php

namespace App\Services;

use App\Enums\RoleSlug;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/**
 * Penerima eskalasi tenggat Piagam (F-21, UC-17): diteruskan ke Dewan Pengawas, dan Kepala SPI diberi tahu.
 */
class EscalationRecipients
{
    /**
     * Semua Dewan Pengawas aktif: pengawas SPI yang menerima eskalasi.
     *
     * @return Collection<int, User>
     */
    public function overseers(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereRelation('role', 'slug', RoleSlug::DewanPengawas)
            ->get();
    }

    /**
     * Kepala SPI yang diberi tahu setiap eskalasi (UC-17 langkah 2).
     *
     * TODO(Langkah 2b): peran Kepala SPI belum ada. Setelah ditambahkan, kembalikan pemegang aktif peran itu di
     * sini; pemanggil sudah mengirim notifikasi yang sama ke mereka tanpa perubahan lain.
     *
     * @return Collection<int, User>
     */
    public function headsOfSpi(): Collection
    {
        return new Collection;
    }

    /**
     * Dewan Pengawas dan Kepala SPI, tanpa duplikat bila satu orang memegang keduanya.
     *
     * @return Collection<int, User>
     */
    public function all(): Collection
    {
        return $this->overseers()->merge($this->headsOfSpi())->unique('id')->values();
    }
}
