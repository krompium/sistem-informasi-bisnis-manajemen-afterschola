<?php

namespace App\Http\Requests;

use App\Models\Branch;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class SaveBranchRequest extends FormRequest
{
    // Otorisasi sebelum validasi: cabang di luar scope harus 404, bukan 422.
    public function authorize(): bool
    {
        $branch = $this->route('branch');
        $branch ? Gate::authorize('update', $branch) : Gate::authorize('create', Branch::class);

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'regex:/^[A-Z0-9]{2,10}$/', Rule::unique('branches', 'code')->ignore($this->route('branch'))],
            'address' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['code.regex' => 'Kode cabang 2-10 karakter huruf kapital atau angka.'];
    }
}
