<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaskAttachmentRequest;
use App\Http\Resources\TaskAttachmentResource;
use App\Models\Task;
use App\Models\TaskAttachment;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

// File disimpan di disk 'local' (privat, tidak bisa diakses lewat URL publik) dengan nama acak,
// dan hanya bisa diunduh lewat endpoint ini setelah otorisasi task induknya.
class TaskAttachmentController extends Controller
{
    private const MAX_FILES = 20;

    private const DISK = 'local';

    // Daftar tidak dipaginasi karena dibatasi MAX_FILES per task.
    public function index(Task $task)
    {
        $this->authorize('view', $task);

        return TaskAttachmentResource::collection(
            $task->attachments()->with('user')->latest()->orderByDesc('id')->get()
        );
    }

    public function store(StoreTaskAttachmentRequest $request, Task $task)
    {
        if ($task->attachments()->count() >= self::MAX_FILES) {
            throw ValidationException::withMessages([
                'file' => ['Lampiran maksimal '.self::MAX_FILES.' file per task.'],
            ]);
        }

        $file = $request->file('file');
        // Nama file di disk acak; ekstensi dari isi file, bukan dari klien.
        $path = $file->storeAs(
            "task-attachments/{$task->id}",
            Str::uuid().'.'.($file->guessExtension() ?: 'bin'),
            self::DISK,
        );

        $attachment = $task->attachments()->create([
            'user_id' => $request->user()->id,
            'original_name' => Str::limit(basename(preg_replace('/[\x00-\x1F\x7F]/u', '', $file->getClientOriginalName())), 255, ''),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return new TaskAttachmentResource($attachment->load('user'));
    }

    public function download(TaskAttachment $attachment)
    {
        $this->authorize('view', $attachment->task);

        abort_unless(Storage::disk(self::DISK)->exists($attachment->path), 404);

        return Storage::disk(self::DISK)->download($attachment->path, $attachment->original_name, [
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function destroy(TaskAttachment $attachment)
    {
        $this->authorize('update', $attachment->task);

        Storage::disk(self::DISK)->delete($attachment->path);
        $attachment->delete();

        return response()->noContent();
    }
}
