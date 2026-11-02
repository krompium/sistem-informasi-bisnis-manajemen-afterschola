<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentDecisionResource;
use App\Models\TrialEvaluation;
use App\Support\EnrollmentFlow;
use Illuminate\Http\Request;

/**
 * Modul 4 & 5 (bagian pendaftaran): Keputusan lanjut/tidak + Pendaftaran Resmi.
 * Hak akses: permission "manage enrollment" (routes) + scope cabang (Policy "enroll" dan query).
 * Staf dapat mencatat atas nama orang tua; orang tua memutuskan sendiri lewat portal (ParentChildController, BR-02).
 * Aturan bisnis ada di EnrollmentFlow, dipakai bersama portal.
 */
class EnrollmentDecisionController extends Controller
{
    private const WITH = ['trainer', 'level', 'lead.parent:id,name,email,phone', 'decision.registration'];

    public function __construct(private EnrollmentFlow $flow) {}

    /**
     * Daftar evaluasi (yang hadir) beserta status keputusan & pendaftarannya.
     */
    public function index(Request $request)
    {
        $query = TrialEvaluation::inBranchesOf($request->user())
            ->where('attendance', 'hadir')
            ->with(self::WITH);

        if ($request->filled('q')) {
            $query->where('participant_name', 'like', '%'.$request->string('q').'%');
        }
        if ($request->filled('status')) {
            $status = $request->string('status')->toString();
            if ($status === 'belum') {
                $query->whereDoesntHave('decision');
            } elseif (in_array($status, ['lanjut', 'tidak', 'pikir_pikir'], true)) {
                $query->whereHas('decision', fn ($q) => $q->where('decision', $status));
            }
        }

        return EnrollmentDecisionResource::collection(
            $query->orderByDesc('trial_date')->orderByDesc('id')->get()
        );
    }

    /**
     * Catat / ubah keputusan untuk satu evaluasi.
     */
    public function saveDecision(Request $request, TrialEvaluation $trialEvaluation)
    {
        $this->authorize('enroll', $trialEvaluation); // sebelum validasi: di luar cabang harus 404, bukan 422
        $this->flow->ensureAttended($trialEvaluation);

        $data = $request->validate([
            'decision' => ['required', 'in:lanjut,tidak,pikir_pikir'],
            'reason' => ['nullable', 'string', 'required_if:decision,tidak'],
            'decided_at' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date', 'required_if:decision,pikir_pikir'],
        ]);

        $this->flow->decide($trialEvaluation, $data, $request->user());

        return new EnrollmentDecisionResource($trialEvaluation->fresh(self::WITH));
    }

    /**
     * Isi / ubah form pendaftaran resmi (hanya bila keputusan = lanjut & belum diajukan).
     */
    public function saveRegistration(Request $request, TrialEvaluation $trialEvaluation)
    {
        $this->authorize('enroll', $trialEvaluation);
        $this->flow->editableRegistration($trialEvaluation);

        $data = $request->validate(EnrollmentFlow::registrationRules());

        $this->flow->saveRegistration($trialEvaluation, $data, $request->user());

        return new EnrollmentDecisionResource($trialEvaluation->fresh(self::WITH));
    }

    /**
     * Ajukan pendaftaran untuk verifikasi (serah ke Modul 5). Setelah ini terkunci.
     */
    public function submitRegistration(TrialEvaluation $trialEvaluation)
    {
        $this->authorize('enroll', $trialEvaluation);

        $this->flow->submitRegistration($trialEvaluation);

        return new EnrollmentDecisionResource($trialEvaluation->fresh(self::WITH));
    }
}
