<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\StudentResource;
use App\Models\Classroom;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenSpout\Reader\CSV\Reader as CsvReader;
use OpenSpout\Reader\XLSX\Reader as XlsxReader;

class StudentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Student::query()->with(['classroom', 'school']);

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }
        if ($request->filled('classroom_id')) {
            $query->where('classroom_id', $request->integer('classroom_id'));
        }

        if (! $user->hasAnyRole(['management', 'finance', 'hr'])) {
            $query->whereIn('school_id', $user->assignedSchoolIds());
        }

        return StudentResource::collection($query->orderBy('name')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('create', Student::class);

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'name' => ['required', 'string', 'max:255'],
            'origin_class' => ['nullable', 'string', 'max:100'],
        ]);

        $this->ensureHandlesSchool($request, (int) $data['school_id']);
        $this->ensureClassroomInSchool((int) $data['classroom_id'], (int) $data['school_id']);

        $data['created_by'] = $request->user()->id;
        $student = Student::create($data);

        return (new StudentResource($student->load('classroom')))->response()->setStatusCode(201);
    }

    public function show(Student $student)
    {
        $this->authorize('view', $student);

        return new StudentResource($student->load(['classroom', 'school']));
    }

    public function update(Request $request, Student $student)
    {
        $this->authorize('update', $student);

        $data = $request->validate([
            'classroom_id' => ['sometimes', 'required', 'exists:classrooms,id'],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'origin_class' => ['nullable', 'string', 'max:100'],
        ]);

        if (isset($data['classroom_id'])) {
            $this->ensureClassroomInSchool((int) $data['classroom_id'], $student->school_id);
        }

        $student->update($data);

        return new StudentResource($student->load('classroom'));
    }

    public function destroy(Student $student)
    {
        $this->authorize('delete', $student);

        $student->delete();

        return response()->json(['message' => 'Murid dihapus.']);
    }

    /**
     * Import murid dari Excel/CSV. Kolom: name (wajib), origin_class (opsional).
     * Baris pertama dianggap header dan dilewati.
     */
    public function import(Request $request)
    {
        $this->authorize('create', Student::class);

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'classroom_id' => ['required', 'exists:classrooms,id'],
            'file' => ['required', 'file', 'mimes:xlsx,csv,txt', 'max:5120'],
        ]);

        $this->ensureHandlesSchool($request, (int) $data['school_id']);
        $this->ensureClassroomInSchool((int) $data['classroom_id'], (int) $data['school_id']);

        $path = $request->file('file')->getRealPath();
        $extension = strtolower($request->file('file')->getClientOriginalExtension());
        $reader = $extension === 'csv' || $extension === 'txt' ? new CsvReader : new XlsxReader;

        $created = 0;
        $skipped = 0;
        $userId = $request->user()->id;

        $reader->open($path);
        foreach ($reader->getSheetIterator() as $sheet) {
            $rowIndex = 0;
            foreach ($sheet->getRowIterator() as $row) {
                $rowIndex++;
                if ($rowIndex === 1) {
                    continue; // header
                }
                $cells = $row->toArray();
                $name = trim((string) ($cells[0] ?? ''));
                if ($name === '') {
                    $skipped++;

                    continue;
                }
                Student::create([
                    'school_id' => $data['school_id'],
                    'classroom_id' => $data['classroom_id'],
                    'name' => $name,
                    'origin_class' => isset($cells[1]) ? trim((string) $cells[1]) : null,
                    'created_by' => $userId,
                ]);
                $created++;
            }
            break; // hanya sheet pertama
        }
        $reader->close();

        return response()->json([
            'message' => "Import selesai: {$created} murid ditambahkan, {$skipped} baris dilewati.",
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    private function ensureHandlesSchool(Request $request, int $schoolId): void
    {
        if (! $request->user()->handlesSchool($schoolId)) {
            throw ValidationException::withMessages([
                'school_id' => ['Anda tidak ditugaskan pada sekolah ini.'],
            ]);
        }
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
