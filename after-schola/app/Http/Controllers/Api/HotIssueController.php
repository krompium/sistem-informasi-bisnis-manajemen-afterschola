<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\HotIssue;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class HotIssueController extends Controller
{
    /** Daftar semua hot issue — buat halaman kelola Management. */
    public function index(Request $request)
    {
        $this->authorize('manage', HotIssue::class);

        return HotIssue::latest()->paginate(20);
    }

    /**
     * Hot issue aktif yang BELUM di-dismiss user yang sedang login.
     * Dipanggil oleh popup di dashboard, untuk semua role.
     */
    public function active(Request $request)
    {
        $issues = HotIssue::active()
            ->notDismissedBy($request->user()->id)
            ->orderByRaw("FIELD(priority, 'high', 'medium', 'low')")
            ->orderByDesc('published_at')
            ->get(['id', 'title', 'content', 'priority', 'published_at']);

        return response()->json($issues);
    }

    public function store(Request $request)
    {
        $this->authorize('create', HotIssue::class);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'priority' => ['required', 'in:low,medium,high'],
            'is_published' => ['boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ]);

        $data['created_by'] = $request->user()->id;

        $hotIssue = HotIssue::create($data);

        return response()->json($hotIssue, Response::HTTP_CREATED);
    }

    public function update(Request $request, HotIssue $hotIssue)
    {
        $this->authorize('update', $hotIssue);

        $data = $request->validate([
            'title' => ['sometimes', 'string', 'max:255'],
            'content' => ['sometimes', 'string'],
            'priority' => ['sometimes', 'in:low,medium,high'],
            'is_published' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date', 'after:published_at'],
        ]);

        $hotIssue->update($data);

        return response()->json($hotIssue);
    }

    public function destroy(Request $request, HotIssue $hotIssue)
    {
        $this->authorize('delete', $hotIssue);

        $hotIssue->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /** Tandai satu hot issue sudah dibaca/ditutup oleh user yang login. */
    public function dismiss(Request $request, HotIssue $hotIssue)
    {
        $hotIssue->dismissedBy()->syncWithoutDetaching([
            $request->user()->id => ['dismissed_at' => now()],
        ]);

        return response()->json(['message' => 'dismissed']);
    }
}