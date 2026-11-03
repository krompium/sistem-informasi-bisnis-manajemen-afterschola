<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationResource;
use App\Http\Resources\ProgressReportResource;
use App\Models\ProgressReport;
use App\Models\User;
use App\Notifications\ReportReturnedNotification;
use App\Notifications\ReportSentNotification;
use Illuminate\Http\Request;
use Illuminate\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Alur raport (Modul 9): trainer mengajukan (draft -> ditinjau); koordinator dengan `review reports` menyetujui
 * (-> terkirim, ortu diberi tahu) atau mengembalikan dengan catatan (-> draft, trainer diberi tahu). BR-17, AC-12.
 */
class ProgressReportController extends Controller
{
    private const WITH = ['evaluation.enrollment.student:id,name', 'evaluation.enrollment.classGroup:id,name', 'evaluation.evaluator:id,name', 'reviewer:id,name'];

    public function submit(ProgressReport $progressReport)
    {
        $evaluation = $progressReport->evaluation;
        $this->authorize('update', $evaluation);

        if ($progressReport->status !== 'draft') {
            throw ValidationException::withMessages(['status' => ['Raport sudah diajukan.']]);
        }

        $criteria = $evaluation->enrollment->classGroup->program->rubricCriteria;
        $scored = $evaluation->criteria()->pluck('rubric_criteria.id');

        if ($criteria->isEmpty()) {
            throw ValidationException::withMessages(['criteria' => ['Program belum memiliki kriteria rubrik.']]);
        }
        if ($criteria->pluck('id')->diff($scored)->isNotEmpty()) {
            throw ValidationException::withMessages(['scores' => ['Semua kriteria rubrik harus dinilai sebelum diajukan.']]);
        }
        if (trim((string) $evaluation->summary) === '') {
            throw ValidationException::withMessages(['summary' => ['Ringkasan evaluasi wajib diisi sebelum diajukan.']]);
        }

        $progressReport->update(['status' => 'ditinjau']);

        return new EvaluationResource($evaluation->load(EvaluationController::DETAIL));
    }

    /** Antrian tinjau (MGT-50): default status ditinjau, yang terlama lebih dulu. */
    public function index(Request $request)
    {
        $status = $request->input('status', 'ditinjau');
        $query = ProgressReport::visibleTo($request->user())->with(self::WITH)->where('status', $status);
        $status === 'ditinjau' ? $query->oldest('updated_at') : $query->latest('updated_at');

        if ($request->filled('class_group_id')) {
            $query->whereHas('evaluation.enrollment', fn ($e) => $e->where('class_group_id', $request->integer('class_group_id')));
        }

        return ProgressReportResource::collection($query->paginate(min($request->integer('per_page', 25), 100)));
    }

    public function approve(Request $request, ProgressReport $progressReport)
    {
        $this->authorize('view', $progressReport->evaluation); // sebelum aturan status: di luar cabang harus 404
        $this->leaveReview($progressReport, [
            'status' => 'terkirim', 'review_note' => null, 'reviewed_by' => $request->user()->id, 'sent_at' => now(),
        ]);

        $evaluation = $progressReport->evaluation->load('enrollment.student.parent', 'enrollment.classGroup');
        $this->notify($evaluation->enrollment->student->parent, new ReportSentNotification($evaluation));

        return new ProgressReportResource($progressReport->load(self::WITH));
    }

    public function sendBack(Request $request, ProgressReport $progressReport)
    {
        $this->authorize('view', $progressReport->evaluation);
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $this->leaveReview($progressReport, ['status' => 'draft', 'review_note' => $data['note'], 'reviewed_by' => $request->user()->id]);

        $evaluation = $progressReport->evaluation->load('enrollment.student', 'evaluator');
        $this->notify($evaluation->evaluator, new ReportReturnedNotification($evaluation, $data['note']));

        return new ProgressReportResource($progressReport->load(self::WITH));
    }

    // Update bersyarat: dua klik "setujui" bersamaan tidak mengirim notifikasi dua kali.
    private function leaveReview(ProgressReport $report, array $attributes): void
    {
        $moved = ProgressReport::whereKey($report->id)->where('status', 'ditinjau')->update($attributes);

        if (! $moved) {
            throw ValidationException::withMessages(['status' => ['Raport tidak sedang menunggu tinjauan.']]);
        }

        $report->refresh();
    }

    // Gagal kirim tidak boleh menggagalkan tinjauan (NFR-07); notifikasi in-app sudah tersimpan lebih dulu.
    private function notify(?User $user, Notification $notification): void
    {
        if (! $user) {
            return;
        }

        try {
            $user->notify($notification);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
