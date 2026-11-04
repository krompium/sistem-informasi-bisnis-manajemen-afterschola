<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluationResource;
use App\Models\Evaluation;
use App\Models\Lead;
use App\Models\Registration;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Portal Orang Tua (ORT-11, BR-17, AC-12): raport terkirim milik anak. Role "parent" dijaga middleware route;
 * kepemilikan dan status dijaga Evaluation::visibleTo (ortu hanya melihat raport terkirim anaknya), selain itu 404.
 */
class ParentReportController extends Controller
{
    public function index(Request $request, int $child)
    {
        $lead = Lead::where('parent_id', $request->user()->id)->findOrFail($child);
        // Siswa anak ini = student_id pada pendaftarannya (baru maupun perpanjangan), diisi saat penempatan ke kelas.
        $studentIds = Registration::where('lead_id', $lead->id)->whereNotNull('student_id')->pluck('student_id');

        return EvaluationResource::collection(
            Evaluation::visibleTo($request->user())
                ->whereHas('enrollment', fn ($enrollment) => $enrollment->whereIn('student_id', $studentIds))
                ->with(EvaluationController::DETAIL)
                ->latest('period')->latest('id')->get()
        );
    }

    public function pdf(Evaluation $evaluation)
    {
        $this->authorize('view', $evaluation); // belum terkirim atau milik ortu lain: 404
        $evaluation->load(array_merge(EvaluationController::DETAIL, ['enrollment.classGroup.branch']));

        return Pdf::loadView('exports.report', [
            'evaluation' => $evaluation,
            'branch' => $evaluation->enrollment->classGroup->branch,
        ])->setPaper('a4')
            ->download('raport-'.Str::slug($evaluation->enrollment->student->name).'-'.$evaluation->period.'.pdf');
    }
}
