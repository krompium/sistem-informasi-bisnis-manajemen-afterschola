<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreParentStudentRequest;
use App\Http\Resources\ParentStudentDetailResource;
use App\Http\Resources\ParentStudentResource;
use App\Models\Lead;
use App\Models\TrialEvaluation;
use App\Models\TrialSlot;
use App\Notifications\TrialRegisteredNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Portal Orang Tua (ORT-02..07). Role parent dicek di route; anak = lead milik user (leads.parent_id),
 * anak orang lain = 404 (AC-07). Keputusan dan pendaftaran memakai aturan bisnis EnrollmentController (satu sumber).
 */
class ParentStudentController extends Controller
{
    private const LIST_WITH = [
        'program', 'trialSlot.program:id,name', 'trialSlot.branch:id,name',
        'trialEvaluation.decision.registration.activeInvoice.payments',
    ];

    private const DETAIL_WITH = [...self::LIST_WITH, 'trialEvaluation.lead.parent:id,name,email,phone', 'trialEvaluation.level', 'trialEvaluation.trainer:id,name'];

    public function index(Request $request)
    {
        return ParentStudentResource::collection(
            Lead::where('parent_id', $request->user()->id)->with(self::LIST_WITH)->latest('id')->get()
        );
    }

    public function show(Request $request, string $id)
    {
        return $this->detail($this->ownLead($request, $id));
    }

    // ORT-03: tambah anak untuk trial tanpa mengisi ulang data orang tua.
    public function store(StoreParentStudentRequest $request)
    {
        $data = $request->validated();
        $parent = $request->user();

        $lead = DB::transaction(function () use ($data, $parent) {
            $slot = TrialSlot::lockBookable((int) $data['trial_slot_id']);

            return Lead::create([
                'name' => $data['name'],
                'education_level' => $data['education_level'],
                'origin_school' => $data['origin_school'],
                'parent_email' => $parent->email,
                'parent_id' => $parent->id,
                'branch_id' => $slot->branch_id,
                'program_id' => $slot->program_id,
                'trial_slot_id' => $slot->id,
                'status' => 'trial_scheduled',
                'consented_at' => now(),
            ]);
        });

        TrialRegisteredNotification::send($lead, $parent);

        return $this->detail($lead)->response()->setStatusCode(201);
    }

    // ORT-05: hanya orang tua yang memutuskan (BR-02).
    public function decision(Request $request, string $id, EnrollmentController $enrollment)
    {
        $lead = $this->ownLead($request, $id);
        $enrollment->saveDecision($request, $this->evaluationOf($lead));

        return $this->detail($lead);
    }

    // ORT-06/07: isi atau perbaiki formulir pendaftaran (draft atau perlu revisi).
    public function registration(Request $request, string $id, EnrollmentController $enrollment)
    {
        $lead = $this->ownLead($request, $id);
        $enrollment->saveRegistration($request, $this->evaluationOf($lead));

        return $this->detail($lead);
    }

    // Ajukan ke verifikasi; setelah ini terkunci sampai admin meminta revisi.
    public function submit(Request $request, string $id, EnrollmentController $enrollment)
    {
        $lead = $this->ownLead($request, $id);
        $enrollment->submitRegistration($this->evaluationOf($lead));

        return $this->detail($lead);
    }

    private function ownLead(Request $request, string $id): Lead
    {
        return Lead::where('parent_id', $request->user()->id)->findOrFail($id);
    }

    private function evaluationOf(Lead $lead): TrialEvaluation
    {
        return $lead->trialEvaluation ?? throw ValidationException::withMessages(['trial' => ['Hasil trial belum tersedia.']]);
    }

    private function detail(Lead $lead): ParentStudentDetailResource
    {
        return new ParentStudentDetailResource(Lead::with(self::DETAIL_WITH)->findOrFail($lead->id));
    }
}
