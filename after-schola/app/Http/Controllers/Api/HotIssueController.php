<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\HotIssueResource;
use App\Models\HotIssue;
use Illuminate\Http\Request;

class HotIssueController extends Controller
{
    /**
     * Daftar semua hot issue (untuk halaman kelola — Management).
     */
    public function index(Request $request)
    {
        $this->authorize('viewAny', HotIssue::class);

        $issues = HotIssue::with('creator')->latest()->get();

        return HotIssueResource::collection($issues);
    }

    /**
     * Hot issue aktif yang BELUM ditutup user login — dipakai untuk popup setelah login,
     * di semua dashboard/role.
     */
    public function active(Request $request)
    {
        $issues = HotIssue::with('creator')
            ->activeAndUndismissedFor($request->user()->id)
            ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'warning' THEN 2 WHEN 'info' THEN 3 ELSE 4 END")
            ->latest()
            ->get();

        return HotIssueResource::collection($issues);
    }

    public function store(Request $request)
    {
        $this->authorize('create', HotIssue::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'severity' => ['required', 'in:info,warning,critical'],
        ]);

        $issue = HotIssue::create([
            ...$data,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return (new HotIssueResource($issue->load('creator')))->response()->setStatusCode(201);
    }

    public function update(Request $request, HotIssue $hotIssue)
    {
        $this->authorize('update', $hotIssue);

        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'message' => ['sometimes', 'required', 'string'],
            'severity' => ['sometimes', 'required', 'in:info,warning,critical'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $hotIssue->update($data);

        return new HotIssueResource($hotIssue->load('creator'));
    }

    public function destroy(Request $request, HotIssue $hotIssue)
    {
        $this->authorize('delete', $hotIssue);

        $hotIssue->delete();

        return response()->json(['message' => 'Hot issue dihapus.']);
    }

    /**
     * Tutup popup untuk user login saat ini — tidak akan muncul lagi untuk dia.
     */
    public function dismiss(Request $request, HotIssue $hotIssue)
    {
        $this->authorize('dismiss', $hotIssue);

        $hotIssue->dismissals()->firstOrCreate(
            ['user_id' => $request->user()->id],
            ['dismissed_at' => now()],
        );

        return response()->json(['message' => 'Ditandai sudah dibaca.']);
    }
}
