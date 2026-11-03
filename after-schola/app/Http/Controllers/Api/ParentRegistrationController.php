<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ParentRegistrationResource;
use App\Models\Lead;
use App\Models\Program;
use App\Models\Registration;
use App\Models\TrialEvaluation;
use App\Models\User;
use App\Support\EnrollmentFlow;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Portal Orang Tua (ORT-06, ORT-07): pendaftaran resmi per anak. Aturan bisnis di EnrollmentFlow (BR-01, BR-04, BR-05);
 * role "parent" dijaga middleware route dan kepemilikan anak dijaga query (anak orang lain = 404).
 */
class ParentRegistrationController extends Controller
{
    public function __construct(private EnrollmentFlow $flow) {}

    // Draft atau pendaftaran yang sudah ada; bila belum ada, isian awal dari trial dan akun orang tua (BR-01, AC-02).
    public function show(Request $request, int $child)
    {
        $lead = $this->ownChild($request, $child);
        $evaluation = $this->evaluationOf($lead);

        $registration = $evaluation->decision?->registration
            ?? $this->flow->editableRegistration($evaluation) // 422 bila keputusan bukan "lanjut"
            ?? $this->prefill($lead, $evaluation, $request->user());

        return $this->respond($registration);
    }

    public function save(Request $request, int $child)
    {
        $evaluation = $this->evaluationOf($this->ownChild($request, $child));
        $this->flow->editableRegistration($evaluation);

        $data = $request->validate(EnrollmentFlow::registrationRules());

        // fresh(): status default (draft) berasal dari kolom DB, belum ada di model yang baru dibuat.
        return $this->respond($this->flow->saveRegistration($evaluation, $data, $request->user())->fresh());
    }

    // Serah ke verifikasi staf (Modul 5); setelah ini terkunci sampai diminta revisi.
    public function submit(Request $request, int $child)
    {
        $evaluation = $this->evaluationOf($this->ownChild($request, $child));

        $this->flow->submitRegistration($evaluation);

        return $this->respond($evaluation->decision->registration->fresh());
    }

    // Selalu 200: PUT adalah upsert idempoten (Laravel akan membalas 201 untuk draft yang baru dibuat).
    private function respond(Registration $registration)
    {
        return (new ParentRegistrationResource($registration))->additional(['options' => [
            'programs' => Program::where('is_active', true)->orderBy('name')->get(['id', 'name', 'jenjang', 'price'])
                ->map(fn (Program $p) => ['id' => $p->id, 'name' => $p->name, 'jenjang' => $p->jenjang, 'price' => (float) $p->price]),
        ]])->response()->setStatusCode(200);
    }

    private function prefill(Lead $lead, TrialEvaluation $evaluation, User $parent): Registration
    {
        return new Registration([
            'student_name' => $lead->name,
            'origin_school' => $lead->origin_school,
            'origin_class' => $evaluation->origin_class,
            'program_id' => $lead->program_id,
            'parent_name' => $parent->name,
            'parent_phone' => $parent->phone,
            'parent_email' => $parent->email,
        ]);
    }

    private function ownChild(Request $request, int $id): Lead
    {
        return Lead::where('parent_id', $request->user()->id)
            ->with('trialEvaluation.level', 'trialEvaluation.decision.registration')
            ->findOrFail($id);
    }

    private function evaluationOf(Lead $lead): TrialEvaluation
    {
        return $lead->trialEvaluation
            ?? throw ValidationException::withMessages(['decision' => ['Trial belum dievaluasi, pendaftaran belum dapat dibuka.']]);
    }
}
