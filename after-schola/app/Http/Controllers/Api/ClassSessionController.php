<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSessionResource;
use App\Models\Classroom;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ClassSessionController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = ClassSession::query()
            ->with(['school', 'classroom', 'trainer'])
            ->withCount(['studentAttendances as present_count' => fn ($q) => $q->where('is_present', true)]);

        foreach (['school_id', 'classroom_id', 'trainer_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }
        if ($request->filled('date')) {
            $query->whereDate('date', $request->date('date'));
        }

        if (! $user->hasAnyRole(['management', 'finance', 'hr'])) {
            $query->whereIn('school_id', $user->assignedSchoolIds());
        }

        return ClassSessionResource::collection(
            $query->orderByDesc('date')->orderBy('meeting_no')->get()
        );
    }

    public function store(Request $request)
    {
        $this->authorize('create', ClassSession::class);

        $data = $this->validateData($request);
        $this->ensureClassroomInSchool((int) $data['classroom_id'], (int) $data['school_id']);

        $session = ClassSession::create($data);

        return (new ClassSessionResource($session->load(['school', 'classroom', 'trainer'])))
            ->response()->setStatusCode(201);
    }

    public function show(ClassSession $classSession)
    {
        $this->authorize('view', $classSession);

        return new ClassSessionResource(
            $classSession->load([
                'school', 'classroom', 'trainer',
                'studentAttendances.student', 'trainerAttendances.trainer',
            ])
        );
    }

    public function update(Request $request, ClassSession $classSession)
    {
        $this->authorize('update', $classSession);

        $data = $this->validateData($request, partial: true);
        if (isset($data['classroom_id'], $data['school_id'])) {
            $this->ensureClassroomInSchool((int) $data['classroom_id'], (int) $data['school_id']);
        }

        $classSession->update($data);

        return new ClassSessionResource($classSession->load(['school', 'classroom', 'trainer']));
    }

    public function destroy(ClassSession $classSession)
    {
        $this->authorize('delete', $classSession);

        $classSession->delete();

        return response()->json(['message' => 'Pertemuan dihapus.']);
    }

    /**
     * Kunci / buka pertemuan agar absensi tidak diubah lagi.
     */
    public function lock(Request $request, ClassSession $classSession)
    {
        $this->authorize('lock', $classSession);

        $data = $request->validate(['is_locked' => ['sometimes', 'boolean']]);
        $classSession->update(['is_locked' => $data['is_locked'] ?? true]);

        return new ClassSessionResource($classSession);
    }

    private function validateData(Request $request, bool $partial = false): array
    {
        $req = $partial ? 'sometimes' : 'required';

        return $request->validate([
            'school_id' => [$req, 'exists:schools,id'],
            'classroom_id' => [$req, 'exists:classrooms,id'],
            'trainer_id' => [$req, 'exists:users,id'],
            'mode' => [$req, 'in:onsite,online'],
            'date' => [$req, 'date'],
            'meeting_no' => [$req, 'integer', 'min:1'],
            'is_locked' => ['sometimes', 'boolean'],
            'note' => ['nullable', 'string'],
        ]);
    }

    private function ensureClassroomInSchool(int $classroomId, int $schoolId): void
    {
        $belongs = Classroom::where('id', $classroomId)->where('school_id', $schoolId)->exists();
        if (! $belongs) {
            throw ValidationException::withMessages([
                'classroom_id' => ['Level tidak berada di sekolah tersebut.'],
            ]);
        }
    }
}
