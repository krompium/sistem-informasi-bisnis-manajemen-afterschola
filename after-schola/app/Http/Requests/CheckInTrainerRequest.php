<?php

namespace App\Http\Requests;

use App\Models\TrainerAttendance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Check-in trainer pada sesi reguler (Modul 7b, BR-15). Bukti dan GPS mengikuti mode sesi:
 * onsite = foto + GPS, online = screenshot dan GPS ditolak. Status tidak_hadir tidak wajib bukti.
 */
class CheckInTrainerRequest extends FormRequest
{
    public function authorize(): bool
    {
        Gate::authorize('checkInRegular', $this->route('class_session'));

        return true;
    }

    public function rules(): array
    {
        $onsite = $this->route('class_session')->mode === 'onsite';
        $needsProof = $this->input('status') !== 'tidak_hadir';

        $coordinate = fn (int $limit, string $other) => $onsite
            ? [$needsProof ? 'required' : 'nullable', 'numeric', "between:-{$limit},{$limit}", "required_with:{$other}"]
            : ['prohibited'];

        return [
            'status' => ['required', Rule::in(TrainerAttendance::STATUSES)],
            // Upload aman (PRD 5.3): hanya jpeg/png, maksimal 5 MB. Nama file dibuat server (UUID).
            'photo' => [$needsProof ? 'required' : 'nullable', 'file', 'mimes:jpeg,jpg,png', 'max:5120'],
            'latitude' => $coordinate(90, 'longitude'),
            'longitude' => $coordinate(180, 'latitude'),
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'photo.required' => $this->route('class_session')->mode === 'online' ? 'Screenshot wajib diunggah.' : 'Foto wajib diunggah.',
            'photo.mimes' => 'Berkas harus berupa gambar JPG atau PNG.',
            'photo.max' => 'Ukuran berkas maksimal 5 MB.',
            'latitude.required' => 'Lokasi GPS wajib untuk sesi onsite.',
            'longitude.required' => 'Lokasi GPS wajib untuk sesi onsite.',
            'latitude.prohibited' => 'Sesi online tidak boleh mengirim lokasi GPS.',
            'longitude.prohibited' => 'Sesi online tidak boleh mengirim lokasi GPS.',
        ];
    }
}
