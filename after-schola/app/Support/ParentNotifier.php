<?php

namespace App\Support;

use App\Models\User;
use App\Notifications\ParentUpdateNotification;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim kabar ke orang tua dari aksi staf (ORT-13). Aksi staf tidak boleh gagal karena notifikasi:
 * kegagalan kirim dicatat (NFR-07) dan notifikasi in-app tetap tersimpan (R-04). Pendaftaran lama tanpa akun orang tua dilewati.
 * TODO(nanti): antrekan (ShouldQueue) bila pengiriman email memperlambat aksi staf.
 */
class ParentNotifier
{
    public function notify(?User $parent, string $kind, string $subject, string $message, array $data = []): void
    {
        if (! $parent) {
            return;
        }

        try {
            $parent->notify(new ParentUpdateNotification($kind, $subject, $message, $data));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim notifikasi ke orang tua.', ['kind' => $kind, 'user_id' => $parent->id, 'error' => $e->getMessage()]);
        }
    }
}
