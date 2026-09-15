<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassSessionResource;
use App\Models\Classroom;
use App\Models\ClassSession;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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

    /**
     * Buat jadwal berulang sekaligus (mis. 16 pertemuan mingguan).
     * meeting_no & tanggal dinaikkan otomatis; tiap pertemuan tetap bisa
     * diubah/dihapus terpisah setelah dibuat.
     */
    public function storeBulk(Request $request)
    {
        $this->authorize('create', ClassSession::class);

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'trainer_id' => ['required', 'exists:users,id'],
            'mode' => ['required', 'in:onsite,online'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'start_date' => ['required', 'date'],
            'interval_days' => ['nullable', 'integer', 'min:1', 'max:60'],
            'count' => ['required', 'integer', 'min:1', 'max:52'],
            'start_meeting_no' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string'],
        ]);

        $this->ensureClassroomInSchool((int) $data['classroom_id'], (int) $data['school_id']);

        $interval = $data['interval_days'] ?? 7;
        $startNo = $data['start_meeting_no'] ?? 1;
        $base = Carbon::parse($data['start_date']);

        $ids = DB::transaction(function () use ($data, $interval, $startNo, $base) {
            $created = [];
            for ($i = 0; $i < $data['count']; $i++) {
                $created[] = ClassSession::create([
                    'school_id' => $data['school_id'],
                    'classroom_id' => $data['classroom_id'],
                    'trainer_id' => $data['trainer_id'],
                    'mode' => $data['mode'],
                    'date' => $base->copy()->addDays($interval * $i)->toDateString(),
                    'start_time' => $data['start_time'] ?? null,
                    'end_time' => $data['end_time'] ?? null,
                    'meeting_no' => $startNo + $i,
                    'note' => $data['note'] ?? null,
                ])->id;
            }

            return $created;
        });

        $sessions = ClassSession::with(['school', 'classroom', 'trainer'])
            ->whereIn('id', $ids)
            ->orderBy('meeting_no')
            ->get();

        return ClassSessionResource::collection($sessions)->response()->setStatusCode(201);
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
     * Mulai sesi: buka detail dulu, lalu mulai agar absensi bisa diinput.
     */
    public function start(Request $request, ClassSession $classSession)
    {
        $this->authorize('inputAttendance', $classSession);

        if (! $classSession->started_at) {
            $classSession->update(['started_at' => now()]);
        }

        return new ClassSessionResource($classSession->load(['school', 'classroom', 'trainer']));
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
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
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
