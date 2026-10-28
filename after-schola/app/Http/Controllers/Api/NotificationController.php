<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use Illuminate\Http\Request;

// Setiap user hanya mengakses notifikasinya sendiri (relasi notifications() milik user yang login),
// jadi notifikasi user lain tidak pernah bisa terambil: id milik orang lain -> 404.
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return NotificationResource::collection($user->notifications()->paginate())
            ->additional(['unread_count' => $user->unreadNotifications()->count()]);
    }

    public function read(Request $request, string $id)
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return new NotificationResource($notification);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'Semua notifikasi ditandai sudah dibaca.']);
    }
}
