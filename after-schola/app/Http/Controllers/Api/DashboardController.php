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
use App\Enums\TaskStatus;
use App\Models\Project;
use App\Models\Task;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        if ($user->hasRole('developer') && ! $user->hasRole('management')) {
            return $this->developerDashboard($user);
        }

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
	
	// Dashboard team dev (FR-12.6): hanya data team milik user.
    private function developerDashboard(User $user)
    {
        $teamIds = $user->teams()->pluck('teams.id');

        $teamTasks = fn () => Task::whereIn(
            'project_id', Project::whereIn('team_id', $teamIds)->select('id')
        );

        $countByStatus = $teamTasks()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'role' => 'developer',
            'ongoing_projects' => Project::whereIn('team_id', $teamIds)
                ->where('status', 'ongoing')
                ->withCount(['tasks', 'tasks as tasks_done_count' => fn ($q) => $q->where('status', 'done')])
                ->orderBy('name')
                ->get()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'due_date' => $p->due_date->toDateString(),
                    'tasks_total' => (int) $p->tasks_count,
                    'tasks_done' => (int) $p->tasks_done_count,
                ]),
            // Empat status selalu ada, 0 bila kosong.
            'tasks_by_status' => collect(TaskStatus::cases())
                ->mapWithKeys(fn ($s) => [$s->value => (int) ($countByStatus[$s->value] ?? 0)]),
            // Task belum done per assignee, terbanyak lebih dulu.
            'workload' => $teamTasks()
                ->where('tasks.status', '!=', 'done')
                ->join('users', 'users.id', '=', 'tasks.assignee_id')
                ->selectRaw('users.id as user_id, users.name, count(*) as open_tasks')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('open_tasks')
                ->orderBy('users.name')
                ->get()
                ->map(fn ($r) => [
                    'user_id' => (int) $r->user_id,
                    'name' => $r->name,
                    'open_tasks' => (int) $r->open_tasks,
                ]),
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
