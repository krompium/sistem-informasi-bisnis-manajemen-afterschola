<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlacementCandidateResource;
use App\Models\Registration;
use Illuminate\Http\Request;

/** Siswa "Siap Dijadwalkan" (MGT-31): registrasi terverifikasi dan Lunas yang belum punya enrollment aktif. */
class PlacementCandidateController extends Controller
{
    public function index(Request $request)
    {
        $query = Registration::inBranchesOf($request->user())
            ->where('status', 'terverifikasi')
            ->whereHas('invoices', fn ($invoice) => $invoice->where('status', 'paid'))
            ->whereDoesntHave('student.enrollments', fn ($enrollment) => $enrollment->where('status', 'aktif'))
            ->with(['program:id,name', 'branch:id,name', 'decision.evaluation.level:id,name'])
            ->orderBy('id');

        if ($request->filled('branch_id')) {
            $query->where('branch_id', $request->integer('branch_id'));
        }
        if ($request->filled('program_id')) {
            $query->where('program_id', $request->integer('program_id'));
        }

        return PlacementCandidateResource::collection($query->paginate(25));
    }
}
