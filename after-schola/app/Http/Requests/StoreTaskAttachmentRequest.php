<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;

class StoreTaskAttachmentRequest extends FormRequest
{
    // Mengunggah lampiran = mengubah task: Management read-only, non-anggota 404.
    public function authorize(): bool
    {
        Gate::authorize('update', $this->route('task'));

        return true;
    }

    // `mimes` memeriksa tipe dari ISI file (bukan nama/ekstensi dari klien). SVG dan HTML sengaja
    // tidak diizinkan (bisa memuat skrip). Maks 10 MB.
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,gif,txt,csv,docx,xlsx,pptx'],
        ];
    }
}
