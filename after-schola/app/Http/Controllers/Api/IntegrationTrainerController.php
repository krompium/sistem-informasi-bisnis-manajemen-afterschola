<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IntegrationTrainerAttendanceRequest;
use App\Http\Resources\IntegrationTrainerAttendanceResource;
use App\Http\Resources\IntegrationTrainerResource;
use App\Models\TrainerAttendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * API integrasi read-only untuk Tim Keuangan (SYS-05, KEU-08, PRD 10.4): trainer dan absensi trainer reguler,
 * dasar perhitungan honor (D-02, BR-20). Ability attendance:read dan permission "export trainer attendance"
 * dijaga middleware route; data dibatasi cabang akun layanan (BR-19). Urut id naik supaya paginasi stabil.
 */
class IntegrationTrainerController extends Controller
{
    public function trainers(Request $request)
    {
        $user = $request->user();
        $query = User::role('trainer')
            ->when(! $user->hasAllBranches(), fn (Builder $q) => $q->where(fn (Builder $scope) => $scope
                ->where('branch_scope', 'all')
                ->orWhereHas('branches', fn (Builder $b) => $b->whereIn('branches.id', $user->accessibleBranchIds()))))
            ->orderBy('id');

        return IntegrationTrainerResource::collection($query->paginate($request->integer('per_page', 25)));
    }

    public function attendances(IntegrationTrainerAttendanceRequest $request)
    {
        $filters = $request->validated();
        $user = $request->user();

        $query = TrainerAttendance::query()
            ->whereHas('classSession', fn (Builder $session) => $session->whereNotNull('class_sessions.class_group_id')
                ->whereDate('class_sessions.date', '>=', $filters['from'])->whereDate('class_sessions.date', '<=', $filters['to'])
                ->whereHas('classGroup', fn (Builder $group) => $group->inBranchesOf($user)
                    ->when($filters['branch_id'] ?? null, fn (Builder $q, $id) => $q->where('class_groups.branch_id', $id))))
            ->when($filters['trainer_id'] ?? null, fn (Builder $q, $id) => $q->where('trainer_attendances.trainer_id', $id))
            ->with('classSession.classGroup.branch:id,code', 'classSession.classGroup.program:id,name', 'classSession.classGroup.assignments', 'trainer:id,name,external_ref')
            ->orderBy('trainer_attendances.id');

        return IntegrationTrainerAttendanceResource::collection($query->paginate($request->integer('per_page', 25)));
    }
}
