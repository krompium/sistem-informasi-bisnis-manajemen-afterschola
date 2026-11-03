<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SaveClassSessionRequest;
use App\Http\Resources\ClassGroupSessionResource;
use App\Models\ClassGroup;
use App\Models\ClassSession;
use App\Support\ScheduleNotifier;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Sesi dan jadwal kelas reguler (Modul 6c, MGT-34/35). Baca: staf per cabang, trainer untuk kelas yang ditugaskan
 * atau sesi yang ia isi sebagai pengganti. Tulis: permission manage schedule di route.
 * Pertemuan sekolah tetap di /sessions (ClassSessionController); keduanya berbagi tabel class_sessions.
 */
class ClassGroupSessionController extends Controller
{
    private const WITH = ['classGroup:id,name', 'classGroup.assignments:id,class_group_id,trainer_id', 'trainer:id,name'];

    public function index(Request $request)
    {
        $this->authorize('viewAny', ClassGroup::class);

        $query = ClassSession::visibleToRegular($request->user())
            ->with(self::WITH)->orderBy('date')->orderBy('start_time')->orderBy('id');

        foreach (['class_group_id', 'trainer_id'] as $filter) {
            if ($request->filled($filter)) {
                $query->where($filter, $request->integer($filter));
            }
        }
        if ($request->filled('from')) {
            $query->whereDate('date', '>=', $request->date('from'));
        }
        if ($request->filled('to')) {
            $query->whereDate('date', '<=', $request->date('to'));
        }

        return ClassGroupSessionResource::collection($query->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function show(ClassSession $classSession)
    {
        $this->authorize('viewAny', ClassGroup::class);
        $this->authorize('viewRegular', $classSession);

        return new ClassGroupSessionResource($classSession->load(self::WITH));
    }

    public function store(SaveClassSessionRequest $request, ScheduleNotifier $notifier)
    {
        $session = ClassSession::create($request->validated());
        $notifier->sessionScheduled($session);

        return (new ClassGroupSessionResource($session->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function update(SaveClassSessionRequest $request, ClassSession $classSession, ScheduleNotifier $notifier)
    {
        if ($classSession->is_locked) {
            throw ValidationException::withMessages(['session' => ['Sesi sudah dikunci dan tidak dapat diubah.']]);
        }

        $before = ScheduleNotifier::signature($classSession);
        $classSession->update($request->validated());
        $notifier->sessionChanged($classSession, $before);

        return new ClassGroupSessionResource($classSession->load(self::WITH));
    }

    // Kunci sesi (Modul 7a): setelah terkunci, jadwal dan absensi siswa tidak dapat diubah.
    public function lock(ClassSession $classSession)
    {
        $this->authorize('lockRegular', $classSession);
        $classSession->update(['is_locked' => true]);

        return new ClassGroupSessionResource($classSession->load(self::WITH));
    }

    public function unlock(ClassSession $classSession)
    {
        $this->authorize('unlockRegular', $classSession);
        $classSession->update(['is_locked' => false]);

        return new ClassGroupSessionResource($classSession->load(self::WITH));
    }
}
