<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreEnrollmentRequest;
use App\Http\Resources\EnrollmentResource;
use App\Models\ClassGroup;
use App\Models\Enrollment;
use App\Models\Registration;
use App\Support\StudentPlacement;
use Illuminate\Http\Request;

/** Penempatan siswa ke kelas (Modul 6b, MGT-32). Permission manage classes di route; scope cabang di FormRequest/query. */
class EnrollmentController extends Controller
{
    private const WITH = ['student:id,name', 'classGroup:id,name'];

    public function index(Request $request)
    {
        $query = Enrollment::visibleTo($request->user())->with(self::WITH)->orderBy('id');

        if ($request->filled('class_group_id')) {
            $query->where('class_group_id', $request->integer('class_group_id'));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        return EnrollmentResource::collection($query->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function store(StoreEnrollmentRequest $request, StudentPlacement $placement)
    {
        $enrollment = $placement->place(
            Registration::findOrFail($request->integer('registration_id')),
            ClassGroup::findOrFail($request->integer('class_group_id')),
            $request->input('level_override_reason'),
        );

        return (new EnrollmentResource($enrollment->load(self::WITH)))->response()->setStatusCode(201);
    }
}
