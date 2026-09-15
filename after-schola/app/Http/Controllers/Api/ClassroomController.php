<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ClassroomResource;
use App\Models\Classroom;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClassroomController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = Classroom::query()->withCount('students')->with('school');

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }

        if (! $user->hasAnyRole(['management', 'finance', 'hr'])) {
            $query->whereIn('school_id', $user->assignedSchoolIds());
        }

        return ClassroomResource::collection($query->orderBy('name')->get());
    }

    /**
     * Katalog nama mata pelajaran unik yang pernah dipakai di sekolah manapun.
     * Dipakai frontend untuk menampilkan pilihan centang saat menambah mata
     * pelajaran ke sekolah lain, tanpa perlu tabel katalog terpisah.
     */
    public function catalog(Request $request)
    {
        $names = Classroom::query()
            ->select('name')
            ->distinct()
            ->orderBy('name')
            ->pluck('name');

        return response()->json(['data' => $names]);
    }

    public function store(Request $request)
    {
        $this->authorize('create', Classroom::class);

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('classrooms', 'name')->where(fn ($q) => $q->where('school_id', $request->input('school_id'))),
            ],
            'level' => ['nullable', 'string', 'max:255'],
        ], [
            'name.unique' => 'Mata pelajaran ini sudah ada di sekolah tersebut.',
        ]);

        $classroom = Classroom::create($data);

        return (new ClassroomResource($classroom))->response()->setStatusCode(201);
    }

    public function show(Classroom $classroom)
    {
        $this->authorize('view', $classroom);

        return new ClassroomResource($classroom->load('school')->loadCount('students'));
    }

    public function update(Request $request, Classroom $classroom)
    {
        $this->authorize('update', $classroom);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('classrooms', 'name')
                    ->where(fn ($q) => $q->where('school_id', $classroom->school_id))
                    ->ignore($classroom->id),
            ],
            'level' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ], [
            'name.unique' => 'Mata pelajaran ini sudah ada di sekolah tersebut.',
        ]);

        $classroom->update($data);

        return new ClassroomResource($classroom);
    }

    public function destroy(Classroom $classroom)
    {
        $this->authorize('delete', $classroom);

        // Pengaman: kalau sudah ada murid atau pertemuan yang menempel,
        // jangan izinkan hapus permanen — arahkan ke nonaktifkan saja
        // supaya data murid/absensi yang sudah ada tidak ikut rusak/hilang.
        if ($classroom->students()->exists() || $classroom->classSessions()->exists()) {
            return response()->json([
                'message' => 'Mata pelajaran ini sudah punya murid atau pertemuan yang tercatat. Nonaktifkan saja, jangan dihapus, supaya data yang sudah ada tetap aman.',
            ], 422);
        }

        $classroom->delete();

        return response()->json(['message' => 'Mata pelajaran dihapus.']);
    }
}