<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParentScheduleResource;
use App\Models\ClassSession;
use App\Models\Enrollment;
use Illuminate\Http\Request;

/**
 * Portal Orang Tua (ORT-10): jadwal kelas anak yang sedang aktif (read-only). Hanya anak sendiri lewat students.parent_id;
 * role "parent" dijaga middleware route.
 */
class ParentScheduleController extends Controller
{
    private const SESSIONS_PER_CLASS = 30;

    public function index(Request $request)
    {
        $enrollments = Enrollment::where('status', 'aktif')
            ->whereHas('student', fn ($q) => $q->where('parent_id', $request->user()->id))
            ->with('student:id,name', 'classGroup.program:id,name', 'classGroup.level:id,name', 'classGroup.branch:id,name', 'classGroup.assignments.trainer:id,name')
            ->get();

        // Satu query untuk semua kelas; "hari ini" menurut zona tampilan (Asia/Jakarta).
        $sessions = ClassSession::whereIn('class_group_id', $enrollments->pluck('class_group_id'))
            ->whereDate('date', '>=', now(config('app.display_timezone'))->toDateString())
            ->with('trainer:id,name')
            ->orderBy('date')->orderBy('start_time')
            ->get()
            ->groupBy('class_group_id');

        foreach ($enrollments as $enrollment) {
            $enrollment->setRelation('upcomingSessions', ($sessions[$enrollment->class_group_id] ?? collect())->take(self::SESSIONS_PER_CLASS)->values());
        }

        return ParentScheduleResource::collection($enrollments);
    }
}
