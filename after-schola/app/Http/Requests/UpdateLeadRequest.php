<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class UpdateLeadRequest extends FormRequest
{
    // Otorisasi sebelum validasi: lead di luar cabang user harus 404, bukan 422.
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('lead'));

        return true;
    }

    // Hanya catatan yang boleh diubah staf; data calon berasal dari form orang tua.
    public function rules(): array
    {
        return ['note' => ['nullable', 'string', 'max:2000']];
    }
}
