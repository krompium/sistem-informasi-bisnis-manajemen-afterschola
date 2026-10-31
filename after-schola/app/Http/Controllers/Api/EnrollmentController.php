<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EnrollmentResource;
use App\Models\EnrollmentDecision;
use App\Models\Registration;
use App\Models\TrialEvaluation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Modul 4 & 5 (bagian pendaftaran): Keputusan lanjut/tidak + Pendaftaran Resmi.
 * Hak akses: permission "manage enrollment" (routes) + scope cabang (Policy "enroll" dan query).
 * Keputusan dicatat staf atas nama orang tua sampai Portal Orang Tua ada (BR-02, ORT-05).
 */
class EnrollmentController extends Controller
{
    private const WITH = ['trainer', 'level', 'lead.parent:id,name,email,phone', 'decision.registration'];

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

        return EnrollmentResource::collection(
            $query->orderByDesc('trial_date')->orderByDesc('id')->get()
        );
    }

    /**
     * Catat / ubah keputusan untuk satu evaluasi.
     */
    public function saveDecision(Request $request, TrialEvaluation $trialEvaluation)
    {
        $this->authorize('enroll', $trialEvaluation); // sebelum validasi: di luar cabang harus 404, bukan 422

        if ($trialEvaluation->attendance !== 'hadir') {
            throw ValidationException::withMessages([
                'decision' => ['Peserta tidak hadir saat trial, keputusan tidak dapat dicatat.'],
            ]);
        }

        $data = $request->validate([
            'decision' => ['required', 'in:lanjut,tidak,pikir_pikir'],
            'reason' => ['nullable', 'string', 'required_if:decision,tidak'],
            'decided_at' => ['nullable', 'date'],
            'follow_up_date' => ['nullable', 'date', 'required_if:decision,pikir_pikir'],
        ]);

        $existing = $trialEvaluation->decision;
        $registration = $existing?->registration;

        // Pendaftaran yang sudah diajukan verifikasi tidak boleh dibatalkan lewat keputusan.
        if ($registration && ! in_array($registration->status, ['draft', 'perlu_revisi'], true) && $data['decision'] !== 'lanjut') {
            throw ValidationException::withMessages([
                'decision' => ['Pendaftaran sudah diajukan untuk verifikasi, keputusan tidak dapat diubah.'],
            ]);
        }
        if ($data['decision'] !== 'pikir_pikir') {
            $data['follow_up_date'] = null;
        }
        $data['decided_at'] = $data['decided_at'] ?? now()->toDateString();
        $data['decided_by'] = $request->user()->id;

        DB::transaction(function () use ($data, $registration, $trialEvaluation) {
            // Draft pendaftaran tidak relevan lagi bila keputusan bukan "lanjut".
            if ($registration && $data['decision'] !== 'lanjut') {
                $registration->delete();
            }

            EnrollmentDecision::updateOrCreate(['evaluation_id' => $trialEvaluation->id], $data);

            // BR-03: tidak lanjut → Leads (data tetap disimpan untuk remarketing). Keputusan lain mengembalikan ke calon.
            if ($lead = $trialEvaluation->lead) {
                if ($data['decision'] === 'tidak') {
                    $lead->update(['status' => 'not_continued']);
                } elseif ($lead->status === 'not_continued') {
                    $lead->update(['status' => 'trial_scheduled']);
                }
            }
        });

        return new EnrollmentResource($trialEvaluation->fresh(self::WITH));
    }

    /**
     * Isi / ubah form pendaftaran resmi (hanya bila keputusan = lanjut & belum diajukan).
     */
    public function saveRegistration(Request $request, TrialEvaluation $trialEvaluation)
    {
        $this->authorize('enroll', $trialEvaluation);

        $decision = $trialEvaluation->decision;
        if (! $decision || $decision->decision !== 'lanjut') {
            throw ValidationException::withMessages([
                'decision' => ['Pendaftaran hanya dapat diisi bila keputusan "lanjut".'],
            ]);
        }

        $existing = $decision->registration;
        if ($existing && ! in_array($existing->status, ['draft', 'perlu_revisi'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Pendaftaran sudah diajukan untuk verifikasi dan tidak dapat diubah.'],
            ]);
        }

        $data = $request->validate([
            'student_name' => ['required', 'string', 'max:255'],
            'birth_date' => ['nullable', 'date', 'before:today'],
            'origin_school' => ['nullable', 'string', 'max:255'],
            'origin_class' => ['nullable', 'string', 'max:50'],
            // Pilih paket (ORT-06): default program slot trial; hanya program aktif.
            'program_id' => ['nullable', 'integer', Rule::exists('programs', 'id')->where('is_active', true)],
            'parent_name' => ['required', 'string', 'max:255'],
            'parent_phone' => ['required', 'string', 'max:30'],
            'parent_email' => ['nullable', 'email', 'max:255'],
            'parent_address' => ['nullable', 'string'],
        ]);

        // Identitas, cabang, dan level berasal dari server (evaluasi trial), bukan dari klien.
        $data['program_id'] ??= $existing?->program_id ?? $trialEvaluation->program_id;
        $data['lead_id'] = $trialEvaluation->lead_id;
        $data['branch_id'] = $trialEvaluation->branch_id;
        $data['level'] = $trialEvaluation->level?->name ?? $existing?->level; // snapshot nama level rekomendasi
        $data['registered_by'] = $request->user()->id;

        Registration::updateOrCreate(['decision_id' => $decision->id], $data);

        return new EnrollmentResource($trialEvaluation->fresh(self::WITH));
    }

    /**
     * Ajukan pendaftaran untuk verifikasi (serah ke Modul 5). Setelah ini terkunci.
     */
    public function submitRegistration(TrialEvaluation $trialEvaluation)
    {
        $this->authorize('enroll', $trialEvaluation);

        $registration = $trialEvaluation->decision?->registration;
        if (! $registration) {
            throw ValidationException::withMessages([
                'registration' => ['Isi data pendaftaran terlebih dahulu.'],
            ]);
        }

        if (! in_array($registration->status, ['draft', 'perlu_revisi'], true)) {
            throw ValidationException::withMessages([
                'status' => ['Pendaftaran sudah diajukan untuk verifikasi.'],
            ]);
        }

        $registration->update(['status' => 'menunggu_verifikasi']);

        return new EnrollmentResource($trialEvaluation->fresh(self::WITH));
    }
}
