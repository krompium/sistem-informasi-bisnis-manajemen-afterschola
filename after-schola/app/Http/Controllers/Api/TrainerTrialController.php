<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\EvaluableSlotResource;
use App\Models\TrialSlot;
use Illuminate\Http\Request;

/**
 * TRN-04: slot trial milik trainer beserta pesertanya dan status evaluasi.
 * Staf evaluasi memakai endpoint yang sama untuk slot di cabangnya (pemilih peserta saat menilai).
 * Hak akses: permission di route; scope lewat TrialSlot::evaluableBy.
 */
class TrainerTrialController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['period' => ['nullable', 'in:upcoming,past']]);

        $query = TrialSlot::evaluableBy($request->user())
            ->with(['branch:id,name', 'program:id,name', 'program.levels:id,program_id,name,sort_order', 'leads.trialEvaluation']);

        if ($request->query('period') === 'upcoming') {
            $query->where('starts_at', '>=', now())->orderBy('starts_at');
        } else {
            $request->query('period') === 'past' && $query->where('starts_at', '<', now());
            $query->orderByDesc('starts_at');
        }

        return EvaluableSlotResource::collection($query->paginate(25));
    }
}
