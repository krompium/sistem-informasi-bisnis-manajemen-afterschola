<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSessionResource;
use App\Models\ClassSession;
use App\Models\School;
use App\Models\Student;
use App\Models\TrainerAttendance;
use App\Models\User;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return $user->hasRole('trainer') && ! $user->hasRole('management')
            ? $this->trainerDashboard($request)
            : $this->managementDashboard();
    }

    private function trainerDashboard(Request $request)
    {
        $user = $request->user();
        $schoolIds = $user->assignedSchoolIds();

        $todaySessions = ClassSession::with(['school', 'classroom'])
            ->whereIn('school_id', $schoolIds)
            ->whereDate('date', today())
            ->orderBy('meeting_no')
            ->get();

        return response()->json([
            'role' => 'trainer',
            'schools_count' => count($schoolIds),
            'schools' => School::whereIn('id', $schoolIds)->orderBy('name')->get(['id', 'name']),
            'students_count' => Student::whereIn('school_id', $schoolIds)->count(),
            'today_sessions' => ClassSessionResource::collection($todaySessions),
        ]);
    }

    private function managementDashboard()
    {
        $today = today();

        $todaySessions = ClassSession::whereDate('date', $today)->get();

        // Trainer yang punya sesi hari ini tapi belum check-in.
        $trainersWithSession = $todaySessions->pluck('trainer_id')->unique();
        $checkedIn = TrainerAttendance::whereIn(
            'class_session_id', $todaySessions->pluck('id')
        )->pluck('trainer_id')->unique();
        $notCheckedIn = $trainersWithSession->diff($checkedIn);

        return response()->json([
            'role' => 'management',
            'totals' => [
                'schools' => School::count(),
                'students' => Student::count(),
                'trainers' => User::role('trainer')->count(),
            ],
            'today' => [
                'sessions' => $todaySessions->count(),
                'trainers_not_checked_in' => User::whereIn('id', $notCheckedIn)
                    ->get(['id', 'name']),
            ],
        ]);
    }
}
