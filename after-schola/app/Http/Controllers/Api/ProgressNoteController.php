<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveProgressNoteRequest;
use App\Http\Resources\ProgressNoteResource;
use App\Models\ProgressNote;
use Illuminate\Http\Request;

/**
 * Catatan progres (Modul 8, TRN-08/MGT-44). Baca: trainer dan Management, dibatasi query (ProgressNote::visibleTo).
 * Tulis: role trainer di route; penulis diambil dari login, bukan dari body.
 */
class ProgressNoteController extends Controller
{
    private const WITH = ['student:id,name', 'classSession:id,class_group_id,date,meeting_no', 'trainer:id,name'];

    public function index(Request $request)
    {
        $query = ProgressNote::visibleTo($request->user())->with(self::WITH)->latest('id');

        foreach (['student_id', 'class_session_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }
        if ($request->filled('class_group_id')) {
            $query->whereHas('classSession', fn ($s) => $s->where('class_group_id', $request->integer('class_group_id')));
        }

        return ProgressNoteResource::collection($query->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function store(SaveProgressNoteRequest $request)
    {
        $note = ProgressNote::create($request->validated() + ['trainer_id' => $request->user()->id]);

        return (new ProgressNoteResource($note->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(SaveProgressNoteRequest $request, ProgressNote $progressNote)
    {
        $progressNote->update($request->validated());

        return new ProgressNoteResource($progressNote->load(self::WITH));
    }
}
