<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreChildRequest;
use App\Http\Resources\ParentChildResource;
use App\Models\Lead;
use App\Models\TrialSlot;
use App\Notifications\TrialRegisteredNotification;
use App\Support\EnrollmentFlow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Portal Orang Tua (ORT-02..05): daftar anak, tambah anak untuk trial, hasil trial, keputusan lanjut/tidak.
 * Role "parent" dijaga middleware route; kepemilikan anak dijaga query (anak orang lain = 404, BR-19).
 */
class ParentChildController extends Controller
{
    private const WITH = [
        'program:id,name,price', 'branch:id,name', 'trialSlot:id,starts_at',
        'trialEvaluation.level', 'trialEvaluation.decision.registration.activeInvoice', 'trialEvaluation.decision.registration.student.enrollments',
    ];

    public function __construct(private EnrollmentFlow $flow) {}

    public function index(Request $request)
    {
        return ParentChildResource::collection(
            Lead::where('parent_id', $request->user()->id)->with(self::WITH)->latest('id')->get()
        );
    }

    public function show(Request $request, int $child)
    {
        return new ParentChildResource($this->ownChild($request, $child));
    }

    // ORT-03: anak baru memesan slot trial; data orang tua dari akun yang login, persetujuan dicatat lagi (NFR-04).
    public function store(StoreChildRequest $request)
    {
        $data = $request->validated();
        $parent = $request->user();

        $lead = DB::transaction(function () use ($data, $parent) {
            $slot = TrialSlot::lockBookable((int) $data['trial_slot_id']);

            return Lead::create([
                'name' => $data['name'],
                'education_level' => $data['education_level'],
                'origin_school' => $data['origin_school'],
                'parent_email' => $parent->email, // TODO(nanti): hapus kolom ini, sumber tunggal users.email
                'parent_id' => $parent->id,
                'branch_id' => $slot->branch_id,
                'program_id' => $slot->program_id,
                'trial_slot_id' => $slot->id,
                'status' => 'trial_scheduled',
                'consented_at' => now(),
            ]);
        });

        // Kegagalan kirim tidak membatalkan pendaftaran: dicatat (NFR-07), notifikasi in-app tetap tersimpan.
        try {
            $parent->notify(new TrialRegisteredNotification($lead));
        } catch (\Throwable $e) {
            Log::error('Gagal mengirim konfirmasi pendaftaran trial.', ['lead_id' => $lead->id, 'error' => $e->getMessage()]);
        }

        return (new ParentChildResource($this->ownChild($request, $lead->id)))->response()->setStatusCode(201);
    }

    // ORT-05: hanya orang tua yang memutuskan (BR-02); pikir_pikir hanya bisa dicatat staf.
    public function decision(Request $request, int $child)
    {
        $lead = $this->ownChild($request, $child);

        $data = $request->validate([
            'decision' => ['required', 'in:lanjut,tidak'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if (! $lead->trialEvaluation) {
            throw ValidationException::withMessages(['decision' => ['Trial belum dievaluasi, keputusan belum dapat dibuat.']]);
        }

        $this->flow->decide($lead->trialEvaluation, $data, $request->user());

        return new ParentChildResource($this->ownChild($request, $child));
    }

    private function ownChild(Request $request, int $id): Lead
    {
        return Lead::where('parent_id', $request->user()->id)->with(self::WITH)->findOrFail($id);
    }
}
