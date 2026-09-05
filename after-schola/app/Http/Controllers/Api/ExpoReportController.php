<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ExpoReportResource;
use App\Models\ExpoReport;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ExpoReportController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $query = ExpoReport::query()->with(['school', 'trainer']);

        if ($request->filled('school_id')) {
            $query->where('school_id', $request->integer('school_id'));
        }

        // Management melihat semua; trainer hanya laporan miliknya.
        if (! $user->can('manage expo reports')) {
            $query->where('trainer_id', $user->id);
        }

        return ExpoReportResource::collection($query->orderByDesc('date')->get());
    }

    public function store(Request $request)
    {
        $this->authorize('create', ExpoReport::class);

        $data = $request->validate([
            'school_id' => ['required', 'exists:schools,id'],
            'date' => ['required', 'date'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'on_schedule' => ['nullable', 'in:ya,sebagian,tidak'],
            'enthusiasm' => ['nullable', 'string'],
            'has_issue' => ['sometimes', 'boolean'],
            'issue_note' => ['nullable', 'string'],
            'doc_url' => ['nullable', 'url', 'max:2048'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        // Trainer hanya boleh melapor untuk sekolah yang ia pegang.
        if (! $request->user()->handlesSchool((int) $data['school_id'])) {
            throw ValidationException::withMessages([
                'school_id' => ['Anda tidak ditugaskan pada sekolah ini.'],
            ]);
        }

        $paths = [];
        if ($request->hasFile('photos')) {
            foreach ($request->file('photos') as $file) {
                $paths[] = $file->store('expo-reports', 'public');
            }
        }

        unset($data['photos']);
        $data['trainer_id'] = $request->user()->id;
        $data['photo_paths'] = $paths;
        $report = ExpoReport::create($data);

        return (new ExpoReportResource($report->load('school')))->response()->setStatusCode(201);
    }

    public function show(ExpoReport $expoReport)
    {
        $this->authorize('view', $expoReport);

        return new ExpoReportResource($expoReport->load(['school', 'trainer']));
    }

    public function update(Request $request, ExpoReport $expoReport)
    {
        $this->authorize('update', $expoReport);

        $data = $request->validate([
            'date' => ['sometimes', 'required', 'date'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'rating' => ['nullable', 'integer', 'between:1,5'],
            'on_schedule' => ['nullable', 'in:ya,sebagian,tidak'],
            'enthusiasm' => ['nullable', 'string'],
            'has_issue' => ['sometimes', 'boolean'],
            'issue_note' => ['nullable', 'string'],
            'doc_url' => ['nullable', 'url', 'max:2048'],
            'photos' => ['nullable', 'array', 'max:10'],
            'photos.*' => ['image', 'max:5120'],
        ]);

        if ($request->hasFile('photos')) {
            $paths = $expoReport->photo_paths ?? [];
            foreach ($request->file('photos') as $file) {
                $paths[] = $file->store('expo-reports', 'public');
            }
            $data['photo_paths'] = $paths;
        }
        unset($data['photos']);

        $expoReport->update($data);

        return new ExpoReportResource($expoReport->load('school'));
    }

    public function destroy(ExpoReport $expoReport)
    {
        $this->authorize('delete', $expoReport);

        $expoReport->delete();

        return response()->json(['message' => 'Laporan ekspo dihapus.']);
    }
}
