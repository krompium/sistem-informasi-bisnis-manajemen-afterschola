<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\ClassGroup;
use Illuminate\Http\Request;

/**
 * Roster kelas (TRN-03): siswa di satu kelas. Staf per cabang, trainer per penugasan; di luar scope 404.
 * Default hanya yang aktif; ?status=selesai|pindah untuk riwayat.
 */
class ClassGroupEnrollmentController extends Controller
{
    public function __invoke(Request $request, ClassGroup $classGroup)
    {
        $this->authorize('viewAny', ClassGroup::class);
        $this->authorize('view', $classGroup);

        $enrollments = $classGroup->enrollments()
            ->where('status', $request->input('status', 'aktif'))
            ->with('student:id,name')->get()
            ->sortBy(fn ($enrollment) => $enrollment->student->name, SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return EnrollmentResource::collection($enrollments);
    }
}
