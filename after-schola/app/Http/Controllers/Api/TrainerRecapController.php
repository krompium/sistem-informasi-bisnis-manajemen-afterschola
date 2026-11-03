<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassSession;
use App\Models\TrainerAttendance;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Rekap kehadiran trainer (check-in) — dipakai di tab "Absensi Trainer"
 * pada halaman Rekap Absensi.
 */
class TrainerRecapController extends Controller
{
    public function index(Request $request)
    {
        $trainers = User::role('trainer')->orderBy('name')->get(['id', 'name']);

        $data = $trainers->map(function (User $trainer) {
            $sessionIds = ClassSession::where('trainer_id', $trainer->id)->pluck('id');
            $totalSessions = $sessionIds->count();
            $checkedIn = TrainerAttendance::whereIn('class_session_id', $sessionIds)->count();

            return [
                'id' => $trainer->id,
                'name' => $trainer->name,
                'total_sessions' => $totalSessions,
                'checked_in' => $checkedIn,
                'rate' => $totalSessions ? round($checkedIn / $totalSessions * 100) : null,
            ];
        });

        return response()->json(['data' => $data]);
    }
}
