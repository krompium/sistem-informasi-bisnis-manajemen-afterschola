<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CheckInTrainerRequest;
use App\Http\Resources\TrainerAttendanceResource;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Models\TrainerAttendance;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Check-in trainer pada sesi kelas reguler (Modul 7b, TRN-07). Satu kali per sesi, dibuka pada tanggal sesi
 * dan ditutup 4 jam setelah jam selesai (D-07, WIB). Absensi sekolah tetap di TrainerAttendanceController.
 */
class ClassSessionTrainerAttendanceController extends Controller
{
    /** Check-in milik trainer yang login pada sesi ini (null bila belum). Check-in orang lain tidak pernah dikembalikan. */
    public function show(Request $request, ClassSession $classSession)
    {
        $this->authorize('viewAny', ClassGroup::class);
        $this->authorize('viewRegular', $classSession);

        $mine = $classSession->trainerAttendances()->where('trainer_id', $request->user()->id)->first();

        return response()->json(['data' => $mine ? (new TrainerAttendanceResource($mine))->resolve() : null]);
    }

    public function store(CheckInTrainerRequest $request, ClassSession $classSession)
    {
        $trainer = $request->user();

        if ($classSession->trainerAttendances()->where('trainer_id', $trainer->id)->exists()) {
            $this->reject('Anda sudah check-in pada sesi ini.');
        }
        $this->assertWithinWindow($classSession);

        $path = null;
        if ($photo = $request->file('photo')) {
            $path = $photo->storeAs('trainer-attendances', Str::uuid().'.'.$photo->extension(), 'public');
        }

        try {
            $attendance = TrainerAttendance::create([
                'class_session_id' => $classSession->id,
                'trainer_id' => $trainer->id,
                'check_in_at' => now(),
                'status' => $request->input('status'),
                'photo_path' => $path,
                'latitude' => $request->input('latitude'),
                'longitude' => $request->input('longitude'),
                'note' => $request->input('note'),
            ]);
        } catch (UniqueConstraintViolationException) {
            // Dua permintaan bersamaan (mis. tombol ditekan dua kali): yang kedua tidak boleh meninggalkan berkas yatim.
            if ($path) {
                Storage::disk('public')->delete($path);
            }
            $this->reject('Anda sudah check-in pada sesi ini.');
        }

        return (new TrainerAttendanceResource($attendance))->response()->setStatusCode(201);
    }

    private function assertWithinWindow(ClassSession $session): void
    {
        $zone = config('app.display_timezone');
        $date = $session->date->toDateString();
        $opens = Carbon::parse($date, $zone)->startOfDay();
        $closes = Carbon::parse($date.' '.substr($session->end_time, 0, 5), $zone)->addHours(TrainerAttendance::CHECK_IN_GRACE_HOURS);
        $now = now($zone);

        if ($now->lt($opens)) {
            $this->reject('Check-in baru dibuka pada tanggal sesi.');
        }
        if ($now->gt($closes)) {
            $this->reject('Batas check-in (4 jam setelah sesi berakhir) sudah lewat.');
        }
    }

    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['check_in' => [$message]]);
    }
}
