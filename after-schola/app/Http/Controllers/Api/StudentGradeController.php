<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentGradeResource;
use App\Models\Student;
use Illuminate\Http\Request;

class StudentGradeController extends Controller
{
    public function store(Request $request, Student $student)
    {
        $user = $request->user();

        abort_unless(
            $user->hasRole('trainer') && $user->handlesSchool($student->school_id),
            403,
            'Tidak berhak input nilai untuk siswa ini.'
        );

        $data = $request->validate([
            'period' => ['required', 'string', 'max:100'],
            'score' => ['required', 'numeric', 'min:0', 'max:100'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $grade = $student->grades()->updateOrCreate(
            ['period' => $data['period']],
            ['score' => $data['score'], 'note' => $data['note'] ?? null, 'input_by' => $user->id]
        );

        return (new StudentGradeResource($grade->load('inputBy')))
            ->response()->setStatusCode(201);
    }
}