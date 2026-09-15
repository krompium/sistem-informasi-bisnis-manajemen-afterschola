<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\SchoolResource;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\Request;

class SchoolController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = School::query()->withCount('students')->with('classrooms');

        // Isolasi data: trainer hanya sekolah yang dipegang.
        if (! $user->hasAnyRole(['management', 'finance', 'hr'])) {
            $query->whereIn('id', $user->assignedSchoolIds());
        }

        return SchoolResource::collection($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('create', School::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'pic_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $school = School::create($data);

        return (new SchoolResource($school))->response()->setStatusCode(201);
    }

    public function show(Request $request, School $school)
    {
        $this->authorize('view', $school);

        return new SchoolResource($school->load('classrooms', 'trainers'));
    }

    public function update(Request $request, School $school)
    {
        $this->authorize('update', $school);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'pic_name' => ['nullable', 'string', 'max:255'],
            'pic_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $school->update($data);

        return new SchoolResource($school);
    }

    public function destroy(Request $request, School $school)
    {
        $this->authorize('delete', $school);

        $school->delete();

        return response()->json(['message' => 'Sekolah dihapus.']);
    }

    /**
     * Penugasan trainer -> sekolah (many-to-many).
     */
    public function assignTrainers(Request $request, School $school)
    {
        $this->authorize('assignTrainer', $school);

        $data = $request->validate([
            'trainer_ids' => ['required', 'array'],
            'trainer_ids.*' => ['integer', 'exists:users,id'],
        ]);

        // Pastikan hanya user ber-role trainer yang bisa ditugaskan.
        $trainerIds = User::whereIn('id', $data['trainer_ids'])
            ->role('trainer')
            ->pluck('id')
            ->all();

        $school->trainers()->sync($trainerIds);

        return new SchoolResource($school->load('trainers'));
    }
}
