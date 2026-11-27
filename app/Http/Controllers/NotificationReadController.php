<?php

namespace App\Http\Controllers;

use App\Services\NotificationLinks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Membuka dan menandai notifikasi sendiri sudah dibaca dari dasbor (US-18). Notifikasi pengguna lain menghasilkan 404.
 */
class NotificationReadController extends Controller
{
    /**
     * Mark the notification read and go to its page; hak akses ke halaman itu dicek ulang saat dibuka.
     */
    public function show(Request $request, string $notification, NotificationLinks $notificationLinks): RedirectResponse
    {
        $user = $request->user();
        $notification = $user->notifications()->findOrFail($notification);
        $notification->markAsRead();

        $target = $notificationLinks->targets($user, collect([$notification]))[$notification->id] ?? null;

        return $target === null ? redirect()->route('dasbor') : redirect()->to($target);
    }

    public function store(Request $request, string $notification): RedirectResponse
    {
        $request->user()->notifications()->findOrFail($notification)->markAsRead();

        return redirect()->route('dasbor');
    }

    public function storeAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return redirect()->route('dasbor');
    }
}
