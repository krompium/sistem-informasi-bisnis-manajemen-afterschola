<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TrainerAttendanceResource;
use App\Models\ClassSession;
use App\Models\TrainerAttendance;
use Illuminate\Http\Request;

class TrainerAttendanceController extends Controller
{
    /**
     * Trainer check-in dirinya pada satu pertemuan.
     * Onsite: foto wajib + GPS. Online: screenshot (tanpa GPS).
     */
    public function checkIn(Request $request, ClassSession $classSession)
    {
        $this->authorize('trainerCheckin', $classSession);

        $isOnsite = $classSession->mode === 'onsite';

        $data = $request->validate([
            'status' => ['required', 'in:hadir,terlambat,tidak_hadir'],
            'photo' => [$isOnsite ? 'required' : 'nullable', 'image', 'max:5120'],
            'latitude' => [$isOnsite ? 'required' : 'nullable', 'numeric', 'between:-90,90'],
            'longitude' => [$isOnsite ? 'required' : 'nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $attributes = [
            'check_in_at' => now(),
            'status' => $data['status'],
            'latitude' => $isOnsite ? $data['latitude'] : null,
            'longitude' => $isOnsite ? $data['longitude'] : null,
            'note' => $data['note'] ?? null,
        ];

        // Simpan foto/screenshot baru bila diupload (jangan hapus yang lama tanpa alasan).
        if ($request->hasFile('photo')) {
            $attributes['photo_path'] = $request->file('photo')->store('trainer-attendances', 'public');
        }

        $attendance = TrainerAttendance::updateOrCreate(
            ['class_session_id' => $classSession->id, 'trainer_id' => $request->user()->id],
            $attributes,
        );

        return (new TrainerAttendanceResource($attendance))->response()->setStatusCode(201);
    }

    public function index(Request $request, ClassSession $classSession)
    {
        $this->authorize('view', $classSession);

        return TrainerAttendanceResource::collection(
            $classSession->trainerAttendances()->with('trainer')->get()
        );
    }
}
