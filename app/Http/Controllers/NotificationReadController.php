<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Menandai notifikasi sendiri sudah dibaca dari dasbor (US-18). Notifikasi pengguna lain menghasilkan 404.
 */
class NotificationReadController extends Controller
{
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
